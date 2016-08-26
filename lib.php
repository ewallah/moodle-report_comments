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
 * Version details.
 *
 * @package    report
 * @subpackage comments
 * @copyright  2014 iplusacademy.org
 * @devolopper Renaat Debleu (www.eWallah.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * This function extends the navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_comments_extend_navigation_course($navigation, $course, $context) {
    global $CFG;
    if (has_capability('report/comments:view', $context)) {
        if ($CFG->usecomments) {
            $url = new moodle_url('/report/comments/index.php', array('course' => $course->id));
            $navigation->add(get_string('comments'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
        }
        $url = new moodle_url('/notes/index.php', array('course'=>$course->id));
        $navigation->add(get_string('notes', 'notes'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));

    }
}

/**
 * This function extends the course navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $user
 * @param stdClass $course The course to object for the report
 */
function report_comments_extend_navigation_user($navigation, $user, $course) {
    global $CFG;
    $context = context_course::instance($course->id);
    if (has_capability('report/comments:view', $context)) {
        if ($CFG->usecomments) {
            $url = new moodle_url('/report/comments/index.php', array('course' => $course->id, 'id' => $user->id));
            $navigation->add(get_string('comments'), $url);
        }
    }
}

/**
 * Is current user allowed to access this report
 *
 * @private defined in lib.php for performance reasons
 *
 * @param stdClass $user
 * @param stdClass $course
 * @return bool
 */
function report_comments_can_access_user_report($user, $course) {
    global $USER, $CFG;

    if (empty($CFG->enablecomments)) {
        return false;
    }

    if ($course->id != SITEID and !$course->enablecomments) {
        return false;
    }

    $coursecontext = context_course::instance($course->id);
    if (has_capability('report/comments:view', $coursecontext)) {
        return true;
    }

    $personalcontext = context_user::instance($user->id);
    if (has_capability('moodle/user:viewuseractivitiesreport', $personalcontext)) {
        if ($course->showreports and (is_viewing($coursecontext, $user) or is_enrolled($coursecontext, $user))) {
            return true;
        }

    } else if ($user->id == $USER->id) {
        if ($course->showreports and (is_viewing($coursecontext, $USER) or is_enrolled($coursecontext, $USER))) {
            return true;
        }
    }
    return false;
}

function report_comments_getusercomments($userid, $sort = 'date') {
    global $CFG, $DB;
    $user = $DB->get_record('user', array('id' => $userid), 'firstname, lastname');
    $url = new moodle_url('/user/view.php', array('id' => $userid));
    $fullname = html_writer::link($url, $user->firstname . ' ' . $user->lastname);
    $formatoptions = array('overflowdiv' => true);
    $strftimeformat = get_string('strftimerecentfull', 'langconfig');

    $comments = $DB->get_records('comments', array('userid' => $userid), 'timecreated DESC');
    foreach ($comments as $comment) {
        $comment->fullname = $fullname;
        $comment->time = userdate($comment->timecreated, $strftimeformat);
        $context = context::instance_by_id($comment->contextid, IGNORE_MISSING);
        if (!$context) {
            continue;
        }
        $contexturl = '';
        switch ($context->contextlevel) {
            case CONTEXT_BLOCK:
                debugging('Block: ' . $context->instanceid);
                break;
            case CONTEXT_MODULE:
                $cm = get_coursemodule_from_id('', $context->instanceid);
                $course = get_course($cm->course);
                $contexturl = course_get_url($course);
                $comment->fullname = html_writer::link($contexturl, $course->fullname);
                $base = core_component::get_component_directory('mod_' . $cm->modname);
                if (file_exists("$base/view.php")) {
                    $base = substr($base, strlen($CFG->dirroot));
                    $contexturl = new moodle_url("$base/view.php", array('id' => $cm->id));
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
        $comment->content = html_writer::link($contexturl, format_text($comment->content, $comment->format, $formatoptions));
    }
    return $comments;
}

function report_comments_getcoursecomments($courseid, $sort = 'date') {
    global $CFG, $DB;
    $formatoptions = array('overflowdiv' => true);
    $strftimeformat = get_string('strftimerecentfull', 'langconfig');
    $context = context_course::instance($courseid);
    $comments = $DB->get_records('comments', array('contextid' => $context->id));
    foreach ($comments as $comment) {
        $user = $DB->get_record('user', array('id' => $comment->userid), 'firstname, lastname');
        $url = new moodle_url('/report/comments/index.php', array('id' => $comment->userid, 'course' => $courseid));
        $comment->fullname = html_writer::link($url, $user->firstname . ' ' . $user->lastname);
        $comment->time = userdate($comment->timecreated, $strftimeformat);
        $url = course_get_url($courseid);
        $comment->content = html_writer::link($url, format_text($comment->content, $comment->format, $formatoptions));
    }

    $rawmods = get_course_mods($courseid);
    foreach ($rawmods as $mod) {
        if ($context = $DB->get_record('context', array('instanceid' => $mod->id, 'contextlevel' => CONTEXT_MODULE))) {
            if ($modcomments = $DB->get_records('comments', array('contextid' => $context->id))) {
                foreach ($modcomments as $comment) {
                    $user = $DB->get_record('user', array('id' => $comment->userid), 'firstname, lastname');
                    $url = new moodle_url('/report/comments/index.php', array('course' => $courseid, 'id' => $comment->userid));
                    $comment->fullname = html_writer::link($url, $user->firstname . ' ' . $user->lastname);
                    $comment->time = userdate($comment->timecreated, $strftimeformat);
                    $base = core_component::get_component_directory('mod_' . $mod->modname);
                    if (file_exists("$base/view.php")) {
                        $base = substr($base, strlen($CFG->dirroot));
                        $url = new moodle_url("$base/view.php", array('id' => $mod->id));
                        $comment->content = html_writer::link($url,
                            format_text($comment->content, $comment->format, $formatoptions));
                    } else {
                        $comment->content = format_text($comment->content, $comment->format, $formatoptions);
                    }

                    $comments[] = $comment;
                }
            }
        }
    }
    switch ($sort) {
        case 'date':
            usort($comments, "cmpdate");
            break;
        case 'content':
            usort($comments, "cmpcontent");
            break;
        case 'author':
            usort($comments, "cmpid");
            break;
    }
    return $comments;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 * @return array
 */
function report_comments_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $array = array(
        '*'                     => get_string('page-x', 'pagetype'),
        'report-*'              => get_string('page-report-x', 'pagetype'),
        'report-comments-*'     => get_string('page-report-comments-x',  'report_comments'),
        'report-comments-index' => get_string('page-report-comments-index',  'report_comments'),
        'report-comments-user'  => get_string('page-report-comments-user',  'report_comments')
    );
    return $array;
}

function cmpdate($a, $b) {
    if ($a->timecreated == $b->timecreated) {
        return 0;
    }
    return ($a->timecreated < $b->timecreated) ? -1 : 1;
}
function cmpdaterev($a, $b) {
    if ($a->timecreated == $b->timecreated) {
        return 0;
    }
    return ($a->timecreated < $b->timecreated) ? 1 : -1;
}

function cmpid($a, $b) {
    if ($a->id == $b->id) {
        return 0;
    }
    return ($a->id < $b->id) ? -1 : 1;
}

function cmpidrev($a, $b) {
    if ($a->id == $b->id) {
        return 0;
    }
    return ($a->id < $b->id) ? 1 : -1;
}

function cmpcontent($a, $b) {
    if ($a->content == $b->content) {
        return 0;
    }
    return ($a->content < $b->content) ? -1 : 1;
}

function cmpcontentrev($a, $b) {
    if ($a->content == $b->content) {
        return 0;
    }
    return ($a->content < $b->content) ? 1 : -1;
}
