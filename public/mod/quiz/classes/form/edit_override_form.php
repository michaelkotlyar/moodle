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

use context_module;
use mod_quiz\local\override_manager;
use mod_quiz\quiz_settings;
use moodle_url;
use moodleform;
use MoodleQuickForm;
use stdClass;

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
    /** @var stdClass the quiz settings object. */
    protected stdClass $quiz;

    /** @var context_module the quiz context. */
    protected context_module $context;

    /** @var override_manager override manager object to provide form fields and validation. */
    protected override_manager $overridemanager;

    /** @var bool editing group override (true) or user override (false). */
    public readonly bool $groupmode;

    /** @var null|stdClass override, if provided. */
    public readonly ?stdClass $override;

    /**
     * Constructor.
     *
     * @param moodle_url $submiturl the form action URL.
     * @param quiz_settings $quizobj the quiz settings object.
     * @param bool $groupmode editing group override (true) or user override (false).
     * @param null|stdClass $override the override being edited, if it already exists.
     */
    public function __construct(
        moodle_url $submiturl,
        quiz_settings $quizobj,
        bool $groupmode,
        ?stdClass $override = null,
    ) {
        $this->quiz = $quizobj->get_quiz();
        $this->context = $quizobj->get_context();
        $this->overridemanager = $quizobj->get_override_manager();
        $this->groupmode = $groupmode;
        $this->override = $override;

        parent::__construct($submiturl);
    }

    #[\Override]
    protected function definition() {
        $mform = $this->_form;

        // Form header.
        $mform->addElement('header', 'override', get_string('override', 'quiz'));

        // Handle quiz specific form fields in the override manager.
        $this->overridemanager->add_edit_override_form_fields($this);

        // Submit buttons.
        $mform->addElement('submit', 'resetbutton', get_string('reverttodefaults', 'quiz'));

        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('save', 'quiz'));
        $buttonarray[] = $mform->createElement('submit', 'againbutton', get_string('saveoverrideandstay', 'quiz'));
        $buttonarray[] = $mform->createElement('cancel');

        $mform->addGroup($buttonarray, 'buttonbar', '', [' '], false);
        $mform->closeHeaderBefore('buttonbar');
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
        $data['id'] = $this->override ? $this->override->id : 0;
        $data['quiz'] = $this->quiz->id;

        $errors = array_merge($errors, $this->overridemanager->validate_data($data));

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

    /**
     * Get the form object.
     *
     * For use in the overrides controller when adding form fields in the quiz access rule override manager.
     *
     * @return MoodleQuickForm
     */
    public function get_form(): MoodleQuickForm {
        return $this->_form;
    }
}
