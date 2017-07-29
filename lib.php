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
 * Library functions.
 *
 * @package    report
 * @subpackage comments
 * @copyright  2017 iplusacademy.org
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
            $url = new moodle_url('/report/comments/index.php', ['course' => $course->id]);
            $navigation->add(get_string('comments'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
        }
        if ($CFG->enablenotes) {
            $url = new moodle_url('/notes/index.php', ['course' => $course->id]);
            $str = get_string('notes', 'notes');
            $navigation->add($str, $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
        }
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
    if (report_comments_can_access_user_report($user, $course)) {
        $url = new moodle_url('/report/comments/index.php', ['course' => $course->id, 'id' => $user->id]);
        $navigation->add(get_string('comments'), $url);
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
    global $CFG, $USER;

    if ($CFG->usecomments) {
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
    }
    return false;
}
