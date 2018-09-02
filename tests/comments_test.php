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

/**
 * Tests for report comments events.
 *
 * @package    report_comments
 * @copyright  2017 iplusacademy.org
 * @author     Renaat Debleu <info@eWallah.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/comment/locallib.php');
require_once($CFG->dirroot . '/comment/lib.php');

/**
 * Class report_comments_events_testcase
 *
 * Class for tests related to comments report events.
 * @package    report_comments
 * @copyright  2017 iplusacademy.org
 * @author     Renaat Debleu <info@eWallah.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
class report_comments_tests_testcase extends advanced_testcase {

    /**
     * Setup testcase.
     */
    public function setUp() {
        $this->setAdminUser();
        $this->resetAfterTest();
    }

    /**
     * Test the report viewed event.
     *
     * It's not possible to use the moodle API to simulate the viewing of log report, so here we
     * simply create the event and trigger it.
     */
    public function test_report_viewed() {
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        // Trigger event for comments report viewed.
        $event = \report_comments\event\report_viewed::create(['context' => $context]);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        $this->assertInstanceOf('\report_comments\event\report_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new moodle_url('/report/comments/index.php', ['course' => $course->id]);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test privacy.
     */
    public function test_privacy() {
        $privacy = new report_comments\privacy\provider();
        $this->assertEquals($privacy->get_reason(), 'privacy:metadata');
    }

    /**
     * Test the usertable.
     *
     */
    public function test_usertable() {
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);
        $comment = $this->get_comment_object($coursecontext, $course);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $comment->add('First comment for user 1');
        $comment->add('Second comment for user 1');
        $table = new \report_comments_usertable($user->id);
        $row = new stdClass;
        $row->contextid = $coursecontext->id;
        $this->assertEquals(1, $table->col_id($row));
        $row->timecreated = time();
        $this->assertContains(date("Y"), $table->col_timecreated($row));
        $row->content = 'AB';
        $row->format = 'html';
        $this->assertContains('text_to_html', $table->col_content($row));
        $row->contexturl = $coursecontext->get_url();
        $row->contextid = context_user::instance($user->id);
        $row->userid = $user->id;
        $this->assertContains('class="userpicture', $table->col_userid($row));
        $this->assertContains('value="Delete"', $table->col_action($row));
    }

    /**
     * Creates a comment object
     *
     * @param  context $context A context object.
     * @param  stdClass $course A course object.
     * @return comment The comment object.
     */
    protected function get_comment_object($context, $course) {
        // Comment on course page.
        $args = new stdClass;
        $args->context = $context;
        $args->course = $course;
        $args->area = 'page_comments';
        $args->itemid = 0;
        $args->component = 'block_comments';
        $comment = new comment($args);
        $comment->set_post_permission(true);
        return $comment;
    }
}