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
 * @package    report_comments
 * @copyright  2017 iplusacademy.org
 * @author     Renaat Debleu (www.eWallah.net)
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
            $str = get_string('comments');
            $url = new \moodle_url('/report/comments/index.php', ['course' => $course->id]);
            $navigation->add($str, $url, navigation_node::TYPE_SETTING, null, null, new \pix_icon('i/report', $str));
        }
        if ($CFG->enablenotes) {
            $url = new \moodle_url('/notes/index.php', ['course' => $course->id]);
            $str = get_string('notes', 'notes');
            $navigation->add($str, $url, navigation_node::TYPE_SETTING, null, null, new \pix_icon('i/report', $str));
        }
    }
}

/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 *
 * @return bool
 */
function report_comments_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    global $CFG;
    if (isguestuser($user) or !$iscurrentuser) {
        return false;
    }
    $context = context_system::instance();
    $return = false;
    if ($CFG->usecomments && has_capability('report/comments:view', $context)) {
        $url = new \moodle_url('/report/comments/index.php', ['course' => 1, 'id' => $user->id]);
        $node = new \core_user\output\myprofile\node('reports', 'comments', get_string('comments'), null, $url);
        $tree->add_node($node);
        $return = true;
    }
    return $return;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 * @return array
 */
function report_comments_page_type_list($pagetype, $parentcontext, $currentcontext) {
    return ['*' => new \lang_string('page-x', 'pagetype'), 'report-*' => new \lang_string('page-report-x', 'pagetype')];
}