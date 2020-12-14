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
 * Local functions.
 *
 * @package   report_comments
 * @copyright 2017 iplusacademy.org
 * @author    Renaat Debleu (www.eWallah.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Get user comments
 *
 * @param int $userid
 * @param string $sort
 * @param int $sortdir
 * @return array
 */
function report_comments_getusercomments($userid, $sort = 'date', $sortdir = 3):array {
    global $CFG, $DB;
    $comments = [];
    if ($user = $DB->get_record('user', ['id' => $userid], 'firstname, lastname')) {
        $url = new moodle_url('/user/view.php', ['id' => $userid]);
        $fullname = html_writer::link($url, $user->firstname . ' ' . $user->lastname);
        $format = ['overflowdiv' => true];
        $strftimeformat = get_string('strftimerecentfull', 'langconfig');

        if ($comments = $DB->get_records('comments', ['userid' => $userid], 'timecreated DESC')) {
            foreach ($comments as $comment) {
                $context = context::instance_by_id($comment->contextid);
                $comment->fullname = $fullname;
                $comment->time = userdate($comment->timecreated, $strftimeformat);
                $contexturl = '';
                switch ($context->contextlevel) {
                    case CONTEXT_MODULE:
                        $cm = get_coursemodule_from_id('', $context->instanceid);
                        $course = get_course($cm->course);
                        $contexturl = course_get_url($course);
                        $comment->fullname = html_writer::link($contexturl, $course->fullname);
                        $base = core_component::get_component_directory('mod_' . $cm->modname);
                        if (file_exists("$base/view.php")) {
                            $base = substr($base, strlen($CFG->dirroot));
                            $contexturl = new moodle_url("$base/view.php", ['id' => $cm->id]);
                        }
                        break;
                    case CONTEXT_COURSE:
                        $course = get_course($context->instanceid);
                        $contexturl = course_get_url($course);
                        $comment->fullname = html_writer::link($contexturl, $course->fullname);
                        break;
                    default:
                        debugging('Default context: ' . $context->instanceid);
                }
                $comment->content = html_writer::link($contexturl, format_text($comment->content, $comment->format, $format));
            }
        }
    }
    return sortcomments($comments, $sort, $sortdir);
}

/**
 * Get course comments
 *
 * @param int $courseid
 * @param string $sort
 * @param int $sortdir
 * @return array
 */
function report_comments_getcoursecomments($courseid, $sort = 'date', $sortdir = 3):array {
    global $CFG, $DB;
    $format = ['overflowdiv' => true];
    $strftimeformat = get_string('strftimerecentfull', 'langconfig');
    $context = context_course::instance($courseid);
    $comments = $DB->get_records('comments', ['contextid' => $context->id]);
    foreach ($comments as $comment) {
        $user = $DB->get_record('user', ['id' => $comment->userid], 'firstname, lastname');
        $url = new moodle_url('/report/comments/index.php', ['id' => $comment->userid, 'course' => $courseid]);
        $comment->fullname = html_writer::link($url, $user->firstname . ' ' . $user->lastname);
        $comment->time = userdate($comment->timecreated, $strftimeformat);
        $url = course_get_url($courseid);
        $comment->content = html_writer::link($url, format_text($comment->content, $comment->format, $format));
    }

    $rawmods = get_course_mods($courseid);
    foreach ($rawmods as $mod) {
        if ($context = $DB->get_record('context', ['instanceid' => $mod->id, 'contextlevel' => CONTEXT_MODULE])) {
            if ($modcomments = $DB->get_records('comments', ['contextid' => $context->id])) {
                foreach ($modcomments as $comment) {
                    $user = $DB->get_record('user', ['id' => $comment->userid], 'firstname, lastname');
                    $url = new moodle_url('/report/comments/index.php', ['course' => $courseid, 'id' => $comment->userid]);
                    $comment->fullname = html_writer::link($url, $user->firstname . ' ' . $user->lastname);
                    $comment->time = userdate($comment->timecreated, $strftimeformat);
                    $base = core_component::get_component_directory('mod_' . $mod->modname);
                    if (file_exists("$base/view.php")) {
                        $base = substr($base, strlen($CFG->dirroot));
                        $url = new moodle_url("$base/view.php", ['id' => $mod->id]);
                        $str = format_text($comment->content, $comment->format, $format);
                        $comment->content = html_writer::link($url, $str);
                    } else {
                        $comment->content = format_text($comment->content, $comment->format, $format);
                    }
                    $comments[] = $comment;
                }
            }
        }
    }
    return sortcomments($comments, $sort, $sortdir);
}

/**
 * Sort comments
 *
 * @param array $comments
 * @param string $sort
 * @param int $sortdir
 * @return array
 */
function sortcomments($comments, $sort, $sortdir = 3) {
    if ($sort == 'date') {
        usort($comments, $sortdir == 4 ? 'cmpdate' : 'cmpdaterev');
    } else if ($sort == 'content') {
        usort($comments, $sortdir == 4 ? 'cmpcontent' : 'cmpcontentrev');
    } else if ($sort == 'author') {
        usort($comments, $sortdir == 4 ? 'cmpid' : 'cmpidrev');
    }
    return $comments;
}

/**
 * Compare date reverse
 *
 * @param stdClass $a
 * @param stdClass $b
 * @return bool
 */
function cmpdate($a, $b) {
    return ($a->timecreated < $b->timecreated) ? -1 : 1;
}

/**
 * Compare date reverse
 *
 * @param stdClass $a
 * @param stdClass $b
 * @return bool
 */
function cmpdaterev($a, $b) {
    return ($a->timecreated < $b->timecreated) ? 1 : -1;
}

/**
 * Compare id
 *
 * @param stdClass $a
 * @param stdClass $b
 * @return bool
 */
function cmpid($a, $b) {
    return ($a->id < $b->id) ? -1 : 1;
}

/**
 * Compare id reverse
 *
 * @param stdClass $a
 * @param stdClass $b
 * @return bool
 */
function cmpidrev($a, $b) {
    return ($a->id < $b->id) ? 1 : -1;
}

/**
 * Compare content
 *
 * @param string $a
 * @param string $b
 * @return bool
 */
function cmpcontent($a, $b) {
    return ($a->content < $b->content) ? -1 : 1;
}

/**
 * Compare content reverse
 *
 * @param string $a
 * @param string $b
 * @return bool
 */
function cmpcontentrev($a, $b) {
    return ($a->content < $b->content) ? 1 : -1;
}
