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
     * @var stdClass The course.
     */
    private $course;

    /**
     * @var stdClass A thing that can be commented.
     */
    private $comment;

    /**
     * Setup testcase.
     */
    public function setUp() {
        global $CFG, $DB;
        $this->setAdminUser();
        $this->resetAfterTest();
        $CFG->usecomments = 1;
        $CFG->enablenotes = 1;
        $CFG->commentnotifications = 1;
        $this->course = $this->getDataGenerator()->create_course();
        $coursecontext = context_course::instance($this->course->id);
        $editingteacher = $this->getDataGenerator()->create_user();
        $editingteacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($editingteacher->id, $this->course->id, $editingteacherroleid);
        $this->comment = $this->get_comment_object($coursecontext, $this->course);
        $this->comment->add('First comment from teacher');
        $this->setUser($editingteacher->id);
        $this->comment->add('First comment from admin user');
        $this->setAdminUser();
        $glossary = $this->getDataGenerator()->create_module('glossary', ['course' => $this->course]);
        $glossarygenerator = $this->getDataGenerator()->get_plugin_generator('mod_glossary');
        $entry = $glossarygenerator->create_content($glossary);
        $context = context_module::instance($glossary->cmid);
        $cm = get_coursemodule_from_instance('glossary', $glossary->id, $this->course->id);
        $cmt = new stdClass();
        $cmt->component = 'mod_glossary';
        $cmt->context = $context;
        $cmt->course = $this->course;
        $cmt->cm = $cm;
        $cmt->area = 'glossary_entry';
        $cmt->itemid = $entry->id;
        $cmt->showcount = true;
        $comment = new comment($cmt);
        $comment->add('Second comment from admin user');
    }

    /**
     * Test the report viewed event.
     *
     */
    public function test_report_viewed() {
        $context = context_course::instance($this->course->id);
        require_capability('report/comments:view', $context);
        $event = \report_comments\event\report_viewed::create(['context' => $context]);
        $this->assertEquals('Comments report viewed', $event->get_name());
        $this->assertContains('The user with id ', $event->get_description());
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);
        $this->assertInstanceOf('\report_comments\event\report_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new moodle_url('/report/comments/index.php', ['course' => $this->course->id]);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test observer.
     *
     */
    public function test_observer() {
        $context = context_course::instance($this->course->id);
        $sink = $this->redirectEvents();
        $messagesink = $this->redirectMessages();
        $this->comment->add('First comment');
        $events = $sink->get_events();
        while ($task = \core\task\manager::get_next_adhoc_task(time())) {
            $task->execute();
            \core\task\manager::adhoc_task_complete($task);
        }
        $this->assertCount(1, $events);
        $event = array_pop($events);
        $this->assertInstanceOf('\core\event\comment_created', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
        $messages = $messagesink->get_messages();
        $this->assertCount(0, $messages);
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
        $coursecontext = context_course::instance($this->course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->comment->add('First comment for user 1');
        $this->comment->add('Second comment for user 1');
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
        $table = new \report_comments_usertable($user->id, true);
    }

    /**
     * Tests the locallib.
     */
    public function test_locallib() {
        global $CFG;
        require_once($CFG->dirroot . '/report/comments/locallib.php');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->comment->add('First comment for user 1');
        $this->comment->add('Second comment for user 1');
        $results = report_comments_getusercomments(8888);
        $this->assertEquals('', $results);
        $results = report_comments_getusercomments($user->id);
        $this->assertCount(2, $results);
        $results = report_comments_getusercomments($user->id, 'content');
        $this->assertCount(2, $results);
        $results = report_comments_getusercomments($user->id, 'author');
        $this->assertCount(2, $results);
        $results = report_comments_getcoursecomments($this->course->id);
        $this->assertCount(5, $results);
    }

    /**
     * Tests the report navigation as an admin.
     */
    public function test_navigation() {
        global $CFG, $PAGE, $USER;
        require_once($CFG->dirroot . '/report/comments/lib.php');
        $context = context_course::instance($this->course->id);
        $PAGE->set_url('/course/view.php', ['id' => $this->course->id]);
        $tree = new \global_navigation($PAGE);
        report_comments_extend_navigation_course($tree, $this->course, $context);
        $user = $this->getDataGenerator()->create_user();
        $tree = new \core_user\output\myprofile\tree();
        report_comments_myprofile_navigation($tree, $user, true, $this->course);
        $children = serialize($tree);
        $this->assertContains('Comments', $children);
        $tree = new \core_user\output\myprofile\tree();
        report_comments_myprofile_navigation($tree, $USER, true, $this->course);
        $children = serialize($tree);
        $this->assertContains('Comments', $children);
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