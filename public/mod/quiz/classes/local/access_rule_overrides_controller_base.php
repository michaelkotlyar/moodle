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

use core\context\module as context_module;
use mod_quiz\form\edit_override_form;
use mod_quiz\quiz_settings;
use stdClass;

/**
 * Class access_rule_overrides_controller_base
 *
 * This class is used for quiz access plugins to implement overridability. Quiz access plugins need to create an
 * overrides_controller class extending this class and implement its functions.
 *
 * @package    mod_quiz
 * @copyright  2025 Michael Kotlyar <michael.kotlyar@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class access_rule_overrides_controller_base {
    /** @var context_module $context The quiz context. */
    protected context_module $context;

    /** @var stdClass $quiz The quiz object. */
    protected stdClass $quiz;

    /**
     * Instantiates the quiz access plugin override controller.
     *
     * @param quiz_settings $quizobj The quiz settings object to get the quiz and context from.
     */
    public function __construct(quiz_settings $quizobj) {
        $this->context = $quizobj->get_context();
        $this->quiz = $quizobj->get_quiz();
    }

    /**
     * Add fields to the quiz override form.
     *
     * To remain consistent with other access rules, you must create a header element at the
     * top and the header should only be expanded if the override for this rule has been configured.
     *
     * E.g. $mform->addElement('header', 'accessrule', $title);
     * $mform->setExpanded('accessrule', $override && $override->plugin_override_enabled);
     *
     * Make sure the form element names will be unique compared to other fields in the override form
     * outside of the implemented fieldset, as to avoid conlict issues.
     *
     * @param edit_override_form $form
     * @return bool return true if fields have been added to the form.
     */
    public function add_form_fields(edit_override_form $form): bool {
        return false;
    }

    /**
     * Validate the data of the submitted form using.
     *
     * Called by the {@see mod_quiz\access_manager::validate_override_form_fields()} function when validating the override form.
     *
     * @see self::add_form_fields() Where the form should be validating fields from
     * @see mod_quiz\access_manager::validate_override_form_fields()
     * @param array $errors the errors found so far.
     * @param array $data the submitted form data.
     * @return array the updated $errors array.
     */
    public function validate_form_fields(array $errors, array $data): array {
        return $errors;
    }

    /**
     * Save any submitted settings when the quiz override settings form is submitted.
     *
     * Save settings relating to the quiz access plugin override, e.g. adding record entries in the database.
     *
     * @see mod_quiz\access_manager::save_override_settings()
     * @param stdClass $override data from the override form.
     */
    public function save_settings(stdClass $override): void {
    }

    /**
     * Delete override settings when the quiz override is deleted.
     *
     * Delete settings relating to the quiz access plugin override including any record entries in the database.
     *
     * @see mod_quiz\access_manager::delete_override_settings()
     * @param stdClass[] $overrides an array of override objects to be deleted.
     */
    public function delete_settings(array $overrides): void {
    }

    /**
     * Returns overridable field keys as a string array.
     *
     * The fields will be placed in the override form/object along with other settings that can be overridden so make sure that the
     * values will be unique.
     *
     * E.g. ['pluginrule_enabled', 'pluginrule_timerlength', 'pluginrule_buzzersoundeffect']
     *
     * @see mod_quiz\access_manager::get_override_setting_names()
     * @return string[] An array of setting/property names e.g. ['pluginrule_enabled', 'pluginrule_timerlength', ...]
     */
    public function get_setting_names(): array {
        return [];
    }

    /**
     * Returns the required overridable field keys as a string array.
     *
     * The fields will be placed in the override form/object along with other settings that can be overridden so make sure that the
     * values will be unique, e.g. ['specificpluginrule_enabled', 'specificpluginrule_password']
     *
     * @see mod_quiz\access_manager::get_override_required_setting_names()
     * @return string[] An array of setting/property names e.g. ['specificpluginrule_enabled', 'specificpluginrule_password']
     */
    public function get_required_setting_names(): array {
        return [];
    }

    /**
     * Provide an array of SQL components to fetch relevant override setting data for the access rule.
     *
     * The function must return a comma seperated string of fields to select, SQL join clauses, and an array of
     * paramater values used in said join clauses (typically empty, but available for outlier cases).
     *
     * E.g. ["pqo.conf1, pqo.conf2", "LEFT JOIN {plugin_quizaccess_overrides} pqo ON pqo.overrideid = {$overridetablename}.id", []]
     *
     * @see mod_quiz\access_manager::get_override_settings_sql()
     * @param string $overridetablename name of the table to reference for joins, typically passed in as 'o' by default.
     * @return array [$selects, $joins, $params]
     */
    public function get_settings_sql(string $overridetablename): array {
        return [];
    }

    /**
     * Add fields and their respective values to be displayed in the overrides HTML table.
     *
     * Adds access rule plugin specifc fields and values based on the properties of the given override. This is used in the
     * overrides.php page to render quiz access plugin override properties in the override table.
     *
     * @link ../../overrides.php
     * @see mod_quiz\access_manager::add_override_table_fields()
     * @param stdClass $override The override data to use to update the $fields and $values
     * @param string[] $fields The access rule fields to display, e.g, [..., 'Enabled']
     * @param string[] $values The value of the field at the same index, e.g, [..., 'Yes']
     * @return array [$fields, $values] An array of the updated fields and values.
     */
    public function add_table_fields(stdClass $override, array $fields, array $values): array {
        return [$fields, $values];
    }

    /**
     * Clean override form data.
     *
     * If the values of the access rule override settings are 'empty' or have no effect in the override, we should clear them so
     * that the form can recognise that the access rule override is not filled in so that the form can invalidate the submission
     * if the rest of the submission is also left unfilled. In the typical case, we would check if the access rule override is
     * enabled, if it is not, but set to '0', we set the value to null.
     *
     * @see mod_quiz\access_manager::clean_override_form_data() Where this function is called to clean the override form data.
     * @param array $formdata
     * @return array Cleaned form data
     */
    public function clean_form_data(array $formdata): array {
        return $formdata;
    }

    /**
     * Combine group overrides into a single override object.
     *
     * This function is used to merge multiple group overrides into a single override object, taking into account the precedence
     * of user-specific overrides over group overrides.
     *
     * @see mod_quiz\access_manager::combine_group_overrides()
     * @param stdClass $override The base override object passed in from previous processing.
     * @param array $groupoverrides An array of group override objects to be combined with the base override.
     * @return stdClass The combined override object.
     */
    public function combine_group_overrides(stdClass $override, array $groupoverrides): stdClass {
        return $override;
    }
}
