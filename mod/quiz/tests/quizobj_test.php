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

namespace mod_quiz;

use basic_testcase;
use mod_quiz\question\display_options;
use mod_quiz\quiz_settings;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/tests/quiz_question_helper_test_trait.php');

/**
 * Unit tests for the quiz class
 *
 * @package    mod_quiz
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_quiz\quiz_settings
 */
final class quizobj_test extends \advanced_testcase {
    use \quiz_question_helper_test_trait;

    /**
     * Test cases for {@see test_cannot_review_message()}.
     *
     * @return array[]
     */
    public static function cannot_review_message_testcases(): array {
        return [
            // Review       Time close
            // Later close  quiz attempt    When                Expected
            // Quiz with no close date.
            [false, false, null, null, display_options::DURING, ''],
            [false, false, null, -60, display_options::IMMEDIATELY_AFTER, 'noreview'],
            [false, false, null, -180, display_options::LATER_WHILE_OPEN, 'noreview'],
            [false, false, null, -180, display_options::AFTER_CLOSE, 'noreview'],
            [false, true, null, null, display_options::DURING, ''],
            [false, true, null, -60, display_options::IMMEDIATELY_AFTER, 'noreview'],
            [false, true, null, -180, display_options::LATER_WHILE_OPEN, 'noreview'],
            [false, true, null, -180, display_options::AFTER_CLOSE, 'noreview'],
            // Quiz with a close in the future date, review only after close.
            [false, true, 300, null, display_options::DURING, ''],
            [false, true, 300, -60, display_options::IMMEDIATELY_AFTER, 300],
            [false, true, 300, -180, display_options::LATER_WHILE_OPEN, 300],
            // Quiz with a close in the future date, review later while open, or after close.
            [true, true, 300, null, display_options::DURING, ''],
            [true, true, 300, -60, display_options::IMMEDIATELY_AFTER, 60],
            [true, false, 300, -60, display_options::IMMEDIATELY_AFTER, 60],
            // Quiz with no closer date, review later while open.
            [true, false, 300, null, display_options::DURING, ''],
            [true, false, 300, -60, display_options::IMMEDIATELY_AFTER, 60],
        ];
    }

    /**
     * Unit test for {@see quiz_settings::cannot_review_message()}.
     *
     * @dataProvider cannot_review_message_testcases
     * @param bool $reviewlater whether the quiz allows reivew 'later while the quiz is still open'.
     * @param bool $reviewafterclose whether the quiz allows rievew 'after the quiz is closed'.
     * @param int|null $quizcloseoffset quiz close date, relative to now. Null means not set.
     * @param int|null $attemptsubmitoffset quiz attempt sumbite time relative to now. Null means not submitted yet.
     * @param int $attemptstate current state of the attempt, one of the display_options constants.
     * @param string|int $expectation expected result: '' means '', 'noreview' means noreview lang string,
     *      int means noreviewuntil with that time relative to now.
     */
    public function test_cannot_review_message(
        bool $reviewlater,
        bool $reviewafterclose,
        ?int $quizcloseoffset,
        ?int $attemptsubmitoffset,
        int $attemptstate,
        string|int $expectation
    ): void {
        $quiz = new stdClass();
        $now = time();

        $cm = new stdClass();
        $cm->id = 123;

        // Prepare quiz settings.
        $quiz->reviewattempt = display_options::DURING;
        if ($reviewlater) {
            $quiz->reviewattempt |= display_options::LATER_WHILE_OPEN;
        }
        if ($reviewafterclose) {
            $quiz->reviewattempt |= display_options::AFTER_CLOSE;
        }
        $quiz->attempts = 0;

        if ($quizcloseoffset === null) {
            $quiz->timeclose = 0;
        } else {
            $quiz->timeclose = $now + $quizcloseoffset;
        }
        if ($attemptsubmitoffset === null) {
            $submittime = 0;
        } else {
            $submittime = $now + $attemptsubmitoffset;
        }

        $quizobj = new quiz_settings($quiz, $cm, new stdClass(), false);

        // Prepare expected message.
        if ($expectation === 'noreview') {
            $expectation = get_string('noreview', 'quiz');
        } else if (is_int($expectation)) {
            $expectation = get_string('noreviewuntil', 'quiz', userdate($now + $expectation));
        }

        // Test.
        $this->assertEquals($expectation,
            $quizobj->cannot_review_message($attemptstate, false, $submittime));
    }

