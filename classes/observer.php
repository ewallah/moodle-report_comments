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
 * Event observer.
 *
 * @package    report
 * @subpackage comments
 * @copyright  2017 iplusacademy.org
 * @devolopper Renaat Debleu (www.eWallah.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

class report_comments_observer {

    public static function commentcreated(\core\event\base $comment) {
        global $CFG, $DB, $USER;
        if (!empty($comment)) {
            if (!empty($comment->courseid)) {
                if ($DB->record_exists('course', ['id' => $comment->courseid])) {
                    $context = context_course::instance($comment->courseid);
                    $role = $DB->get_record('role', ['shortname' => 'editingteacher']);
                    $supportuser = core_user::get_support_user();
                    if ($teachers = get_role_users($role->id, $context)) {
                        $sendtext = $CFG->wwwroot . ': '. $USER->firstname . ' ' . $USER->lastname . ' made a comment.';
                        if ($content = $DB->get_field('comments', 'content', ['id' => $comment->objectid])) {
                            $message = new \core\message\message();
                            $message->component = 'report_comments';
                            $message->name = 'newcomment';
                            $message->courseid = $comment->courseid;
                            $message->userfrom = $supportuser;
                            $message->subject = $sendtext;
                            $message->fullmessage = $sendtext;
                            $message->fullmessageformat = FORMAT_MARKDOWN;
                            $message->fullmessagehtml = stripcslashes($content);
                            $message->smallmessage = $sendtext;
                            $message->notification = '1';
                            $message->contexturl = new \moodle_url('/course/view.php', ['id' => $comment->courseid]);
                            $message->contexturlname = $sendtext;
                            $message->set_additional_content('email', ['*' => ['header' => $CFG->wwwroot, 'footer' => ' ']]);
                            foreach ($teachers as $admin) {
                                $teacher = $DB->get_record('user', ['id' => $admin->id]);
                                $message->userto = $teacher;
                                message_send($message);
                            }
                        }
                    }
                } else {
                    debugging('course not found');
                }
            }
        } else {
            debugging('empty comment');
        }
    }
}
