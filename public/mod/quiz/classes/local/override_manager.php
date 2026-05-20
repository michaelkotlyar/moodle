<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_quiz\local;

use cm_info;
use core\context\course as context_course;
use core\context\module as context_module;
use core_group\hook\after_group_membership_added;
use core_group\hook\after_group_membership_removed;
use core_user\fields;
use mod_quiz_mod_form;
use mod_quiz\access_manager;
use mod_quiz\event\group_override_created;
use mod_quiz\event\group_override_deleted;
use mod_quiz\event\group_override_updated;
use mod_quiz\event\user_override_created;
use mod_quiz\event\user_override_deleted;
use mod_quiz\event\user_override_updated;
use mod_quiz\form\edit_override_form;
use mod_quiz\quiz_settings;
use stdClass;

/**
 * Manager class for quiz overrides
 *
 * @package   mod_quiz
 * @copyright 2024 Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class override_manager {
    /** @var array quiz setting keys that can be overwritten **/
    private const OVERRIDEABLE_QUIZ_SETTINGS = ['timeopen', 'timeclose', 'timelimit', 'attempts', 'password'];

    /** @var access_manager The quiz access manager **/
    protected access_manager $accessmanager;

    /** @var cm_info|stdClass The quiz course module object related to the quiz */
    protected readonly cm_info|stdClass $cm;

    /** @var stdClass The quiz course object related to the quiz */
    protected readonly stdClass $course;

    /** @var stdClass The quiz linked to this manager instance **/
    protected readonly stdClass $quiz;

    /** @var context_module The context being operated in **/
    public readonly context_module $context;

    /**
     * Create override manager
     *
     * @param quiz_settings $quizobj The information of the quiz required by the override manager
     */
    public function __construct(quiz_settings &$quizobj) {
        global $CFG;
        // Required for quiz_* methods.
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $this->quiz = $quizobj->get_quiz();
        $this->context = $quizobj->get_context();
        $this->cm = $quizobj->get_cm();
        $this->course = $quizobj->get_course();
        $this->accessmanager = $quizobj->get_access_manager(time());
    }

    /**
     * Returns all overrides for the linked quiz.
     *
     * @return array of quiz_override records
     */
    public function get_all_overrides(): array {
        global $DB;
        return $DB->get_records('quiz_overrides', ['quiz' => $this->quiz->id]);
    }

    /**
     * Validates the data, usually from a moodleform or a webservice call.
     *
     * If it contains an 'id' property, additional validation is performed against the existing record.
     * Calls {@see mod_quiz\access_manager::validate_override_form_fields()} to validate access rule plugin specific
     * override setttings.
     *
     * @see mod_quiz\access_manager::validate_override_form_fields()
     * @param array $formdata data from moodleform or webservice call.
     * @return array array where the keys are error elements, and the values are lists of errors for each element.
     */
    public function validate_data(array $formdata): array {
        global $DB;

        // Because this can be called directly (e.g. via edit_override_form)
        // and not just through save_override, we must ensure the data
        // is parsed in the same way.
        $formdata = $this->parse_formdata($formdata);

        $data = $formdata;
        $formdata = (object) $formdata;

        $errors = [];

        // Ensure at least one of the overrideable settings is set.
        $requiredkeys = $this->get_override_required_setting_names();
        $keysthatareset = array_map(function ($key) use ($formdata) {
            return isset($formdata->$key) && !is_null($formdata->$key);
        }, $requiredkeys);

        $hasoverridevalues = in_array(true, $keysthatareset, true);

        // If updating, we can also just update the reason.
        if (!empty($formdata->id) && (property_exists($formdata, 'reason') || property_exists($formdata, 'reasonformat'))) {
            $hasoverridevalues = true;
        }

        if (!$hasoverridevalues) {
            $errors['general'][] = new \lang_string('nooverridedata', 'quiz');
        }

        // Ensure quiz is a valid quiz.
        if (empty($formdata->quiz) || empty(get_coursemodule_from_instance('quiz', $formdata->quiz))) {
            $errors['quiz'][] = new \lang_string('overrideinvalidquiz', 'quiz');
        }

        // Ensure either userid or groupid is set.
        if (empty($formdata->userid) && empty($formdata->groupid)) {
            $errors['general'][] = new \lang_string('overridemustsetuserorgroup', 'quiz');
        }

        // Ensure not both userid and groupid are set.
        if (!empty($formdata->userid) && !empty($formdata->groupid)) {
            $errors['general'][] = new \lang_string('overridecannotsetbothgroupanduser', 'quiz');
        }

        // If group is set, ensure it is a real group.
        if (!empty($formdata->groupid) && empty(groups_get_group($formdata->groupid))) {
            $errors['groupid'][] = new \lang_string('overrideinvalidgroup', 'quiz');
        }

        // If user is set, ensure it is a valid user.
        if (!empty($formdata->userid) && !\core_user::is_real_user($formdata->userid, true)) {
            $errors['userid'][] = new \lang_string('overrideinvaliduser', 'quiz');
        }

        // Ensure timeclose is later than timeopen, if both are set.
        if (!empty($formdata->timeclose) && !empty($formdata->timeopen) && $formdata->timeclose <= $formdata->timeopen) {
            $errors['timeclose'][] = new \lang_string('closebeforeopen', 'quiz');
        }

        // Ensure attempts is a integer greater than or equal to 0 (0 is unlimited attempts).
        if (isset($formdata->attempts) && ((int) $formdata->attempts < 0)) {
            $errors['attempts'][] = new \lang_string('overrideinvalidattempts', 'quiz');
        }

        // Ensure timelimit is greather than zero.
        if (!empty($formdata->timelimit) && $formdata->timelimit <= 0) {
            $errors['timelimit'][] = new \lang_string('overrideinvalidtimelimit', 'quiz');
        }

        // Ensure other records do not exist with the same group or user.
        if (!empty($formdata->quiz) && (!empty($formdata->userid) || !empty($formdata->groupid))) {
            $existingrecordparams = ['quiz' => $formdata->quiz, 'groupid' => $formdata->groupid ?? null,
                'userid' => $formdata->userid ?? null, ];
            $records = $DB->get_records('quiz_overrides', $existingrecordparams, '', 'id');

            // Ignore self if updating.
            if (!empty($formdata->id)) {
                unset($records[$formdata->id]);
            }

            // If count is not zero, it means existing records exist already for this user/group.
            if (!empty($records)) {
                $errors['general'][] = new \lang_string('overridemultiplerecordsexist', 'quiz');
            }
        }

        // If is existing record, validate it against the existing record.
        if (!empty($formdata->id)) {
            $existingrecorderrors = self::validate_against_existing_record($formdata->id, $formdata);
            $errors = array_merge($errors, $existingrecorderrors);
        }

        // Implode each value (array of error strings) into a single error string.
        foreach ($errors as $key => $value) {
            $errors[$key] = implode(",", $value);
        }

        // Apply access rule plugin validation.
        $errors = $this->accessmanager->validate_override_form_fields($errors, $data);

        return $errors;
    }

    /**
     * Returns the existing quiz override record with the given ID or null if does not exist.
     *
     * @param int $id existing quiz override id
     * @return ?\stdClass record, if exists
     */
    private static function get_existing(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record('quiz_overrides', ['id' => $id]) ?: null;
    }

    /**
     * Validates the formdata against an existing record.
     *
     * @param int $existingid id of existing quiz override record
     * @param \stdClass $formdata formdata, usually from moodleform or webservice call.
     * @return array array where the keys are error elements, and the values are lists of errors for each element.
     */
    private static function validate_against_existing_record(int $existingid, \stdClass $formdata): array {
        $existingrecord = self::get_existing($existingid);
        $errors = [];

        // Existing record must exist.
        if (empty($existingrecord)) {
            $errors['general'][] = new \lang_string('overrideinvalidexistingid', 'quiz');
        }

        // Group value must match existing record if it is set in the formdata.
        if (!empty($existingrecord) && !empty($formdata->groupid) && $existingrecord->groupid != $formdata->groupid) {
            $errors['groupid'][] = new \lang_string('overridecannotchange', 'quiz');
        }

        // User value must match existing record if it is set in the formdata.
        if (!empty($existingrecord) && !empty($formdata->userid) && $existingrecord->userid != $formdata->userid) {
            $errors['userid'][] = new \lang_string('overridecannotchange', 'quiz');
        }

        return $errors;
    }

    /**
     * Parses the formdata by finding only the OVERRIDEABLE_QUIZ_SETTINGS,
     * clearing any values that match the existing quiz, and re-adds the user or group id.
     *
     * @param array $formdata data usually from moodleform or webservice call.
     * @return array array containing parsed formdata, with keys as the properties and values as the values.
     * Any values set the same as the existing quiz are set to null.
     */
    public function parse_formdata(array $formdata): array {
        // Get the data from the form that we want to update.
        $keys = $this->get_override_setting_names();
        $settings = array_intersect_key($formdata, array_flip($keys));

        // Remove values that are the same as currently in the quiz.
        $settings = $this->clear_unused_values($settings);

        // Pass through the optional reason fields unchanged.
        if (array_key_exists('reason', $formdata)) {
            $settings['reason'] = $formdata['reason'];
        }
        if (array_key_exists('reasonformat', $formdata) && $formdata['reasonformat'] !== null) {
            $settings['reasonformat'] = $formdata['reasonformat'];
        }

        // Add the user / group back as applicable.
        $userorgroupdata = array_intersect_key($formdata, array_flip(['userid', 'groupid', 'quiz', 'id']));

        return array_merge($settings, $userorgroupdata);
    }

    /**
     * Saves the given override. If an id is given, it updates, otherwise it creates a new one.
     * Note, capabilities are not checked, {@see require_manage_capability()}
     *
     * @param array $formdata data usually from moodleform or webservice call.
     * @return int updated/inserted record id
     */
    public function save_override(array $formdata): int {
        global $DB;

        // Extract only the necessary data.
        $datatoset = $this->parse_formdata($formdata);
        $datatoset['quiz'] = $this->quiz->id;

        // Validate the data is OK.
        $errors = $this->validate_data($datatoset);
        if (!empty($errors)) {
            $errorstr = implode(',', $errors);
            throw new \invalid_parameter_exception($errorstr);
        }

        // Insert or update.
        $id = $datatoset['id'] ?? 0;
        if (!empty($id)) {
            $DB->update_record('quiz_overrides', $datatoset);
        } else {
            $id = $DB->insert_record('quiz_overrides', $datatoset);
        }

        $datatoset['overrideid'] = $id;

        $userid = $datatoset['userid'] ?? null;
        $groupid = $datatoset['groupid'] ?? null;

        // Clear the cache.
        if (!empty($userid)) {
            quiz_overrides_cache_manager::purge_for_user($this->quiz->id, $userid);
        }
        if (!empty($groupid)) {
            quiz_overrides_cache_manager::purge_for_group($this->quiz->id, $groupid);
        }

        // Trigger moodle events.
        if (empty($formdata['id'])) {
            $this->fire_created_event($id, $userid, $groupid);
        } else {
            $this->fire_updated_event($id, $userid, $groupid);
        }

        // Update open events.
        quiz_update_open_attempts(['quizid' => $this->quiz->id]);

        // Update calendar events.
        $isgroup = !empty($datatoset['groupid']);
        if ($isgroup) {
            // If is group, must update the entire quiz calendar events.
            quiz_update_events($this->quiz);
        } else {
            // If is just a user, can update only their calendar event.
            quiz_update_events($this->quiz, (object) $datatoset);
        }

        // Update access-rule override data.
        $this->accessmanager->save_override_settings((object) $datatoset);

        return $id;
    }

    /**
     * Deletes all the overrides for the linked quiz
     *
     * @param bool $shouldlog If true, will log a override_deleted event
     */
    public function delete_all_overrides(bool $shouldlog = true): void {
        global $DB;
        $overrides = $DB->get_records('quiz_overrides', ['quiz' => $this->quiz->id], '', 'id,quiz,userid,groupid');
        $this->delete_overrides($overrides, $shouldlog);
    }

    /**
     * Deletes overrides given just their ID.
     * Note, the given IDs must exist and user must have access to them otherwise an exception will be thrown.
     * Also note, capabilities are not checked, {@see require_manage_capability()}
     *
     * @param array $ids IDs of overrides to delete
     * @param bool $shouldlog If true, will log a override_deleted event
     */
    public function delete_overrides_by_id(array $ids, bool $shouldlog = true): void {
        global $DB;

        // Filter for those overrides user can access.
        [$sql, $params] = self::get_override_in_sql($this->quiz->id, $ids);
        $records = array_filter(
            $DB->get_records_select('quiz_overrides', $sql, $params, '', 'id,quiz,userid,groupid'),
            fn(stdClass $override) => $this->can_view_override($override),
        );

        // Ensure all the given ids exist, so the user is aware if they give a dodgy id.
        $missingids = array_diff($ids, array_keys($records));
        if (!empty($missingids)) {
            throw new \invalid_parameter_exception(get_string('overridemissingdelete', 'quiz', implode(',', $missingids)));
        }

        $this->delete_overrides($records, $shouldlog);
    }


    /**
     * Builds sql and parameters to find overrides in quiz with the given ids
     *
     * @param int $quizid id of quiz
     * @param array $ids array of quiz override ids
     * @return array sql and params
     */
    private static function get_override_in_sql(int $quizid, array $ids): array {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $params = array_merge($inparams, ['quizid' => $quizid]);
        $sql = 'id ' . $insql . ' AND quiz = :quizid';
        return [$sql, $params];
    }

    /**
     * Deletes the given overrides in the quiz linked to the override manager.
     * Note - capabilities are not checked, {@see require_manage_capability()}
     *
     * @param array $overrides override to delete. Must specify an id, quizid, and either a userid or groupid.
     * @param bool $shouldlog If true, will log a override_deleted event
     */
    public function delete_overrides(array $overrides, bool $shouldlog = true): void {
        global $DB;

        foreach ($overrides as $override) {
            if (empty($override->id)) {
                throw new \coding_exception("All overrides must specify an ID");
            }

            if (empty($override->quiz)) {
                throw new \coding_exception("All overrides must specify a quiz ID");
            }

            if ($override->quiz != $this->quiz->id) {
                throw new \coding_exception("All overrides must belong to the quiz linked to this manager");
            }

            // Sanity check that user xor group is specified.
            // User or group is required to clear the cache.
            self::ensure_userid_xor_groupid_set($override->userid ?? null, $override->groupid ?? null);
        }

        if (empty($overrides)) {
            // Exit early, since delete select requires at least 1 record.
            return;
        }

        // Match id and quiz.
        [$sql, $params] = self::get_override_in_sql($this->quiz->id, array_column($overrides, 'id'));
        $DB->delete_records_select('quiz_overrides', $sql, $params);

        // Perform other cleanup.
        quiz_overrides_cache_manager::purge_for_overrides($overrides);
        foreach ($overrides as $override) {
            $userid = $override->userid ?? null;
            $groupid = $override->groupid ?? null;

            $this->delete_override_events($userid, $groupid);

            if ($shouldlog) {
                $this->fire_deleted_event($override->id, $userid, $groupid);
            }
        }

        // Delete quizaccess rule override data.
        $this->accessmanager->delete_override_settings($overrides);
    }

    /**
     * Ensures either userid or groupid is set, but not both.
     * If neither or both are set, a coding exception is thrown.
     *
     * @param ?int $userid user for the record, or null
     * @param ?int $groupid group for the record, or null
     */
    private static function ensure_userid_xor_groupid_set(?int $userid = null, ?int $groupid = null): void {
        $groupset = !empty($groupid);
        $userset = !empty($userid);

        // If either set, but not both (xor).
        $xorset = $groupset ^ $userset;

        if (!$xorset) {
            throw new \coding_exception("Either userid or groupid must be specified, but not both.");
        }
    }

    /**
     * Deletes the events associated with the override.
     *
     * @param ?int $userid or null if groupid is specified
     * @param ?int $groupid or null if the userid is specified
     */
    private function delete_override_events(?int $userid = null, ?int $groupid = null): void {
        global $DB;

        // Sanity check.
        self::ensure_userid_xor_groupid_set($userid, $groupid);

        $eventssearchparams = ['modulename' => 'quiz', 'instance' => $this->quiz->id];

        if (!empty($userid)) {
            $eventssearchparams['userid'] = $userid;
        }

        if (!empty($groupid)) {
            $eventssearchparams['groupid'] = $groupid;
        }

        $events = $DB->get_records('event', $eventssearchparams);
        foreach ($events as $event) {
            $eventold = \calendar_event::load($event);
            $eventold->delete();
        }
    }

    /**
     * Requires the user has the override management capability
     */
    public function require_manage_capability(): void {
        require_capability('mod/quiz:manageoverrides', $this->context);
    }

    /**
     * Requires the user has the override viewing capability
     */
    public function require_read_capability(): void {
        // If user can manage, they can also view.
        // It would not make sense to be able to create and edit overrides without being able to view them.
        if (!has_any_capability(['mod/quiz:viewoverrides', 'mod/quiz:manageoverrides'], $this->context)) {
            throw new \required_capability_exception($this->context, 'mod/quiz:viewoverrides', 'nopermissions', '');
        }
    }

    /**
     * Determine whether user can view a given override record
     *
     * @param stdClass $override An object containing at least a userid or groupid property.
     * @return bool
     */
    public function can_view_override(stdClass $override): bool {
        if ($override->groupid) {
            return groups_group_visible($override->groupid, $this->course, $this->cm);
        } else {
            return groups_user_groups_visible($this->course, $override->userid, $this->cm);
        }
    }

    /**
     * Builds common event data
     *
     * @param int $id override id
     * @return array of data to add as parameters to an event.
     */
    private function get_base_event_params(int $id): array {
        return [
            'context' => $this->context,
            'other' => [
                'quizid' => $this->quiz->id,
            ],
            'objectid' => $id,
        ];
    }

    /**
     * Log that a given override was deleted
     *
     * @param int $id of quiz override that was just deleted
     * @param ?int $userid user attached to override record, or null
     * @param ?int $groupid group attached to override record, or null
     */
    private function fire_deleted_event(int $id, ?int $userid = null, ?int $groupid = null): void {
        // Sanity check.
        self::ensure_userid_xor_groupid_set($userid, $groupid);

        $params = $this->get_base_event_params($id);
        $params['objectid'] = $id;

        if (!empty($userid)) {
            $params['relateduserid'] = $userid;
            user_override_deleted::create($params)->trigger();
        }

        if (!empty($groupid)) {
            $params['other']['groupid'] = $groupid;
            group_override_deleted::create($params)->trigger();
        }
    }


    /**
     * Log that a given override was created
     *
     * @param int $id of quiz override that was just created
     * @param ?int $userid user attached to override record, or null
     * @param ?int $groupid group attached to override record, or null
     */
    private function fire_created_event(int $id, ?int $userid = null, ?int $groupid = null): void {
        // Sanity check.
        self::ensure_userid_xor_groupid_set($userid, $groupid);

        $params = $this->get_base_event_params($id);

        if (!empty($userid)) {
            $params['relateduserid'] = $userid;
            user_override_created::create($params)->trigger();
        }

        if (!empty($groupid)) {
            $params['other']['groupid'] = $groupid;
            group_override_created::create($params)->trigger();
        }
    }

    /**
     * Log that a given override was updated
     *
     * @param int $id of quiz override that was just updated
     * @param ?int $userid user attached to override record, or null
     * @param ?int $groupid group attached to override record, or null
     */
    private function fire_updated_event(int $id, ?int $userid = null, ?int $groupid = null): void {
        // Sanity check.
        self::ensure_userid_xor_groupid_set($userid, $groupid);

        $params = $this->get_base_event_params($id);

        if (!empty($userid)) {
            $params['relateduserid'] = $userid;
            user_override_updated::create($params)->trigger();
        }

        if (!empty($groupid)) {
            $params['other']['groupid'] = $groupid;
            group_override_updated::create($params)->trigger();
        }
    }

    /**
     * Clears any overrideable settings in the formdata, where the value matches what is already in the quiz
     * If they match, the data is set to null.
     *
     * @param array $formdata data usually from moodleform or webservice call.
     * @return array formdata with same values cleared
     */
    private function clear_unused_values(array $formdata): array {
        foreach (self::OVERRIDEABLE_QUIZ_SETTINGS as $key) {
            // If the formdata is the same as the current quiz object data, clear it.
            if (isset($formdata[$key]) && $formdata[$key] == $this->quiz->$key) {
                $formdata[$key] = null;
            }

            // Ensure these keys always are set (even if null).
            $formdata[$key] = $formdata[$key] ?? null;

            // If the formdata is empty, set it to null.
            // This avoids putting 0, false, or '' into the DB since the override logic expects null.
            // Attempts is the exception, it can have a integer value of '0', so we use is_numeric instead.
            if ($key != 'attempts' && empty($formdata[$key])) {
                $formdata[$key] = null;
            }

            if ($key == 'attempts' && !is_numeric($formdata[$key])) {
                $formdata[$key] = null;
            }
        }

        $formdata = $this->accessmanager->clean_override_form_data($formdata);

        return $formdata;
    }

    /**
     * Computes the effective overridden open/close times for a user for a given quiz.
     *
     * @param int $quizid The quiz ID.
     * @param int $userid The user ID.
     * @return array Array with optional keys 'timeopen' and 'timeclose'.
     */
    public static function get_effective_open_close_times(int $quizid, int $userid): array {
        $overrides = quiz_overrides_cache_manager::get_overrides($quizid, $userid);

        if (empty($overrides)) {
            return [];
        }

        // Get user override (there should be at most one per user per quiz).
        $useroverride = array_filter($overrides, fn($o): bool => !empty($o->userid));
        $useroverride = reset($useroverride);

        $timeopen = empty($useroverride) ? null : $useroverride->timeopen;
        $timeclose = empty($useroverride) ? null : $useroverride->timeclose;

        // If either value is still null, check group overrides.
        if ($timeopen === null || $timeclose === null) {
            $groupoverrides = array_filter($overrides, fn($o): bool => !empty($o->groupid));
            if (!empty($groupoverrides)) {
                $opens = array_filter(array_column($groupoverrides, 'timeopen'), fn($t): bool => $t !== null);
                $closes = array_filter(array_column($groupoverrides, 'timeclose'), fn($t): bool => $t !== null);

                // Get the earliest open time.
                if ($timeopen === null && count($opens)) {
                    $timeopen = min($opens);
                }

                // Get the latest close time, unless any are 0 which takes precedence.
                if ($timeclose === null && count($closes)) {
                    $timeclose = in_array(0, $closes) ? 0 : max($closes);
                }
            }
        }

        $result = [];
        if ($timeopen !== null) {
            $result['timeopen'] = $timeopen;
        }
        if ($timeclose !== null) {
            $result['timeclose'] = $timeclose;
        }

        return $result;
    }

    /**
     * Deletes orphaned group overrides in a given course.
     * Note - permissions are not checked and events are not logged for performance reasons.
     *
     * @param int $courseid ID of course to delete orphaned group overrides in
     * @return array array of quizzes that had orphaned group overrides.
     */
    public static function delete_orphaned_group_overrides_in_course(int $courseid): array {
        global $DB;

        // It would be nice if we got the groupid that was deleted.
        // Instead, we just update all quizzes with orphaned group overrides.
        $sql = "SELECT o.id, o.quiz, o.groupid
                  FROM {quiz_overrides} o
                  JOIN {quiz} quiz ON quiz.id = o.quiz
             LEFT JOIN {groups} grp ON grp.id = o.groupid
                 WHERE quiz.course = :courseid
                   AND o.groupid IS NOT NULL
                   AND grp.id IS NULL";
        $params = ['courseid' => $courseid];
        $records = $DB->get_records_sql($sql, $params);

        $DB->delete_records_list('quiz_overrides', 'id', array_keys($records));

        // Clear the cache for all users in the course for each quiz that had an orphaned group override.
        $quizids = array_unique(array_column($records, 'quiz'));
        $userids = array_keys(get_enrolled_users(context_course::instance($courseid), '', 0, 'u.id'));
        foreach ($quizids as $quizid) {
            quiz_overrides_cache_manager::purge_for_users($quizid, $userids);
        }

        return $quizids;
    }

    /**
     * Hook callback to clear relevant cache entries when a user is added to a group.
     *
     * @param after_group_membership_added $hook
     */
    public static function after_group_membership_added(after_group_membership_added $hook): void {
        quiz_overrides_cache_manager::purge_for_group_members($hook->groupinstance->id, $hook->userids);
    }

    /**
     * Hook callback to clear relevant cache entries when a user is removed from a group.
     *
     * @param after_group_membership_removed $hook
     */
    public static function after_group_membership_removed(after_group_membership_removed $hook): void {
        quiz_overrides_cache_manager::purge_for_group_members($hook->groupinstance->id, $hook->userids);
    }

    /**
     * Determine if the user can view all groups in the quiz.
     *
     * Used to help fetch the groups the user is allowed to see.
     *
     * @link ../../overrides.php
     * @return bool $showallgroups True if the user has access to all groups in the course.
     */
    public function can_showallgroups(): bool {
        return groups_get_activity_groupmode($this->cm) === NOGROUPS
            || has_capability('moodle/site:accessallgroups', $this->context);
    }

    /**
     * Get the groups the user is able to view in the override manager.
     *
     * The function checks if the user can view all groups, if so can fetch all groups in the course, otherwise
     * will fetch groups the user is allowed to see in the activity.
     *
     * @link ../../overrides.php
     * @return array $groups A list of group objects in the quiz, returns an empty array if no groups found.
     */
    public function get_groups(): array {
        $groups = $this->can_showallgroups()
            ? groups_get_all_groups($this->course->id)
            : groups_get_activity_allowed_groups($this->cm);
        return $groups ?: [];
    }

    /**
     * Get override by ID.
     *
     * Adds properties of access rule plugin overrides. The override object belong to the quiz the override manager belong.
     *
     * @link ../../overrides.php
     * @param int $overrideid The ID of the override record in the quiz_overrides table.
     * @param int $strictness One of IGNORE_MISSING or MUST_EXIST.
     * @return ?stdClass $override The override object, returns null if no override found and $strictness is set to IGNORE_MISSING.
     * @throws \dml_exception if quiz_overrides record not found and respective $strictness is set.
     */
    public function get_override_by_id(int $overrideid, int $strictness = MUST_EXIST): ?stdClass {
        global $DB;
        [$accessselects, $accessjoins, $accessparams] = $this->accessmanager->get_override_settings_sql();
        $accessrulesqlselects = !empty($accessselects) ? ", $accessselects" : '';
        $sql = "SELECT o.* {$accessrulesqlselects}
                FROM {quiz_overrides} o
                {$accessjoins}
                WHERE o.id = ? AND o.quiz = ?";
        $accessparams[] = $overrideid;
        $accessparams[] = $this->quiz->id;
        return $DB->get_record_sql($sql, $accessparams, $strictness) ?: null;
    }

    /**
     * Get the override settings of a user in the quiz.
     *
     * Looks through user targeted overrides first, then checks for group overrides that will apply to the user.
     *
     * @see self::get_effective_override() to get the combined override settings for a user.
     * @param int $userid user ID. Must be a user that is enrolled in the course of the override manager quiz.
     * @return ?stdClass
     */
    protected function get_user_override(int $userid): ?stdClass {
        global $DB;

        $override = $DB->get_record('quiz_overrides', ['quiz' => $this->quiz->id, 'userid' => $userid]) ?: null;

        if ($override) {
            $accessruleoverridesettings = $this->accessmanager->get_override_settings($userid);
            $override = (object) array_merge((array) $override, $accessruleoverridesettings);
        }

        return $override;
    }

    /**
     * Fetches the effective override for a user in the quiz, taking into account user and group targeted overrides.
     *
     * If the user has a user-targeted override, properties from that override will be used. For any setting not specified in the
     * user-targeted override, the group-targeted overrides will be checked and applied. If multiple group-targeted overrides
     * apply to the user and specify a value for the same setting, the most lenient value will be used (e.g. latest close time,
     * earliest open time, highest attempts allowed, etc.).
     *
     * @link ../../lib.php calls quiz_update_effective_access() to fetch the override settings for a user.
     * @param int $userid The ID of the user fetch the quiz override for.
     * @return stdClass $override The final override settings object.
     */
    public function get_effective_override(int $userid): stdClass {
        // Check for user override.
        $override = $this->get_user_override($userid);

        if (!$override) {
            $override = (object) [
                'timeopen' => null,
                'timeclose' => null,
                'timelimit' => null,
                'attempts' => null,
                'password' => null,
            ];
        }

        // Check for group overrides.
        $gpoverrides = $this->get_group_overrides($userid);

        if ($gpoverrides) {
            // Combine the overrides.
            $opens = [];
            $closes = [];
            $limits = [];
            $attempts = [];
            $passwords = [];

            foreach ($gpoverrides as $gpoverride) {
                if (isset($gpoverride->timeopen)) {
                    $opens[] = $gpoverride->timeopen;
                }
                if (isset($gpoverride->timeclose)) {
                    $closes[] = $gpoverride->timeclose;
                }
                if (isset($gpoverride->timelimit)) {
                    $limits[] = $gpoverride->timelimit;
                }
                if (isset($gpoverride->attempts)) {
                    $attempts[] = $gpoverride->attempts;
                }
                if (isset($gpoverride->password)) {
                    $passwords[] = $gpoverride->password;
                }
            }
            // If there is a user override for a setting, ignore the group override.
            if (is_null($override->timeopen) && count($opens)) {
                $override->timeopen = min($opens);
            }
            if (is_null($override->timeclose) && count($closes)) {
                if (in_array(0, $closes)) {
                    $override->timeclose = 0;
                } else {
                    $override->timeclose = max($closes);
                }
            }
            if (is_null($override->timelimit) && count($limits)) {
                if (in_array(0, $limits)) {
                    $override->timelimit = 0;
                } else {
                    $override->timelimit = max($limits);
                }
            }
            if (is_null($override->attempts) && count($attempts)) {
                if (in_array(0, $attempts)) {
                    $override->attempts = 0;
                } else {
                    $override->attempts = max($attempts);
                }
            }
            if (is_null($override->password) && count($passwords)) {
                $override->password = array_shift($passwords);
                if (count($passwords)) {
                    $override->extrapasswords = $passwords;
                }
            }

            // Combine with access rule plugin override settings as well.
            $override = $this->accessmanager->combine_group_overrides($override, $gpoverrides);
        }

        return $override;
    }

    /**
     * Provide a list of group overrides in the quiz.
     *
     * Retrieves group overrides for user-targeted or all group overrides in the quiz. Used by quiz_update_effective_access()
     * in overrides.php to fetch all group overrides in the quiz and in {@see self::get_effective_override()} to check
     * for group overrides that apply to a given user.
     *
     * @link ../../overrides.php
     * @see self::get_effective_override()
     * @param ?int $userid the ID of the user the group overrides apply. If null, will retrieve all group overrides in the quiz.
     * @return array A list of group override objects. Returns an empty array if no group overrides found.
     */
    public function get_group_overrides(?int $userid = null): array {
        global $DB;

        // Fetch quiz groups.
        $groups = [];
        if ($userid) {
            // If user specified, only fetch groups the user belongs to.
            $groups = groups_get_user_groups($this->course->id, $userid, true);
            $groups = $groups ? $groups[0] : [];
        } else {
            // If no user specified, fetch all groups in the quiz.
            $groups = $this->get_groups();
            $groups = array_keys($groups);
        }

        // If no groups, return empty array.
        if (empty($groups)) {
            return [];
        }

        // Build query to fetch the group overrides. Add access rule plugin fields as necessary.
        $selects = ['o.*, g.name'];
        [$accessselects, $accessjoins, $accessparams] = $this->accessmanager->get_override_settings_sql();
        if ($accessselects) {
            $selects[] = $accessselects;
        }
        $selects = implode(', ', $selects);
        $params = ['quizid' => $this->quiz->id];
        [$insql, $inparams] = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED);

        $sql = "SELECT {$selects}
                  FROM {quiz_overrides} o
                  JOIN {groups} g ON o.groupid = g.id
                       {$accessjoins}
                 WHERE o.quiz = :quizid
                   AND g.id $insql
              ORDER BY g.name";
        $params = array_merge($accessparams, $params, $inparams);

        return $DB->get_records_sql($sql, $params) ?: [];
    }

    /**
     * Provide a list of user targeted overrides for the current quiz.
     *
     * Used in the overrides.php page to fetch user overrides.
     *
     * @link ../../overrides.php
     * @return array $overrides The list of user targeted overrides in the quiz, returns an empty array if no user overrides found.
     */
    public function get_user_overrides(): array {
        global $DB;

        $userfieldsapi = fields::for_identity($this->context)->with_name()->with_userpic();
        $extrauserfields = $userfieldsapi->get_required_fields([fields::PURPOSE_IDENTITY]);
        $userfieldssql = $userfieldsapi->get_sql('u', true, '', 'userid', false);

        $overrides = [];
        [$accessselects, $accessjoins, $accessparams] = $this->accessmanager->get_override_settings_sql();
        [$sort, $params] = users_order_by_sql('u', null, $this->context, $extrauserfields);
        $params['quizid'] = $this->quiz->id;

        if ($this->can_showallgroups()) {
            $groupsjoin = '';
            $groupswhere = '';
        } else if ($groups = $this->get_groups()) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys($groups), SQL_PARAMS_NAMED);
            $groupsjoin = 'JOIN {groups_members} gm ON u.id = gm.userid';
            $groupswhere = ' AND gm.groupid ' . $insql;
            $params += $inparams;
        } else {
            // User cannot see any data.
            $groupsjoin = '';
            $groupswhere = ' AND 1 = 2';
        }
        $selects = ['o.*', $userfieldssql->selects];
        if ($accessselects) {
            $selects[] = $accessselects;
        }
        $selects = implode(', ', $selects);

        $sql = "SELECT {$selects}
                  FROM {quiz_overrides} o
                  JOIN {user} u ON u.id = o.userid
                       {$userfieldssql->joins}
                       {$accessjoins}
                       {$groupsjoin}
                 WHERE o.quiz = :quizid
                       {$groupswhere}
              ORDER BY $sort";
        $params = [...$params, ...$userfieldssql->params, ...$accessparams];
        $overrides = $DB->get_records_sql($sql, $params);

        return $overrides ?: [];
    }

    /**
     * Add override properties to a 2D array of fields and values to be displayed on an HTML table.
     *
     * Calls {@see mod_quiz\access_manager::add_override_table_fields()} to display access rule plugin override properties.
     *
     * @link ../../overrides.php
     * @see mod_quiz\access_manager::add_override_table_fields()
     * @param stdClass $override The override object used to read and display properties of in the table.
     * @return array [$fields, $values] A 2D array of fields and their respective fields.
     */
    public function add_table_fields(stdClass $override): array {
        // Prepare the information about which settings are overridden.
        $fields = [];
        $values = [];

        // Format timeopen.
        if (isset($override->timeopen)) {
            $fields[] = get_string('quizopens', 'quiz');
            $values[] = $override->timeopen > 0 ?
                    userdate($override->timeopen) : get_string('noopen', 'quiz');
        }
        // Format timeclose.
        if (isset($override->timeclose)) {
            $fields[] = get_string('quizcloses', 'quiz');
            $values[] = $override->timeclose > 0 ?
                    userdate($override->timeclose) : get_string('noclose', 'quiz');
        }
        // Format timelimit.
        if (isset($override->timelimit)) {
            $fields[] = get_string('timelimit', 'quiz');
            $values[] = $override->timelimit > 0 ?
                    format_time($override->timelimit) : get_string('none', 'quiz');
        }
        // Format number of attempts.
        if (isset($override->attempts)) {
            $fields[] = get_string('attempts', 'quiz');
            $values[] = $override->attempts > 0 ?
                    $override->attempts : get_string('unlimited');
        }
        // Format password.
        if (isset($override->password)) {
            $fields[] = get_string('requirepassword', 'quiz');
            $values[] = $override->password !== '' ?
                    get_string('enabled', 'quiz') : get_string('none', 'quiz');
        }

        // Add access rule plugin table fields and values.
        [$fields, $values] = $this->accessmanager->add_override_table_fields($override, $fields, $values);

        // Format reason.
        if (isset($override->reason) && $override->reason !== '') {
            $formattedreason = format_text(
                $override->reason,
                $override->reasonformat ?? FORMAT_MOODLE,
                ['context' => $this->context],
            );

            if ($formattedreason !== '') {
                $fields[] = get_string('overridereason', 'quiz');
                $values[] = $formattedreason;
            }
        }

        return [$fields, $values];
    }

    /**
     * Return the override setting property names.
     *
     * Includes the baseline overridable quiz settings, but adds the "extrapasswords" field and settings names from other access
     * rule plugins. Used in the quiz_update_effective_access() function in to ensure the user is correctly accessing the quiz.
     *
     * @see mod_quiz\access_manager::get_override_setting_names()
     * @link ../../lib.php
     * @return array $settingnames An array of strings of override setting property names that can be set.
     */
    public function get_override_setting_names(): array {
        return array_merge(
            self::OVERRIDEABLE_QUIZ_SETTINGS,
            ['extrapasswords'],
            $this->accessmanager->get_override_setting_names(),
        );
    }

    /**
     * Return the required override setting property names.
     *
     * Used to validate the {@see mod_quiz\form\edit_override_form} when adding or updating an override, making sure that
     * at least one of these fields are filled in. The validation is performed at {@see self::validate_data()}. Includes required
     * setting names provided by overridable access rule plugins.
     *
     * @see self::validate_data()
     * @see self::OVERRIDEABLE_QUIZ_SETTINGS
     * @see mod_quiz\access_manager::get_override_required_setting_names()
     * @return string[]
     */
    public function get_override_required_setting_names(): array {
        return array_merge(
            self::OVERRIDEABLE_QUIZ_SETTINGS,
            $this->accessmanager->get_override_required_setting_names(),
        );
    }

    /**
     * Add fields for use on the override edit form.
     *
     * Focuses on quiz specifc override fields outside of the base form fields in
     * {@see mod_quiz\form\edit_override_form::definition()}. Calls {@see mod_quiz\access_manager::add_override_form_fields()} to
     * add form fields from overridable access rule plugins.
     *
     * @see mod_quiz\form\edit_override_form::definition()
     * @see mod_quiz\access_manager::add_override_form_fields()
     * @param edit_override_form $form
     */
    public function add_edit_override_form_fields(edit_override_form $form): void {
        global $DB;
        $mform = $form->get_form();
        $groupmode = $form->groupmode;
        $groupid = $form->override->groupid ?? null;
        $userid = $form->override->userid ?? null;
        $accessallgroups = $this->can_showallgroups();

        if ($groupmode) {
            // Group override.
            if ($groupid) {
                // There is already a groupid, so freeze the selector.
                $groupchoices = [
                    $groupid => format_string(groups_get_group_name($groupid), true, ['context' => $this->context]),
                ];
                $mform->addElement('select', 'groupid', get_string('overridegroup', 'quiz'), $groupchoices);
                $mform->freeze('groupid');
            } else {
                // Prepare the list of groups.
                // Only include the groups the current can access.
                $groups = $this->get_groups();
                if (empty($groups)) {
                    // Generate an error.
                    $link = new \moodle_url('/mod/quiz/overrides.php', ['cmid' => $this->cm->id]);
                    throw new \moodle_exception('groupsnone', 'quiz', $link);
                }

                $groupchoices = [];
                foreach ($groups as $group) {
                    if ($group->visibility != GROUPS_VISIBILITY_NONE) {
                        $groupchoices[$group->id] = format_string($group->name, true, ['context' => $this->context]);
                    }
                }
                unset($groups);

                if (count($groupchoices) == 0) {
                    $groupchoices[0] = get_string('none');
                }

                $mform->addElement('select', 'groupid', get_string('overridegroup', 'quiz'), $groupchoices);
                $mform->addRule('groupid', get_string('required'), 'required', null, 'client');
            }
        } else {
            // User override.
            $userfieldsapi = fields::for_identity($this->context)->with_userpic()->with_name();
            $extrauserfields = $userfieldsapi->get_required_fields([fields::PURPOSE_IDENTITY]);
            if ($userid) {
                // There is already a userid, so freeze the selector.
                $user = $DB->get_record('user', ['id' => $userid]);
                profile_load_custom_fields($user);
                $userchoices = [];
                $userchoices[$userid] = $form::display_user_name($user, $extrauserfields);
                $mform->addElement('select', 'userid', get_string('overrideuser', 'quiz'), $userchoices);
                $mform->freeze('userid');
            } else {
                // Prepare the list of users.
                $groupids = 0;
                if (!$accessallgroups) {
                    $groups = groups_get_activity_allowed_groups($this->cm);
                    $groupids = array_keys($groups);
                }
                $enrolledjoin = get_enrolled_with_capabilities_join($this->context, '', 'mod/quiz:attempt', $groupids, true);
                $userfieldsql = $userfieldsapi->get_sql('u', true, '', '', false);
                [$sort, $sortparams] = users_order_by_sql('u', null, $this->context, $userfieldsql->mappings);

                $users = $DB->get_records_sql(
                    "SELECT DISTINCT $userfieldsql->selects
                                FROM {user} u
                                     $enrolledjoin->joins
                                     $userfieldsql->joins
                           LEFT JOIN {quiz_overrides} existingoverride
                                  ON existingoverride.userid = u.id
                                 AND existingoverride.quiz = :quizid
                               WHERE existingoverride.id IS NULL
                                 AND $enrolledjoin->wheres
                            ORDER BY $sort",
                    array_merge(
                        ['quizid' => $this->quiz->id],
                        $userfieldsql->params,
                        $enrolledjoin->params,
                        $sortparams,
                    ),
                );

                // Filter users based on any fixed restrictions (groups, profile).
                $cm = is_object($this->cm) ? cm_info::create($this->cm) : $this->cm;
                $cminfo = new \core_availability\info_module($cm);
                $users = $cminfo->filter_user_list($users);

                if (empty($users)) {
                    // Generate an error.
                    $link = new \moodle_url('/mod/quiz/overrides.php', ['cmid' => $this->cm->id]);
                    throw new \moodle_exception('usersnone', 'quiz', $link);
                }

                $userchoices = [];
                foreach ($users as $id => $user) {
                    $userchoices[$id] = $form::display_user_name($user, $extrauserfields);
                }
                unset($users);

                $mform->addElement('searchableselector', 'userid', get_string('overrideuser', 'quiz'), $userchoices);
                $mform->addRule('userid', get_string('required'), 'required', null, 'client');
            }
        }

        // Password.
        // This field has to be above the date and timelimit fields,
        // otherwise browsers will clear it when those fields are changed.
        $mform->addElement('passwordunmask', 'password', get_string('requirepassword', 'quiz'));
        $mform->setType('password', PARAM_TEXT);
        $mform->addHelpButton('password', 'requirepassword', 'quiz');
        $mform->setDefault('password', $this->quiz->password);

        // Open and close dates.
        $mform->addElement('date_time_selector', 'timeopen', get_string('quizopen', 'quiz'), mod_quiz_mod_form::$datefieldoptions);
        $mform->setDefault('timeopen', $this->quiz->timeopen);

        $mform->addElement(
            'date_time_selector',
            'timeclose',
            get_string('quizclose', 'quiz'),
            mod_quiz_mod_form::$datefieldoptions,
        );
        $mform->setDefault('timeclose', $this->quiz->timeclose);

        // Time limit.
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'quiz'), ['optional' => true]);
        $mform->addHelpButton('timelimit', 'timelimit', 'quiz');
        $mform->setDefault('timelimit', $this->quiz->timelimit);

        // Number of attempts.
        $attemptoptions = ['0' => get_string('unlimited')];
        for ($i = 1; $i <= QUIZ_MAX_ATTEMPT_OPTION; $i++) {
            $attemptoptions[$i] = $i;
        }
        $mform->addElement('select', 'attempts', get_string('attemptsallowed', 'quiz'), $attemptoptions);
        $mform->addHelpButton('attempts', 'attempts', 'quiz');
        $mform->setDefault('attempts', $this->quiz->attempts);

        // Access rule plugin override fields.
        if ($this->accessmanager->add_override_form_fields($form)) {
            $mform->closeHeaderBefore('reason_editor');
        }

        // Reason for override.
        $editoroptions = [
            'maxfiles' => 0,
            'noclean' => false,
            'context' => $this->context,
        ];
        $mform->addElement('editor', 'reason_editor', get_string('overridereason', 'quiz'), null, $editoroptions);
        $mform->setType('reason_editor', PARAM_RAW);
        $mform->addHelpButton('reason_editor', 'overridereason', 'quiz');
    }
}