    /**
     * Create and apply a quiz override for a hidden group.
     */
    public function test_create_and_retrieve_group_override(): void {
        $this->setAdminUser();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        // Create course, quiz, users and 2 groups (one hidden, one visible) - enrol user to course and group.
        $course = $generator->create_course();
        $quiz = $this->create_test_quiz($course);
        $groupids = [
            'hidden' => groups_create_group((object) [
                'courseid' => $course->id,
                'name' => 'Hidden Group',
                'visibility' => GROUPS_VISIBILITY_NONE,
            ]),
            'visible' => groups_create_group((object) [
                'courseid' => $course->id,
                'name' => 'Visible Group',
                'visibility' => GROUPS_VISIBILITY_ALL,
            ]),
        ];

        // Create 5 users to be added to the groups.
        $users = [
            $generator->create_and_enrol($course, 'student'),
            $generator->create_and_enrol($course, 'student'),
            $generator->create_and_enrol($course, 'student'),
            $generator->create_and_enrol($course, 'student'),
            $generator->create_and_enrol($course, 'student'),
        ];

        // Add the first two users to the visible group.
        $generator->create_group_member(['groupid' => $groupids['visible'], 'userid' => $users[0]->id]);
        $generator->create_group_member(['groupid' => $groupids['visible'], 'userid' => $users[1]->id]);

        // And the last two to the hidden group.
        $generator->create_group_member(['groupid' => $groupids['hidden'], 'userid' => $users[2]->id]);
        $generator->create_group_member(['groupid' => $groupids['hidden'], 'userid' => $users[3]->id]);

        // The last user is not in a group.

        // Add a quiz override to the visible group.
        $quizobj = quiz_settings::create($quiz->id);
        $overridedata = [
            'quiz' => $quiz->id,
            'groupid' => $groupids['visible'],
            'password' => 'owl',
        ];
        $manager = $quizobj->get_override_manager();
        $manager->save_override($overridedata);

        // Confirm that one override exists in the database for this quiz and it's for the visible group.
        $overrides = $manager->get_all_overrides();
        $this->assertCount(1, $overrides);
        $this->assertEquals($groupids['visible'], reset($overrides)->groupid);

        // Confirm that for a user in the visible group there is a password rule.
        $quizobjuser0 = quiz_settings::create($quiz->id, $users[0]->id);
        $this->assertEquals('owl', $quizobjuser0->get_quiz()->password);

        // And confirm that for a user not in the visible group, there is not a password rule.
        $quizobjuser2 = quiz_settings::create($quiz->id, $users[2]->id);
        $this->assertEquals('', $quizobjuser2->get_quiz()->password);

        // Now add an override to the hidden group as well with a different password.
        $overridedata = [
            'quiz' => $quiz->id,
            'groupid' => $groupids['hidden'],
            'password' => 'fox',
        ];
        $manager->save_override($overridedata);

        // Confirm that there are now 2 override records for this quiz.
        $overrides = $manager->get_all_overrides();
        $this->assertCount(2, $overrides);

        // Confirm still that a user in the visible group has an override password of 'owl'.
        $quizobjuser1 = quiz_settings::create($quiz->id, $users[1]->id);
        $this->assertEquals('owl', $quizobjuser1->get_quiz()->password);

        // And that a user in the hidden group has an override password of 'fox'.
        $quizobjuser3 = quiz_settings::create($quiz->id, $users[3]->id);
        $this->assertEquals('fox', $quizobjuser3->get_quiz()->password);

        // Finally, confirm that the user not in any groups has no password.
        $quizobjuser4 = quiz_settings::create($quiz->id, $users[4]->id);
        $this->assertEquals('', $quizobjuser4->get_quiz()->password);
    }
}
