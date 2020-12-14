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
 * Comments report
 *
 * @package   report_comments
 * @copyright 2017 iplusacademy.org
 * @author    Renaat Debleu (www.eWallah.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/comment/lib.php');
require_once($CFG->dirroot . '/comment/locallib.php');
require_once($CFG->dirroot . '/report/comments/locallib.php');
require_once($CFG->libdir . '/tablelib.php');

$courseid   = required_param('course', PARAM_INT);
$userid     = optional_param('id', 0, PARAM_INT);
$commentid  = optional_param('commentid', 0, PARAM_INT);
$action     = optional_param('action', '', PARAM_ALPHA);
$confirm    = optional_param('confirm', 0, PARAM_INT);
$sort       = optional_param('tsort', 'date', PARAM_ALPHA);
$sortdir    = optional_param('tdir', 3, PARAM_INT);
$download   = optional_param('download', '', PARAM_ALPHA);

$course = get_course($courseid);
$context = context_course::instance($courseid);

$url = '/report/comments/index.php';
$arr = ['course' => $courseid];

require_login();
require_capability('report/comments:view', $context);

$strcomments = get_string('comments');

if ($courseid > 1) {
    $PAGE->set_course($course);
}
$PAGE->set_pagelayout('report');
$PAGE->set_url(new moodle_url($url, $arr));
$PAGE->set_context($context);
$PAGE->set_pagetype('report-comments');
$PAGE->set_title($strcomments);
$PAGE->set_heading($strcomments);

if ($action and !confirm_sesskey()) {
    // No action if sesskey not confirmed.
    $action = '';
}

if ($action === 'delete') {
    // Delete a single comment.
    if (!empty($commentid)) {
        if (!$confirm) {
            echo $OUTPUT->header();
            $optionsyes = ['course' => $courseid, 'action' => 'delete', 'commentid' => $commentid,
                           'confirm' => 1, 'sesskey' => sesskey()];
            $optionsno  = ['course' => $courseid, 'sesskey' => sesskey()];
            $buttoncontinue = new single_button(new moodle_url($url, $optionsyes), get_string('delete'));
            $buttoncancel = new single_button(new moodle_url($url, $optionsno), get_string('cancel'));
            echo $OUTPUT->confirm(get_string('confirmdeletecomments', 'admin'), $buttoncontinue, $buttoncancel);
            echo $OUTPUT->footer();
            die;
        } else {
            $manager = new comment_manager();
            if ($manager->delete_comment($commentid)) {
                redirect(new moodle_url($url, $arr));
            } else {
                $err = 'cannotdeletecomment';
            }
        }
    }
}

echo $OUTPUT->header();

$tabl = new flexible_table('admin-comments-compatible');

if ($userid == 0) {
    echo html_writer::tag('h3', $course->fullname);
    $comments = report_comments_getcoursecomments($courseid, $sort, $sortdir);
    $tabl->define_columns(['date', 'author', 'content', 'action']);
    $tabl->define_headers([get_string('date'), get_string('author', 'search'), get_string('content'), get_string('action')]);

} else {
    $table = new \report_comments\usertable($userid, $download);
    if ($table->is_downloading($download)) {
         $table->out(999, true);
    } else {
         $table->out(5, true);
    }
    $user = $DB->get_record('user', ['id' => $userid], 'firstname, lastname');
    echo html_writer::tag('h3', $user->firstname . ' ' . $user->lastname);
    $comments = report_comments_getusercomments($userid);
    $tabl->define_columns(['date', 'course', 'content', 'action']);
    $tabl->define_headers([get_string('date'), get_string('course'), get_string('content'), get_string('action')]);
}


if (count($comments) == 0) {
    echo $OUTPUT->notification(get_string('nocomments', 'moodle'));
} else {
    $tabl->sortable(true, 'date', SORT_DESC);
    $tabl->no_sorting('action');

    $tabl->define_baseurl(new moodle_url($url, $arr));
    $tabl->set_attribute('class', 'admintable generaltable');
    $tabl->setup();
    $tablrows = [];

    $link = new moodle_url($url, ['course' => $courseid, 'action' => 'delete', 'sesskey' => sesskey()]);
    foreach ($comments as $c) {
        $action = html_writer::link(new moodle_url($link, ['commentid' => $c->id]), get_string('delete'));
        $tabl->add_data([$c->time, $c->fullname, $c->content, $action]);
    }
    $tabl->print_html();
}


echo $OUTPUT->footer($course);

// Trigger a report viewed event.
$event = \report_comments\event\report_viewed::create(['context' => $context]);
$event->trigger();
