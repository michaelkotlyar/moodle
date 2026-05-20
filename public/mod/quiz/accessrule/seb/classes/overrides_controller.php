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

namespace quizaccess_seb;

use mod_quiz\local\access_rule_overrides_controller_base;
use mod_quiz\form\edit_override_form;
use stdClass;

/**
 * Override controller for quizaccess_seb.
 *
 * @package    quizaccess_seb
 * @copyright  2025 Michael Kotlyar <michael.kotlyar@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overrides_controller extends access_rule_overrides_controller_base {
    #[\Override]
    public function add_form_fields(edit_override_form $form): bool {
        $mform = $form->get_form();
        $override = $form->override;

        // Add header element.
        $mform->addElement('header', 'seb', get_string('seb', 'quizaccess_seb'));
        $mform->setExpanded('seb', $override && $override->seb_enabled);

        // If conflicting permissions, display existing require setting statically.
        if (settings_provider::is_conflicting_permissions($this->context, true)) {
            $quizsettings = seb_quiz_settings::get_by_quiz_id((int) $this->quiz->id);
            $requireseb = $quizsettings ? $quizsettings->get('requiresafeexambrowser') : 0;

            $requiresebstring = match ($requireseb) {
                settings_provider::USE_SEB_NO              => get_string('no'),
                settings_provider::USE_SEB_CONFIG_MANUALLY => get_string('seb_use_manually', 'quizaccess_seb'),
                settings_provider::USE_SEB_TEMPLATE        => get_string('seb_use_template', 'quizaccess_seb'),
                settings_provider::USE_SEB_UPLOAD_CONFIG   => get_string('seb_use_upload', 'quizaccess_seb'),
                settings_provider::USE_SEB_CLIENT_CONFIG   => get_string('seb_use_client', 'quizaccess_seb'),
                default                                    => '',
            };

            $mform->addElement(
                'static',
                'require_seb_static',
                get_string('seb_requiresafeexambrowser', 'quizaccess_seb'),
                $requiresebstring,
            );

            return true;
        }

        // Enable Safe Exam Browser override.
        $mform->addElement('selectyesno', 'seb_enabled', get_string('enableoverride', 'quizaccess_seb'));
        $mform->setDefault('seb_enabled', $this->get_default_field('requiresafeexambrowser', 0, $override));

        $requireseboptions = settings_provider::get_requiresafeexambrowser_options($this->context, true);
        $mform->addElement(
            'select',
            'seb_requiresafeexambrowser',
            get_string('seb_requiresafeexambrowser', 'quizaccess_seb'),
            $requireseboptions,
        );

        $mform->setType('seb_requiresafeexambrowser', PARAM_INT);
        $mform->setDefault(
            'seb_requiresafeexambrowser',
            $this->get_default_field('requiresafeexambrowser', 0, $override),
        );
        $mform->addHelpButton('seb_requiresafeexambrowser', 'seb_requiresafeexambrowser', 'quizaccess_seb');
        $mform->hideIf('seb_requiresafeexambrowser', 'seb_enabled', 0);

        // Safe Exam Browser config template.
        $templateoptions = settings_provider::get_template_options($this->quiz->cmid);
        if (settings_provider::can_use_seb_template($this->context)) {
            $mform->addElement(
                'select',
                'seb_templateid',
                get_string('seb_templateid', 'quizaccess_seb'),
                $templateoptions,
            );
        } else {
            $mform->addElement('hidden', 'seb_templateid');
        }

        $mform->setType('seb_templateid', PARAM_INT);
        $mform->setDefault('seb_templateid', $this->get_default_field('templateid', 0, $override));
        $mform->addHelpButton('seb_templateid', 'seb_templateid', 'quizaccess_seb');
        $mform->hideIf('seb_templateid', 'seb_enabled', 0);

        // Show Safe Exam browser download button.
        if (settings_provider::can_change_seb_showsebdownloadlink($this->context)) {
            $mform->addElement(
                'selectyesno',
                'seb_showsebdownloadlink',
                get_string('seb_showsebdownloadlink', 'quizaccess_seb'),
            );

            $mform->setType('seb_showsebdownloadlink', PARAM_BOOL);
            $mform->setDefault(
                'seb_showsebdownloadlink',
                $this->get_default_field('showsebdownloadlink', 1, $override),
            );
            $mform->addHelpButton('seb_showsebdownloadlink', 'seb_showsebdownloadlink', 'quizaccess_seb');
            $mform->hideIf('seb_showsebdownloadlink', 'seb_enabled', 0);
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
            $mform->setType($name, PARAM_BOOL);
            $mform->setDefault(
                $name,
                $this->get_default_field($name, 1, $override),
            );
            $mform->hideIf($name, 'seb_enabled', 0);

            if (isset($defaults[$name])) {
                $mform->setDefault(
                    $name,
                    $this->get_default_field($name, $defaults[$name], $override, true),
                );
            }

            if (isset($types[$name])) {
                $mform->setType($name, $types[$name]);
            }
        }

        // Allowed browser exam keys.
        if (settings_provider::can_change_seb_allowedbrowserexamkeys($this->context)) {
            $mform->addElement(
                'textarea',
                'seb_allowedbrowserexamkeys',
                get_string('seb_allowedbrowserexamkeys', 'quizaccess_seb'),
            );

            $mform->setType('seb_allowedbrowserexamkeys', PARAM_RAW);
            $mform->setDefault(
                'seb_allowedbrowserexamkeys',
                $this->get_default_field('allowedbrowserexamkeys', '', $override),
            );
            $mform->addHelpButton('seb_allowedbrowserexamkeys', 'seb_allowedbrowserexamkeys', 'quizaccess_seb');
            $mform->hideIf('seb_allowedbrowserexamkeys', 'seb_enabled', 0);
        }

        // Hideifs.
        foreach (settings_provider::get_quiz_hideifs() as $elname => $rules) {
            if ($mform->elementExists($elname)) {
                foreach ($rules as $hideif) {
                    $mform->hideIf(
                        $hideif->get_element(),
                        $hideif->get_dependantname(),
                        $hideif->get_condition(),
                        $hideif->get_dependantvalue(),
                    );
                }
            }
        }

        return true;
    }

    /**
     * Fetches the best suited default value for a field.
     *
     * If there is an override value set, use this. If there's no override value, check if the quiz had SEB settings and use this
     * value instead. Otherwise, use the default value defined.
     *
     * @param string $field The field key to search $default and $override.
     * @param string $default The default form value.
     * @param ?stdClass $override The override data object.
     * @param bool $removeprefix Remove 'seb_' from the field key.
     * @return string
     */
    protected function get_default_field(
        string $field,
        string $default,
        ?stdClass $override,
        bool $removeprefix = false,
    ): string {
        if ($removeprefix) {
            $field = substr($field, 4);
        }
        return match (true) {
            isset($override->field) => $override->field,
            isset($quiz->$field)    => $this->quiz->$field,
            default                 => $default,
        };
    }

    #[\Override]
    public function validate_form_fields(array $errors, array $data): array {
        if (!settings_provider::can_configure_seb($this->context)) {
            return $errors;
        }

        if (settings_provider::is_seb_settings_locked($this->quiz->id)) {
            return $errors;
        }

        if (settings_provider::is_conflicting_permissions($this->context, true)) {
            return $errors;
        }

        $settings = settings_provider::filter_plugin_settings((object) $data);

        // Validate basic settings using persistent class.
        $quizsettings = (new seb_quiz_settings())->from_record($settings);
        $quizsettings->set('cmid', $this->quiz->cmid);
        $quizsettings->set('quizid', $this->quiz->id);

        // Edge case for filemanager_sebconfig.
        if ($quizsettings->get('requiresafeexambrowser') == settings_provider::USE_SEB_UPLOAD_CONFIG) {
            $errorvalidatefile = settings_provider::validate_draftarea_configfile($data['filemanager_sebconfigfile']);
            if (!empty($errorvalidatefile)) {
                $errors['filemanager_sebconfigfile'] = $errorvalidatefile;
            }
        }

        // Edge case to force user to select a template.
        if ($quizsettings->get('requiresafeexambrowser') == settings_provider::USE_SEB_TEMPLATE) {
            if (empty($data['seb_templateid'])) {
                $errors['seb_templateid'] = get_string('invalidtemplate', 'quizaccess_seb');
            }
        }

        if ($quizsettings->get('requiresafeexambrowser') != settings_provider::USE_SEB_NO) {
            // Global settings may be active which require a quiz password to be set if using SEB.
            if (!empty(get_config('quizaccess_seb', 'quizpasswordrequired')) && empty($data['quizpassword'])) {
                $errors['quizpassword'] = get_string('passwordnotset', 'quizaccess_seb');
            }
        }

        return $errors;
    }

    #[\Override]
    public function get_settings(?int $userid = null): ?array {
        if ($seb = seb_quiz_settings::get_by_quiz_id($this->quiz->id, $userid)) {
            return (array) $seb->to_record();
        }
        return null;
    }

    #[\Override]
    public function save_settings(stdClass $override): void {
        $defaults = [
            'seb_enabled'                => 0,
            'seb_requiresafeexambrowser' => 0,
            'seb_templateid'             => 0,
            'seb_allowedbrowserexamkeys' => '',
            'seb_showsebdownloadlink'    => 1,
        ];
        $defaults += settings_provider::get_seb_config_element_defaults();

        foreach ($defaults as $key => $default) {
            if (!isset($override->$key)) {
                $override->$key = $default;
            }
        }

        $seboverride = [
            'templateid'             => $override->seb_templateid,
            'requiresafeexambrowser' => $override->seb_requiresafeexambrowser,
            'showsebtaskbar'         => $override->seb_showsebtaskbar,
            'showwificontrol'        => $override->seb_showwificontrol,
            'showreloadbutton'       => $override->seb_showreloadbutton,
            'showtime'               => $override->seb_showtime,
            'showkeyboardlayout'     => $override->seb_showkeyboardlayout,
            'allowuserquitseb'       => $override->seb_allowuserquitseb,
            'quitpassword'           => $override->seb_quitpassword,
            'linkquitseb'            => $override->seb_linkquitseb,
            'userconfirmquit'        => $override->seb_userconfirmquit,
            'enableaudiocontrol'     => $override->seb_enableaudiocontrol,
            'muteonstartup'          => $override->seb_muteonstartup,
            'allowcapturecamera'     => $override->seb_allowcapturecamera,
            'allowcapturemicrophone' => $override->seb_allowcapturemicrophone,
            'allowspellchecking'     => $override->seb_allowspellchecking,
            'allowreloadinexam'      => $override->seb_allowreloadinexam,
            'activateurlfiltering'   => $override->seb_activateurlfiltering,
            'filterembeddedcontent'  => $override->seb_filterembeddedcontent,
            'expressionsallowed'     => $override->seb_expressionsallowed,
            'regexallowed'           => $override->seb_regexallowed,
            'expressionsblocked'     => $override->seb_expressionsblocked,
            'regexblocked'           => $override->seb_regexblocked,
            'allowedbrowserexamkeys' => $override->seb_allowedbrowserexamkeys,
            'showsebdownloadlink'    => $override->seb_showsebdownloadlink,
            'overrideid'             => $override->overrideid,
            'overrideenabled'        => $override->seb_enabled,
        ];

        $sebquizsettings = seb_quiz_settings::get_record(['quizid' => $this->quiz->id, 'overrideid' => $override->overrideid]);
        if ($sebquizsettings) {
            $sebquizsettings->set_many($seboverride);
            $sebquizsettings->save();
        } else {
            $seboverride['cmid'] = $this->quiz->cmid;
            $seboverride['quizid'] = $this->quiz->id;
            $sebquizsettings = new seb_quiz_settings(0, (object) $seboverride);
            $sebquizsettings->create();
        }
    }

    #[\Override]
    public function delete_settings(array $overrides): void {
        $overrideids = array_column($overrides, 'id');
        foreach ($overrideids as $overrideid) {
            if ($sebquizsettings = seb_quiz_settings::get_record(['quizid' => $this->quiz->id, 'overrideid' => $overrideid])) {
                $sebquizsettings->delete();
            }
        }
    }

    #[\Override]
    public function get_setting_names(): array {
        return [
            'seb_enabled',
            'seb_templateid',
            'seb_requiresafeexambrowser',
            'seb_showsebtaskbar',
            'seb_showwificontrol',
            'seb_showreloadbutton',
            'seb_showtime',
            'seb_showkeyboardlayout',
            'seb_allowuserquitseb',
            'seb_quitpassword',
            'seb_linkquitseb',
            'seb_userconfirmquit',
            'seb_enableaudiocontrol',
            'seb_muteonstartup',
            'seb_allowcapturecamera',
            'seb_allowcapturemicrophone',
            'seb_allowspellchecking',
            'seb_allowreloadinexam',
            'seb_activateurlfiltering',
            'seb_filterembeddedcontent',
            'seb_expressionsallowed',
            'seb_regexallowed',
            'seb_expressionsblocked',
            'seb_regexblocked',
            'seb_allowedbrowserexamkeys',
            'seb_showsebdownloadlink',
        ];
    }

    #[\Override]
    public function get_required_setting_names(): array {
        return ['seb_enabled'];
    }

    #[\Override]
    public function get_settings_sql(string $overridetablename): array {
        $selects = [
            'seb.overrideenabled seb_enabled',
            'seb.templateid seb_templateid',
            'seb.requiresafeexambrowser seb_requiresafeexambrowser',
            'seb.showsebtaskbar seb_showsebtaskbar',
            'seb.showwificontrol seb_showwificontrol',
            'seb.showreloadbutton seb_showreloadbutton',
            'seb.showtime seb_showtime',
            'seb.showkeyboardlayout seb_showkeyboardlayout',
            'seb.allowuserquitseb seb_allowuserquitseb',
            'seb.quitpassword seb_quitpassword',
            'seb.linkquitseb seb_linkquitseb',
            'seb.userconfirmquit seb_userconfirmquit',
            'seb.enableaudiocontrol seb_enableaudiocontrol',
            'seb.muteonstartup seb_muteonstartup',
            'seb.allowcapturecamera seb_allowcapturecamera',
            'seb.allowcapturemicrophone seb_allowcapturemicrophone',
            'seb.allowspellchecking seb_allowspellchecking',
            'seb.allowreloadinexam seb_allowreloadinexam',
            'seb.activateurlfiltering seb_activateurlfiltering',
            'seb.filterembeddedcontent seb_filterembeddedcontent',
            'seb.expressionsallowed seb_expressionsallowed',
            'seb.regexallowed seb_regexallowed',
            'seb.expressionsblocked seb_expressionsblocked',
            'seb.regexblocked seb_regexblocked',
            'seb.allowedbrowserexamkeys seb_allowedbrowserexamkeys',
            'seb.showsebdownloadlink seb_showsebdownloadlink',
        ];
        return [
            $selects,
            ["LEFT JOIN {quizaccess_seb_quizsettings} seb ON seb.overrideid = {$overridetablename}.id"],
            [],
        ];
    }

    #[\Override]
    public function add_table_fields(stdClass $override, array $fields, array $values): array {
        if (!empty($override->seb_enabled)) {
            $fields[] = get_string('seb_requiresafeexambrowser', 'quizaccess_seb');
            $values[] = settings_provider::get_requiresafeexambrowser_options(
                $this->context,
            )[$override->seb_requiresafeexambrowser];
        }
        return [$fields, $values];
    }

    #[\Override]
    public function clean_form_data(array $formdata): array {
        if (isset($formdata['seb_enabled']) && $formdata['seb_enabled'] === '0') {
            $formdata['seb_enabled'] = null;
        }
        return $formdata;
    }

    #[\Override]
    public function combine_group_overrides(stdClass $override, array $groupoverrides): stdClass {
        global $DB;

        $ids = array_column($groupoverrides, 'id');
        [$insql, $inparams] = $DB->get_in_or_equal($ids);
        $latestoverrideid = $DB->get_field_sql(
            "SELECT overrideid
                FROM {quizaccess_seb_quizsettings}
                WHERE overrideenabled = 1 AND overrideid {$insql}
            ORDER BY timecreated DESC
                LIMIT 1",
            $inparams,
        );

        if ($latestoverrideid) {
            $gpoverride = $groupoverrides[$latestoverrideid];

            foreach ($this->get_setting_names() as $setting) {
                if (isset($gpoverride->{$setting})) {
                    $override->{$setting} = $gpoverride->{$setting};
                }
            }
        }

        return $override;
    }
}
