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
namespace report_comments;

use advanced_testcase;
use context_course;
use context_coursecat;
use context_module;
use context_system;
use context_user;
use core_user;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Class report_comments_events_testcase
 *
 * Class for tests related to comments report events.
 * @package    report_comments
 * @copyright  2017 iplusacademy.org
 * @author     Renaat Debleu <info@eWallah.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
class comments_test extends advanced_testcase {

    /**
     * @var stdClass The course.
     */
    private $course;

    /**
     * @var stdClass The teacher.
     */
    private $teacher;

    /**
     * @var stdClass A course that can be commented.
     */
    private $comment;

    /**
     * @var stdClass A glossary that can be commented.
     */
    private $glossarycomment;

    /**
     * Setup testcase.
     */
    public function setUp():void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/comment/lib.php');
        $this->setAdminUser();
        $this->resetAfterTest();
        $CFG->usecomments = 1;
        $CFG->enablenotes = 1;
        $CFG->commentnotifications = 1;
        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_user();
        $editingteacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $editingteacherroleid);
        $this->comment = $this->get_comment_object($this->course);
        $this->comment->add('First comment from teacher');
        $this->setUser($this->teacher->id);
        $this->comment->add('First comment from admin user');
        $this->glossarycomment = $this->get_glossarycomment_object($this->course);
        $this->glossarycomment->add('Second comment from teacher');
    }

    /**
     * Test the report viewed event.
     * @covers \report_comments\event\report_viewed
     */
    public function test_report_viewed() {
        $context = context_course::instance($this->course->id);
        require_capability('report/comments:view', $context);
        $event = event\report_viewed::create(['context' => $context]);
        $this->assertEquals('Comments report viewed', $event->get_name());
        $this->assertStringContainsString('The user with id ', $event->get_description());
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
     * @covers \report_comments\observer
     */
    public function test_observer() {
        $context = context_course::instance($this->course->id);
        $sink = $this->redirectEvents();
        $messagesink = $this->redirectMessages();
        $this->comment->add('First comment');
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_pop($events);
        $this->assertInstanceOf('\core\event\comment_created', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
        observer::commentcreated($event);
        $messages = $messagesink->get_messages();
        $this->assertCount(1, $messages);
    }

    /**
     * Test privacy.
     * @covers \report_comments\privacy\provider
     */
    public function test_privacy() {

        $privacy = new privacy\provider();
        $this->assertEquals($privacy->get_reason(), 'privacy:metadata');
    }

    /**
     * Test the usertable.
     * @covers \report_comments\usertable
     */
    public function test_usertable() {
        $coursecontext = context_course::instance($this->course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        assign_capability('report/comments:view', CAP_ALLOW, 5, context_system::instance()->id, true);
        reload_all_capabilities();
        $this->comment->add('First comment for user 2');
        $this->comment->add('Second comment for user 2');
        $this->glossarycomment->add('Third comment for user 2');
        $this->glossarycomment->add('Fourt comment for user 2');
        $table = new usertable($user->id);
        $row = new stdClass;
        $row->contextid = $coursecontext->id;
        $this->assertEquals(1, $table->col_id($row));
        $row->timecreated = time();
        $this->assertStringContainsString(date("Y"), $table->col_timecreated($row));
        $row->content = 'AB';
        $row->format = 'html';
        $this->assertStringContainsString('text_to_html', $table->col_content($row));
        $row->contexturl = $coursecontext->get_url();
        $row->contextid = context_user::instance($user->id);
        $row->userid = $user->id;
        $this->assertStringContainsString('profile', $table->col_userid($row));
        $this->assertStringContainsString('value="Delete"', $table->col_action($row));
        $this->setAdminUser();
        $table = new usertable($user->id, true);
        ob_start();
        $table->out(9999, true);
        $data = ob_end_clean();
        $this->assertNotEmpty($data);
        $table = new usertable($user->id, false);
        ob_start();
        $table->out(9999, false);
        $data = ob_end_clean();
        $this->assertNotEmpty($data);
    }

    /**
     * Test the invalid usertable.
     * @covers \report_comments\usertable
     */
    public function test_invalid_usertable() {
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = context_coursecat::instance($category->id);
        $this->setAdminUser();
        $table = new usertable(2);
        $row = new stdClass;
        $row->contextid = $categorycontext->id;
        try {
            $this->assertEquals(1, $table->col_id($row));
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('error/invalid context', $e->getMessage());
        }
    }

    /**
     * Tests the locallib.
     * @coversNothing
     */
    public function test_locallib() {
        global $CFG;
        require_once($CFG->dirroot . '/report/comments/locallib.php');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->comment->add('First comment for user 1');
        $this->comment->add('Second comment for user 1');
        $this->comment->add('Second comment for user 1');
        $this->glossarycomment->add('Third comment for user 1');
        $this->glossarycomment->add('Fourt comment for user 1');
        $this->glossarycomment->add('Fourt comment for user 1');
        $this->setUser($this->teacher->id);
        $this->assertEquals([], report_comments_getusercomments(8888));
        $this->assertCount(6, report_comments_getusercomments($user->id));
        $this->assertCount(6, report_comments_getusercomments($user->id, 'content', 3));
        $this->assertCount(6, report_comments_getusercomments($user->id, 'content', 4));
        $this->assertCount(6, report_comments_getusercomments($user->id, 'author', 3));
        $this->assertCount(6, report_comments_getusercomments($user->id, 'author', 4));
        $this->assertCount(6, report_comments_getusercomments($user->id, 'date', 3));
        $this->assertCount(6, report_comments_getusercomments($user->id, 'date', 4));
        $this->assertCount(9, report_comments_getcoursecomments($this->course->id));
        $this->assertCount(2, report_comments_page_type_list(null, null, null));
    }

    /**
     * Tests the report navigation as an admin.
     * @coversNothing
     */
    public function test_navigation() {
        global $CFG, $PAGE, $USER;
        require_once($CFG->dirroot . '/report/comments/lib.php');
        $context = context_course::instance($this->course->id);
        $this->setAdminUser();
        $PAGE->set_url('/course/view.php', ['id' => $this->course->id]);
        $tree = new \global_navigation($PAGE);
        report_comments_extend_navigation_course($tree, $this->course, $context);
        $user = $this->getDataGenerator()->create_user();
        $tree = new core_user\output\myprofile\tree();
        $this->assertTrue(report_comments_myprofile_navigation($tree, $user, true, $this->course));
        $tree = new core_user\output\myprofile\tree();
        $this->assertTrue(report_comments_myprofile_navigation($tree, $this->teacher, true, $this->course));
        $this->setGuestUser();
        $this->assertFalse(report_comments_myprofile_navigation($tree, $USER, true, $this->course));
    }

    /**
     * Tests orther files.
     * @coversNothing
     */
    public function test_other() {
        global $CFG, $PAGE;
        $this->setAdminUser();
        require_once($CFG->dirroot . '/report/comments/db/access.php');
        chdir($CFG->dirroot . '/report/comments');
        $_POST['course'] = $this->course->id;
        $this->expectException(moodle_exception::class);
        include($CFG->dirroot . '/report/comments/index.php');
    }

    /**
     * Tests settings.
     * @coversNothing
     */
    public function test_settings() {
        global $ADMIN, $CFG;
        require_once($CFG->dirroot . '/lib/adminlib.php');
        $ADMIN = admin_get_root(true, true);
        $this->assertTrue($ADMIN->fulltree);
    }

    /**
     * Creates a comment object
     *
     * @param  stdClass $course A course object.
     * @return comment The comment object.
     */
    protected function get_comment_object($course) {
        // Comment on course page.
        $args = new stdClass;
        $args->context = context_course::instance($course->id);
        $args->course = $course;
        $args->area = 'page_comments';
        $args->itemid = 0;
        $args->component = 'block_comments';
        $comment = new \comment($args);
        $comment->set_post_permission(true);
        return $comment;
    }

    /**
     * Creates a comment object
     *
     * @param  stdClass $course A course object.
     * @return comment The comment object.
     */
    protected function get_glossarycomment_object($course) {
        $glossary = $this->getDataGenerator()->create_module('glossary', ['course' => $course]);
        $glossarygenerator = $this->getDataGenerator()->get_plugin_generator('mod_glossary');
        $entry = $glossarygenerator->create_content($glossary);
        $cm = get_coursemodule_from_instance('glossary', $glossary->id, $this->course->id);
        $cmt = new stdClass();
        $cmt->component = 'mod_glossary';
        $cmt->context = context_module::instance($glossary->cmid);
        $cmt->course = $this->course;
        $cmt->cm = $cm;
        $cmt->area = 'glossary_entry';
        $cmt->itemid = $entry->id;
        $cmt->showcount = true;
        $comment = new \comment($cmt);
        $comment->set_post_permission(true);
        return $comment;
    }
}
