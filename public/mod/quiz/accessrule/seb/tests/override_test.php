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

use mod_quiz_external;
use mod_quiz\quiz_settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use quizaccess_seb\helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/test_helper_trait.php');
require_once($CFG->dirroot . '/mod/quiz/tests/quiz_question_helper_test_trait.php');

/**
 * Tests for Safe Exam Browser access rules
 *
 * @package    quizaccess_seb
 * @copyright  2024 Michael Kotlyar <michael.kotlyar@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(helper::class)]
#[CoversMethod(helper::class, 'get_seb_config_content')]
#[CoversClass(seb_quiz_settings::class)]
#[CoversMethod(seb_quiz_settings::class, 'get_config_by_quiz_id')]
final class override_test extends \advanced_testcase {
    use \quizaccess_seb_test_helper_trait;

    /**
     * Set up method.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Set up test course and enrol a user and a user to override settings with.
     *
     * @return array
     */
    protected function setup_test_course(): array {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $overrideuser = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->getDataGenerator()->enrol_user($overrideuser->id, $course->id);

        return [$course, $user, $overrideuser];
    }

    /**
     * Test that the correct user gets overridden when accessing a quiz.
     */
    public function test_override_applies_to_correct_user(): void {
        [$course, $user, $overrideuser] = $this->setup_test_course();
        $users = [$user, $overrideuser];

        // Create a quiz with no SEB access rules.
        $this->quiz = $this->create_test_quiz($course);

        // Create an override for overrideuser.
        $this->setAdminUser();
        $overrideid = $this->save_override($overrideuser);

        // Check overrideuser is overridden.
        $this->setUser($overrideuser);
        $config = helper::get_seb_config_content($this->quiz->cmid);
        $this->assertNotEmpty($config);

        // Check there are no SEB settings for non-overridden user.
        $this->setUser($user);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('@' . 'No SEB config could be found for quiz with cmid: ' . $this->quiz->cmid . '@');
        helper::get_seb_config_content($this->quiz->cmid);
    }

    /**
     * Test overridden settings apply to user over base SEB settings.
     */
    public function test_override_applies_to_correct_user_over_base_seb_config(): void {
        [$course, $user, $overrideuser] = $this->setup_test_course();
        $users = [$user, $overrideuser];

        // Create a quiz with no SEB access rules.
        $this->quiz = $this->create_test_quiz($course);

        // Create an override for overrideuser.
        $this->setAdminUser();
        $overrideid = $this->save_override($overrideuser);

        // Add SEB settings to quiz.
        $settings = $this->get_test_settings([
            'quizid' => $this->quiz->id,
            'cmid' => $this->quiz->cmid,
        ]);
        $quizsettings = new seb_quiz_settings(0, $settings);
        $quizsettings->save();

        // Check both users have settings.
        $configs = [];
        foreach ($users as $user) {
            $this->setUser($user);
            $config = helper::get_seb_config_content($this->quiz->cmid);
            $this->assertNotEmpty($config);
            $configs[] = $config;
        }
        $this->setUser($overrideuser);
        $config = helper::get_seb_config_content($this->quiz->cmid);

        // Check that settings are equal (both SEB settings should be identical as they are default).
        $this->setAdminUser();
        $this->assertEquals($configs[0], $configs[1]);

        // Change the override settings, check override now differs from base settings.
        $manager = quiz_settings::create($this->quiz->id)->get_override_manager();
        $this->save_override($overrideuser, ['id' => $overrideid, 'seb_showreloadbutton' => 0]);

        $newconfigs = [];
        foreach ($users as $user) {
            $this->setUser($user);
            $config = helper::get_seb_config_content($this->quiz->cmid);
            $this->assertNotEmpty($config);
            $newconfigs[] = $config;
        }

        $this->assertNotEquals($newconfigs[0], $newconfigs[1]);
    }

    /**
     * Test override no longer applies when removed.
     */
    public function test_override_no_longer_applies_when_removed(): void {
        [$course, $user, $overrideuser] = $this->setup_test_course();
        $users = [$user, $overrideuser];

        // Create a quiz with default manual SEB access rule.
        $this->quiz = $this->create_test_quiz($course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        // Create an override for overrideuser.
        $this->setAdminUser();
        $overrideid = $this->save_override($overrideuser, ['seb_showwificontrol' => 1]);

        // Check override user uses overridden settings rather than base SEB settings.
        $configs = [];
        foreach ($users as $user) {
            $this->setUser($user);
            $config = helper::get_seb_config_content($this->quiz->cmid);
            $this->assertNotEmpty($config);
            $configs[] = $config;
        }
        $this->assertNotEquals($configs[0], $configs[1]);

        // Remove override from override user.
        quiz_settings::create($this->quiz->id)
            ->get_override_manager()
            ->delete_overrides_by_id([$overrideid]);

        // Check override user now uses base SEB settings.
        $configs = [];
        foreach ($users as $user) {
            $this->setUser($user);
            $config = helper::get_seb_config_content($this->quiz->cmid);
            $this->assertNotEmpty($config);
            $configs[] = $config;
        }
        $this->assertEquals($configs[0], $configs[1]);
    }

    /**
     * Test that there are no base and override SEB settings after removal.
     */
    public function test_override_and_non_override_no_longer_applies_when_removed(): void {
        [$course, $user, $overrideuser] = $this->setup_test_course();
        $users = [$user, $overrideuser];

        // Create a quiz with manual default SEB access rules.
        $this->quiz = $this->create_test_quiz($course);
        $settings = $this->get_test_settings([
            'quizid' => $this->quiz->id,
            'cmid' => $this->quiz->cmid,
        ]);
        $quizsettings = new seb_quiz_settings(0, $settings);
        $quizsettings->save();

        // Create an SEB override for overrideuser.
        $this->setAdminUser();
        $overrideid = $this->save_override($overrideuser, ['seb_showwificontrol' => 1]);

        // Check configs are applied and are different.
        $configs = [];
        foreach ($users as $user) {
            $this->setUser($user);
            $config = helper::get_seb_config_content($this->quiz->cmid);
            $this->assertNotEmpty($config);
            $configs[] = $config;
        }
        $this->assertNotEquals($configs[0], $configs[1]);

        // Delete base settings and override.
        $del = $quizsettings->delete();
        quiz_settings::create($this->quiz->id)
            ->get_override_manager()
            ->delete_overrides_by_id([$overrideid]);

        // Check both users no longer have SEB settings.
        foreach ($users as $user) {
            $this->setUser($user);
            $this->expectException(\moodle_exception::class);
            $this->expectExceptionMessageMatches(
                '@' . 'No SEB config could be found for quiz with cmid: ' . $this->quiz->cmid . '@',
            );
            $config = helper::get_seb_config_content($this->quiz->cmid);
        }
    }

    /**
     * Test quiz override for SEB, checking the SEB values retrieved are correct.
     */
    public function test_override_settings_values(): void {
        [$course, $user, $overrideuser] = $this->setup_test_course();
        // Create quiz and add SEB access rule.
        $this->quiz = $this->create_test_quiz($course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        // Override user seb settings.
        $this->save_override($overrideuser, [
            'seb_requiresafeexambrowser' => 1,
            'seb_showsebtaskbar' => 0,
            'seb_showwificontrol' => 1,
            'seb_showreloadbutton' => 0,
            'seb_showtime' => 0,
            'seb_showkeyboardlayout' => 0,
            'seb_allowuserquitseb' => 0,
            'seb_quitpassword' => 'test',
            'seb_linkquitseb' => 'https://example.com/quit',
            'seb_userconfirmquit' => 0,
            'seb_enableaudiocontrol' => 1,
            'seb_muteonstartup' => 1,
            'seb_allowspellchecking' => 1,
            'seb_allowreloadinexam' => 0,
            'seb_allowcapturecamera' => 1,
            'seb_allowcapturemicrophone' => 1,
            'seb_activateurlfiltering' => 1,
            'seb_filterembeddedcontent' => 1,
            'seb_expressionsallowed' => 'test.com',
            'seb_regexallowed' => '^allow$',
            'seb_expressionsblocked' => 'bad.com',
            'seb_regexblocked' => '^bad$',
            'seb_showsebdownloadlink' => 0,
        ]);

        // Check we are retrieving overridden settings.
        $this->setUser($overrideuser);
        $sebconfig = seb_quiz_settings::get_by_quiz_id($this->quiz->id);

        $this->assertEquals(1, $sebconfig->get('requiresafeexambrowser'));
        $this->assertEquals(0, $sebconfig->get('showsebtaskbar'));
        $this->assertEquals(1, $sebconfig->get('showwificontrol'));
        $this->assertEquals(0, $sebconfig->get('showreloadbutton'));
        $this->assertEquals(0, $sebconfig->get('showtime'));
        $this->assertEquals(0, $sebconfig->get('showkeyboardlayout'));
        $this->assertEquals(0, $sebconfig->get('allowuserquitseb'));
        $this->assertEquals('test', $sebconfig->get('quitpassword'));
        $this->assertEquals('https://example.com/quit', $sebconfig->get('linkquitseb'));
        $this->assertEquals(0, $sebconfig->get('userconfirmquit'));
        $this->assertEquals(1, $sebconfig->get('enableaudiocontrol'));
        $this->assertEquals(1, $sebconfig->get('muteonstartup'));
        $this->assertEquals(1, $sebconfig->get('allowcapturecamera'));
        $this->assertEquals(1, $sebconfig->get('allowcapturemicrophone'));
        $this->assertEquals(1, $sebconfig->get('allowspellchecking'));
        $this->assertEquals(0, $sebconfig->get('allowreloadinexam'));
        $this->assertEquals(1, $sebconfig->get('activateurlfiltering'));
        $this->assertEquals(1, $sebconfig->get('filterembeddedcontent'));
        $this->assertEquals('test.com', $sebconfig->get('expressionsallowed'));
        $this->assertEquals('^allow$', $sebconfig->get('regexallowed'));
        $this->assertEquals('bad.com', $sebconfig->get('expressionsblocked'));
        $this->assertEquals('^bad$', $sebconfig->get('regexblocked'));
        $this->assertEquals(0, $sebconfig->get('showsebdownloadlink'));
        $this->assertEquals([], $sebconfig->get('allowedbrowserexamkeys'));

        // Test normal user is not overridden.
        $this->setUser($user);
        $sebconfig = seb_quiz_settings::get_by_quiz_id($this->quiz->id);

        $this->assertEquals(0, $sebconfig->get('activateurlfiltering'));
        $this->assertEquals([], $sebconfig->get('allowedbrowserexamkeys'));
        $this->assertEquals(1, $sebconfig->get('allowreloadinexam'));
        $this->assertEquals(0, $sebconfig->get('allowspellchecking'));
        $this->assertEquals(1, $sebconfig->get('allowuserquitseb'));
        $this->assertEquals(0, $sebconfig->get('enableaudiocontrol'));
        $this->assertEquals('', $sebconfig->get('expressionsallowed'));
        $this->assertEquals('', $sebconfig->get('expressionsblocked'));
        $this->assertEquals(0, $sebconfig->get('filterembeddedcontent'));
        $this->assertEquals('', $sebconfig->get('linkquitseb'));
        $this->assertEquals(0, $sebconfig->get('muteonstartup'));
        $this->assertEquals('', $sebconfig->get('quitpassword'));
        $this->assertEquals('', $sebconfig->get('regexallowed'));
        $this->assertEquals('', $sebconfig->get('regexblocked'));
        $this->assertEquals(1, $sebconfig->get('requiresafeexambrowser'));
        $this->assertEquals(1, $sebconfig->get('showkeyboardlayout'));
        $this->assertEquals(1, $sebconfig->get('showreloadbutton'));
        $this->assertEquals(1, $sebconfig->get('showsebdownloadlink'));
        $this->assertEquals(1, $sebconfig->get('showsebtaskbar'));
        $this->assertEquals(1, $sebconfig->get('showtime'));
        $this->assertEquals(0, $sebconfig->get('showwificontrol'));
        $this->assertEquals(1, $sebconfig->get('userconfirmquit'));
    }

    /**
     * Test quiz override settings for SEB are correctly cached.
     *
     * Requires some repetition to utlilise and check the cache.
     */
    public function test_override_cache(): void {
        [$course, $user, $overrideuser] = $this->setup_test_course();
        $this->quiz = $this->create_test_quiz($course);
        $settings = $this->get_test_settings([
            'quizid' => $this->quiz->id,
            'cmid' => $this->quiz->cmid,
            'muteonstartup' => '1',
        ]);
        $quizsettings = new seb_quiz_settings(0, $settings);
        $quizsettings->save();
        $sebconfigcache = \cache::make('quizaccess_seb', 'config');
        $sebconfigkeycache = \cache::make('quizaccess_seb', 'configkey');

        // Retrieve SEB settings, triggering the cache.
        $sebconfig = $quizsettings->get_config();
        $cachedsebconfig = $sebconfigcache->get($this->quiz->id);
        $this->assertNotEmpty($sebconfig);
        $this->assertNotEmpty($cachedsebconfig);
        $this->assertEquals($sebconfig, $cachedsebconfig);

        $sebkey = $quizsettings->get_config_key();
        $cachedsebkey = $sebconfigkeycache->get($this->quiz->id);
        $this->assertNotEmpty($sebkey);
        $this->assertNotEmpty($cachedsebkey);
        $this->assertEquals($sebkey, $cachedsebkey);

        // Override the user.
        $overrideid = $this->save_override($overrideuser);
        $overrideindexkey = "{$this->quiz->id}-{$overrideid}";

        // Retrieve overridden SEB settings.
        $this->setUser($overrideuser);

        $quizsettings = seb_quiz_settings::get_by_quiz_id($this->quiz->id);
        $overridesebconfig = $quizsettings->get_config();
        $overridecachesebconfig = $sebconfigcache->get($overrideindexkey);
        $this->assertNotEmpty($overridesebconfig);
        $this->assertNotEmpty($overridecachesebconfig);
        $this->assertEquals($overridesebconfig, $overridecachesebconfig);

        $overridesebkey = $quizsettings->get_config_key();
        $overridecachedsebkey = $sebconfigkeycache->get($overrideindexkey);
        $this->assertNotEmpty($overridesebkey);
        $this->assertNotEmpty($overridecachedsebkey);
        $this->assertEquals($overridesebkey, $overridecachedsebkey);

        // Test overridden and original seb settings are different.
        $this->assertNotEquals($overridesebkey, $sebkey);
        $this->assertNotEquals($overridecachedsebkey, $cachedsebkey);
        $this->assertNotEquals($overridesebconfig, $sebconfig);
        $this->assertNotEquals($overridecachesebconfig, $cachedsebconfig);

        // Delete original settings.
        $this->setAdminUser();
        $quizsettings = seb_quiz_settings::get_by_quiz_id($this->quiz->id);
        $quizsettings->delete();

        // Test cached settings are gone and cached override settings are unaffected.
        $quizsettings = seb_quiz_settings::get_by_quiz_id($this->quiz->id);
        $cachedsebconfig = $sebconfigcache->get($this->quiz->id);
        $cachedsebkey = $sebconfigkeycache->get($this->quiz->id);
        $this->assertNull($quizsettings);
        $this->assertEmpty($cachedsebconfig);
        $this->assertEmpty($cachedsebkey);

        $this->setUser($overrideuser);

        $quizsettings = seb_quiz_settings::get_by_quiz_id($this->quiz->id);

        $overridesebconfig = $quizsettings->get_config();
        $overridecachesebconfig = $sebconfigcache->get($overrideindexkey);
        $this->assertNotEmpty($overridesebconfig);
        $this->assertNotEmpty($overridecachesebconfig);
        $this->assertEquals($overridesebconfig, $overridecachesebconfig);

        $overridesebkey = $quizsettings->get_config_key();
        $overridecachedsebkey = $sebconfigkeycache->get($overrideindexkey);
        $this->assertNotEmpty($overridesebkey);
        $this->assertNotEmpty($overridecachedsebkey);
        $this->assertEquals($overridesebkey, $overridecachedsebkey);

        // Delete override settings.
        quiz_settings::create($this->quiz->id)
            ->get_override_manager()
            ->delete_overrides_by_id([$overrideid]);

        // Test override settings are now empty.
        $quizsettings = seb_quiz_settings::get_by_quiz_id($this->quiz->id);
        $overridecachesebconfig = $sebconfigcache->get($overrideindexkey);
        $overridecachedsebkey = $sebconfigkeycache->get($overrideindexkey);
        $this->assertNull($quizsettings);
        $this->assertEmpty($overridecachesebconfig);
        $this->assertEmpty($overridecachedsebkey);
    }

    /**
     * Test overridden settings are correctly fetched with external function mod_quiz_external::get_quiz_access_information.
     */
    public function test_get_quiz_access_information_with_override(): void {
        // Create a new quiz.
        [$course, $user, $overrideuser] = $this->setup_test_course();
        $this->quiz = $this->create_test_quiz($course);

        // Add SEB access rule.
        $settings = $this->get_test_settings([
            'quizid' => $this->quiz->id,
            'cmid' => $this->quiz->cmid,
            'muteonstartup' => '1',
        ]);
        $quizsettings = new seb_quiz_settings(0, $settings);
        $quizsettings->save();

        // Get access manager rule descriptions.
        $cm = get_coursemodule_from_id('quiz', $this->quiz->cmid, $course->id, false, MUST_EXIST);
        $quizsettings = new quiz_settings($this->quiz, $cm, $course);
        $accessmanager = $quizsettings->get_access_manager(time());
        $expected = $accessmanager->describe_rules();

        // Get information via external function.
        $info = mod_quiz_external::get_quiz_access_information($this->quiz->id);
        $result = $info['accessrules'];

        $this->assertEquals($expected, $result);

        // Override a user, make sure get_quiz_access_information is not affected.
        $this->save_override($overrideuser);

        $info = mod_quiz_external::get_quiz_access_information($this->quiz->id);
        $result = $info['accessrules'];

        $this->assertEquals($expected, $result);
    }
}
