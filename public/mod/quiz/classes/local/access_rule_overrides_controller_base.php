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

use context;
use context_module;
use mod_quiz\form\edit_override_form;
use MoodleQuickForm;

/**
 * Class access_rule_overrides_controller_base
 * 
 * TODO: PHPDOC
 *
 * @package    mod_quiz
 * @copyright  2025 Michael Kotlyar <michael.kotlyar@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class access_rule_overrides_controller_base {
    /**
     * Add fields to the quiz override form.
     *
     * TODO: PHPDOC
     *
     * To remain consistent with other access rules, you must
     * create a header element at the top and the header should only be expanded if the override for
     * this rule has been configured.
     *
     * E.g. $mform->addElement('header', 'accessrule', $title);
     * $mform->setExpanded('accessrule', $override && $override->enabled);
     *
     * @param edit_override_form $form
     */
    public static function add_form_fields(edit_override_form $form): void {
    }

    /**
     * Validate the data from any form fields added using {@see add_form_fields()}.
     *
     * TODO: PHPDOC
     *
     * @param array $errors the errors found so far.
     * @param array $data the submitted form data.
     * @param array $files information about any uploaded files.
     * @param context_module $context
     * @return array the updated $errors array.
     */
    public static function validate_form_fields(
        array $errors,
        array $data,
        array $files,
        edit_override_form $mform,
    ): array {
        return [];
    }

    /**
     * Save any submitted settings when the quiz override settings form is submitted.
     *
     * TODO: PHPDOC
     *
     * @param array $override data from the override form.
     */
    public static function save_settings(array $override): void {
    }

    /**
     * Delete any rule-specific override settings when the quiz override is deleted.
     *
     * TODO: PHPDOC
     *
     * @param int $quizid all overrides being deleted should belong to the same quiz.
     * @param array $overrides an array of override objects to be deleted.
     */
    public static function delete_settings(int $quizid, array $overrides): void {
    }

    /**
     * Returns all form field keys in the override form as a string array.
     *
     * The fields will be placed in the override form along with other settings that can be overridden so make sure that the values
     * will be unique, e.g. ['specificpluginrule_enabled', 'specificpluginrule_password']
     *
     * @return array An array of field key strings
     */
    public static function get_settings(): array {
        return [];
    }

    /**
     * Returns the required form field keys in the override form as a string array.
     *
     * The fields will be placed in the override form along with other settings that can be overridden so make sure that the values
     * will be unique, e.g. ['specificpluginrule_enabled', 'specificpluginrule_password']
     *
     * @return array An array of field key strings
     */
    public static function get_required_settings(): array {
        return [];
    }

    /**
     * Get components of the SQL query to fetch the access rule components' override
     * settings. To be used as part of a quiz_override query to reference.
     *
     * TODO: PHPDOC
     *
     * @param string $overridetablename Name of the table to reference for joins.
     * @return array [$selects, $joins, $params']
     */
    public static function get_settings_sql(string $overridetablename): array {
        return [];
    }

    /**
     * Add fields and their respective values to be displayed in the overrides HTML table.
     *
     * TODO: PHPDOC
     *
     * @param stdClass $override the override data to use to update the $fields and $values
     * @param array $fields the access rule fields to display, e.g, [..., 'Enabled']
     * @param array $values the value of the field at the same index, e.g, [..., 'Yes']
     * @param context $context the context of which the override is being applied to.
     * @return array an array of the updated fields and values, e.g. [$fields, $values]
     */
    public static function add_table_fields(\stdClass $override, array $fields, array $values, context_module $context): array {
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
     * @param array $formdata
     * @return array Cleaned form data
     */
    public static function clean_form_data(array $formdata): array {
        return $formdata;
    }
}
