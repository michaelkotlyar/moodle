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

namespace mod_quiz\form;

use cm_info;
use context;
use context_module;
use mod_quiz_mod_form;
use moodle_url;
use moodleform;
use stdClass;
use quizaccess_seb\{seb_quiz_settings, settings_provider};

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/quiz/mod_form.php');

/**
 * Form for editing quiz settings overrides.
 *
 * @package    mod_quiz
 * @copyright  2010 Matt Petro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_override_form extends moodleform {

    /** @var cm_info course module object. */
    protected $cm;

    /** @var stdClass the quiz settings object. */
    protected $quiz;

    /** @var context_module the quiz context. */
    protected $context;

    /** @var bool editing group override (true) or user override (false). */
    protected $groupmode;

    /** @var int groupid, if provided. */
    protected $groupid;

    /** @var int userid, if provided. */
    protected $userid;

    /** @var int overrideid, if provided. */
    protected int $overrideid;

    /** @var array array of seb settings to override. */
    protected array $sebdata;

    /**
     * Constructor.
     *
     * @param moodle_url $submiturl the form action URL.
     * @param cm_info $cm course module object.
     * @param stdClass $quiz the quiz settings object.
     * @param context_module $context the quiz context.
     * @param bool $groupmode editing group override (true) or user override (false).
     * @param stdClass|null $override the override being edited, if it already exists.
     */
    public function __construct(moodle_url $submiturl,
            cm_info $cm, stdClass $quiz, context_module $context,
            bool $groupmode, ?stdClass $override) {

        $this->cm = $cm;
        $this->quiz = $quiz;
        $this->context = $context;
        $this->groupmode = $groupmode;
        $this->groupid = empty($override->groupid) ? 0 : $override->groupid;
        $this->userid = empty($override->userid) ? 0 : $override->userid;
        $this->overrideid = $override->id ?? 0;
        $this->sebdata = empty($override->sebdata) ? [] : unserialize($override->sebdata);

        parent::__construct($submiturl);
    }

    protected function definition() {
        global $DB;

        $cm = $this->cm;
        $mform = $this->_form;

        $mform->addElement('header', 'override', get_string('override', 'quiz'));

        $quizgroupmode = groups_get_activity_groupmode($cm);
        $accessallgroups = ($quizgroupmode == NOGROUPS) || has_capability('moodle/site:accessallgroups', $this->context);

        if ($this->groupmode) {
            // Group override.
            if ($this->groupid) {
                // There is already a groupid, so freeze the selector.
                $groupchoices = [
                    $this->groupid => format_string(groups_get_group_name($this->groupid), true, ['context' => $this->context]),
                ];
                $mform->addElement('select', 'groupid',
                        get_string('overridegroup', 'quiz'), $groupchoices);
                $mform->freeze('groupid');
            } else {
                // Prepare the list of groups.
                // Only include the groups the current can access.
                $groups = $accessallgroups ? groups_get_all_groups($cm->course) : groups_get_activity_allowed_groups($cm);
                if (empty($groups)) {
                    // Generate an error.
                    $link = new moodle_url('/mod/quiz/overrides.php', ['cmid' => $cm->id]);
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

                $mform->addElement('select', 'groupid',
                        get_string('overridegroup', 'quiz'), $groupchoices);
                $mform->addRule('groupid', get_string('required'), 'required', null, 'client');
            }
        } else {
            // User override.
            $userfieldsapi = \core_user\fields::for_identity($this->context)->with_userpic()->with_name();
            $extrauserfields = $userfieldsapi->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]);
            if ($this->userid) {
                // There is already a userid, so freeze the selector.
                $user = $DB->get_record('user', ['id' => $this->userid]);
                profile_load_custom_fields($user);
                $userchoices = [];
                $userchoices[$this->userid] = self::display_user_name($user, $extrauserfields);
                $mform->addElement('select', 'userid',
                        get_string('overrideuser', 'quiz'), $userchoices);
                $mform->freeze('userid');
            } else {
                // Prepare the list of users.
                $groupids = 0;
                if (!$accessallgroups) {
                    $groups = groups_get_activity_allowed_groups($cm);
                    $groupids = array_keys($groups);
                }
                $enrolledjoin = get_enrolled_with_capabilities_join(
                        $this->context, '', 'mod/quiz:attempt', $groupids, true);
                $userfieldsql = $userfieldsapi->get_sql('u', true, '', '', false);
                list($sort, $sortparams) = users_order_by_sql('u', null,
                        $this->context, $userfieldsql->mappings);

                $users = $DB->get_records_sql("
                        SELECT DISTINCT $userfieldsql->selects
                          FROM {user} u
                          $enrolledjoin->joins
                          $userfieldsql->joins
                          LEFT JOIN {quiz_overrides} existingoverride ON
                                      existingoverride.userid = u.id AND existingoverride.quiz = :quizid
                         WHERE existingoverride.id IS NULL
                           AND $enrolledjoin->wheres
                      ORDER BY $sort
                        ", array_merge(['quizid' => $this->quiz->id], $userfieldsql->params, $enrolledjoin->params, $sortparams));

                // Filter users based on any fixed restrictions (groups, profile).
                $info = new \core_availability\info_module($cm);
                $users = $info->filter_user_list($users);

                if (empty($users)) {
                    // Generate an error.
                    $link = new moodle_url('/mod/quiz/overrides.php', ['cmid' => $cm->id]);
                    throw new \moodle_exception('usersnone', 'quiz', $link);
                }

                $userchoices = [];
                foreach ($users as $id => $user) {
                    $userchoices[$id] = self::display_user_name($user, $extrauserfields);
                }
                unset($users);

                $mform->addElement('searchableselector', 'userid',
                        get_string('overrideuser', 'quiz'), $userchoices);
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
        $mform->addElement('date_time_selector', 'timeopen',
                get_string('quizopen', 'quiz'), mod_quiz_mod_form::$datefieldoptions);
        $mform->setDefault('timeopen', $this->quiz->timeopen);

        $mform->addElement('date_time_selector', 'timeclose',
                get_string('quizclose', 'quiz'), mod_quiz_mod_form::$datefieldoptions);
        $mform->setDefault('timeclose', $this->quiz->timeclose);

        // Time limit.
        $mform->addElement('duration', 'timelimit',
                get_string('timelimit', 'quiz'), ['optional' => true]);
        $mform->addHelpButton('timelimit', 'timelimit', 'quiz');
        $mform->setDefault('timelimit', $this->quiz->timelimit);

        // Number of attempts.
        $attemptoptions = ['0' => get_string('unlimited')];
        for ($i = 1; $i <= QUIZ_MAX_ATTEMPT_OPTION; $i++) {
            $attemptoptions[$i] = $i;
        }
        $mform->addElement('select', 'attempts',
                get_string('attemptsallowed', 'quiz'), $attemptoptions);
        $mform->addHelpButton('attempts', 'attempts', 'quiz');
        $mform->setDefault('attempts', $this->quiz->attempts);

        // SEB override settings.
        $this->display_seb_settings($mform);

        // Submit buttons.
        $mform->addElement('submit', 'resetbutton',
                get_string('reverttodefaults', 'quiz'));

        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'submitbutton',
                get_string('save', 'quiz'));
        $buttonarray[] = $mform->createElement('submit', 'againbutton',
                get_string('saveoverrideandstay', 'quiz'));
        $buttonarray[] = $mform->createElement('cancel');

        $mform->addGroup($buttonarray, 'buttonbar', '', [' '], false);
        $mform->closeHeaderBefore('buttonbar');
    }

    /**
     * Add SEB settings to the form.
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    protected function display_seb_settings($mform) {
        $mform->addElement('header', 'seb', get_string('seb', 'quizaccess_seb'));

        $mform->addElement('checkbox', 'enableseboverride', get_string('enabled', 'quizaccess_seb'));
        $mform->setDefault('enableseboverride', $this->sebdata['enableseboverride'] ?? false);

        // ... "Require the use of Safe Exam Browser"
        if (settings_provider::can_override_unrequire($this->context)) {
            $requireseboptions[settings_provider::USE_SEB_NO] = get_string('no');
        }

        if (settings_provider::can_configure_manually($this->context) || settings_provider::is_conflicting_permissions($this->context)) {
            $requireseboptions[settings_provider::USE_SEB_CONFIG_MANUALLY] = get_string('seb_use_manually', 'quizaccess_seb');
        }

        if (settings_provider::can_use_seb_template($this->context) || settings_provider::is_conflicting_permissions($this->context)) {
            if (!empty(settings_provider::get_template_options())) {
                $requireseboptions[settings_provider::USE_SEB_TEMPLATE] = get_string('seb_use_template', 'quizaccess_seb');
            }
        }

        $requireseboptions[settings_provider::USE_SEB_CLIENT_CONFIG] = get_string('seb_use_client', 'quizaccess_seb');

        $mform->addElement(
            'select',
            'seb_requiresafeexambrowser',
            get_string('seb_requiresafeexambrowser', 'quizaccess_seb'),
            $requireseboptions
        );

        $mform->setType('seb_requiresafeexambrowser', PARAM_INT);
        $mform->setDefault(
            'seb_requiresafeexambrowser',
            $this->sebdata['seb_requiresafeexambrowser'] ?? $this->quiz->seb_requiresafeexambrowser ?? 0
        );
        $mform->addHelpButton('seb_requiresafeexambrowser', 'seb_requiresafeexambrowser', 'quizaccess_seb');
        $mform->disabledIf('seb_requiresafeexambrowser', 'enableseboverride');

        if (settings_provider::is_conflicting_permissions($this->context)) {
            $mform->freeze('seb_requiresafeexambrowser');
        }

        // ... "Safe Exam Browser config template"
        if (settings_provider::can_use_seb_template($this->context) ||
            settings_provider::is_conflicting_permissions($this->context)) {
            $element = $mform->addElement(
                'select',
                'seb_templateid',
                get_string('seb_templateid', 'quizaccess_seb'),
                settings_provider::get_template_options()
            );
        } else {
            $element = $mform->addElement('hidden', 'seb_templateid');
        }

        $mform->setType('seb_templateid', PARAM_INT);
        $mform->setDefault('seb_templateid', $this->sebdata['seb_templateid'] ?? $this->quiz->seb_templateid ?? 0);
        $mform->addHelpButton('seb_templateid', 'seb_templateid', 'quizaccess_seb');
        $mform->disabledIf('seb_templateid', 'enableseboverride');

        if (settings_provider::is_conflicting_permissions($this->context)) {
            $mform->freeze('seb_templateid');
        }

        // ... "Show Safe Exam browser download button"
        if (settings_provider::can_change_seb_showsebdownloadlink($this->context)) {
            $mform->addElement('selectyesno',
                'seb_showsebdownloadlink',
                get_string('seb_showsebdownloadlink', 'quizaccess_seb')
            );

            $mform->setType('seb_showsebdownloadlink', PARAM_BOOL);
            $mform->setDefault(
                'seb_showsebdownloadlink',
                $this->sebdata['seb_showsebdownloadlink'] ?? $this->quiz->seb_showsebdownloadlink ?? 1
            );
            $mform->addHelpButton('seb_showsebdownloadlink', 'seb_showsebdownloadlink', 'quizaccess_seb');
            $mform->disabledIf('seb_showsebdownloadlink', 'enableseboverride');
        }

        // Manual config elements.
        $defaults = settings_provider::get_seb_config_element_defaults();
        $types = settings_provider::get_seb_config_element_types();

        foreach (settings_provider::get_seb_config_elements() as $name => $type) {
            if (!settings_provider::can_manage_seb_config_setting($name, $this->context)) {
                $type = 'hidden';
            }

            $mform->addElement($type, $name, get_string($name, 'quizaccess_seb'));

            $mform->addHelpButton($name, $name, 'quizaccess_seb');
            $mform->setType('seb_showsebdownloadlink', PARAM_BOOL);
            $mform->setDefault(
                'seb_showsebdownloadlink',
                $this->sebdata['seb_showsebdownloadlink'] ?? $this->quiz->seb_showsebdownloadlink ?? 1
            );
            $mform->disabledIf($name, 'enableseboverride');

            if (isset($defaults[$name])) {
                $mform->setDefault($name, $this->sebdata[$name] ?? $this->quiz->{$name} ?? $defaults[$name]);
            }

            if (isset($types[$name])) {
                $mform->setType($name, $types[$name]);
            }
        }

        if (settings_provider::can_change_seb_allowedbrowserexamkeys($this->context)) {
            $mform->addElement('textarea',
                'seb_allowedbrowserexamkeys',
                get_string('seb_allowedbrowserexamkeys', 'quizaccess_seb')
            );

            $mform->setType('seb_allowedbrowserexamkeys', PARAM_RAW);
            $mform->setDefault(
                'seb_allowedbrowserexamkeys',
                $this->sebdata['seb_allowedbrowserexamkeys'] ?? $this->quiz->seb_allowedbrowserexamkeys ?? ''
            );
            $mform->addHelpButton('seb_allowedbrowserexamkeys', 'seb_allowedbrowserexamkeys', 'quizaccess_seb');
            $mform->disabledIf('seb_allowedbrowserexamkeys', 'enableseboverride');
        }

        // Hideifs.
        foreach (settings_provider::get_quiz_hideifs() as $elname => $rules) {
            if ($mform->elementExists($elname)) {
                foreach ($rules as $hideif) {
                    $mform->hideIf(
                        $hideif->get_element(),
                        $hideif->get_dependantname(),
                        $hideif->get_condition(),
                        $hideif->get_dependantvalue()
                    );
                }
            }
        }

        // Lock elements.
        if (settings_provider::is_conflicting_permissions($this->context)) {
            // Freeze common quiz settings.
            $mform->addElement('enableseboverride');
            $mform->freeze('seb_requiresafeexambrowser');
            $mform->freeze('seb_templateid');
            $mform->freeze('seb_showsebdownloadlink');
            $mform->freeze('seb_allowedbrowserexamkeys');

            $quizsettings = seb_quiz_settings::get_by_quiz_id((int) $this->quiz->id);

            // Remove template ID if not using template for this quiz.
            if (empty($quizsettings) || $quizsettings->get('requiresafeexambrowser') != settings_provider::USE_SEB_TEMPLATE) {
                $mform->removeElement('seb_templateid');
            }

            // Freeze all SEB specific settings.
            foreach (settings_provider::get_seb_config_elements() as $element => $type) {
                if ($mform->elementExists($element)) {
                    $mform->freeze($element);
                }
            }
        }

        // Close header before next field.
        $mform->closeHeaderBefore('resetbutton');
    }

    /**
     * Get a user's name and identity ready to display.
     *
     * @param stdClass $user a user object.
     * @param array $extrauserfields (identity fields in user table only from the user_fields API)
     * @return string User's name, with extra info, for display.
     */
    public static function display_user_name(stdClass $user, array $extrauserfields): string {
        $username = fullname($user);
        $namefields = [];
        foreach ($extrauserfields as $field) {
            if (isset($user->$field) && $user->$field !== '') {
                $namefields[] = s($user->$field);
            } else if (strpos($field, 'profile_field_') === 0) {
                $field = substr($field, 14);
                if (isset($user->profile[$field]) && $user->profile[$field] !== '') {
                    $namefields[] = s($user->profile[$field]);
                }
            }
        }
        if ($namefields) {
            $username .= ' (' . implode(', ', $namefields) . ')';
        }
        return $username;
    }

    /**
     * Validate the data from the form.
     *
     * @param  array $data form data
     * @param  array $files form files
     * @return array An array of error messages, where the key is is the mform element name and the value is the error.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $data['id'] = $this->overrideid;
        $data['quiz'] = $this->quiz->id;

        $manager = new \mod_quiz\local\override_manager($this->quiz, $this->context);
        $errors = array_merge($errors, $manager->validate_data($data));

        // Any 'general' errors we merge with the group/user selector element.
        if (!empty($errors['general'])) {
            if ($this->groupmode) {
                $errors['groupid'] = $errors['groupid'] ?? "" . $errors['general'];
            } else {
                $errors['userid'] = $errors['userid'] ?? "" . $errors['general'];
            }
        }

        return $errors;
    }
}
