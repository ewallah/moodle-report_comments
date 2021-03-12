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
 * Comments table per user.
 *
 * @package    report_comments
 * @copyright  2017 iplusacademy.org
 * @author     Renaat Debleu <info@eWallah.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_comments;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/tablelib.php');

/**
 * Extends table_sql to provide a table of user comments
 *
 * @package    report_comments
 * @copyright  2017 iplusacademy.org
 * @author     Renaat Debleu <info@eWallah.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usertable extends \table_sql {

    /** @var string Time format. */
    protected $timeformat;
    /** @var int Counter. */
    protected $counter;
    /** @var bool Download. */
    public $download;

    /**
     * Overridden constructor.
     *
     * @param int $userid The affected user
     * @param bool $download quickgrading Is this table wrapped in a quickgrading form?
     */
    public function __construct($userid, $download = false) {
        parent::__construct('comments');
        $this->download = $download;
        $arr = ['course' => 1, 'id' => $userid];
        $this->define_baseurl(new \moodle_url('/report/comments/index.php', $arr));
        $this->timeformat = get_string('strftimerecentfull', 'langconfig');
        $this->counter = 1;
        $this->set_sql('id, timecreated, userid, content, format, contextid, component, commentarea, itemid',
           '{comments}', 'userid = :userid', ['userid' => $userid]);
        $this->set_count_sql('SELECT COUNT(id) FROM {comments} WHERE userid = :userid', ['userid' => $userid]);
        if ($download) {
            $this->define_columns(['id', 'timecreated', 'userid', 'content']);
            $this->define_headers(['', get_string('date'), get_string('user'), get_string('content')]);
        } else {
            $this->define_columns(['id', 'timecreated', 'userid', 'content', 'action']);
            $this->define_headers(['', get_string('date'), get_string('user'), get_string('content'), get_string('action')]);
        }
        $this->column_nosort[] = 'action';
        $this->collapsible(false);
        $this->showdownloadbuttonsat = [TABLE_P_BOTTOM];
    }

    /**
     * Id columnn.
     * Used to check capabilities.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_id(\stdClass $row) {
        global $CFG;
        $context = \context::instance_by_id($row->contextid, IGNORE_MISSING);
        if ($context) {
            $row->contexturl = false;
            if (has_capability('report/comments:view', $context)) {
                switch ($context->contextlevel) {
                    case CONTEXT_MODULE:
                        $cm = get_coursemodule_from_id('', $context->instanceid);
                        $base = \core_component::get_component_directory('mod_' . $cm->modname);
                        if (file_exists("$base/view.php")) {
                            $base = substr($base, strlen($CFG->dirroot));
                            $row->contexturl = new \moodle_url("$base/view.php", ['id' => $cm->id]);
                        }
                        break;
                    case CONTEXT_COURSE:
                        $row->contexturl = course_get_url(get_course($context->instanceid));
                        break;
                    default:
                        throw new \comment_exception('invalid context');
                }
            }
        }
        if (!$row->contexturl) {
            $row->content = '***';
            $row->context = 0;
        }
        return $this->counter++;
    }

    /**
     * Time comment created columnn.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_timecreated(\stdClass $row) {
        if ($row->contexturl) {
            return \html_writer::link($row->contexturl, userdate($row->timecreated, $this->timeformat));
        }
        return userdate($row->timecreated, $this->timeformat);
    }

    /**
     * Comment text columnn.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_content(\stdClass $row) {
        return format_text($row->content, $row->format);
    }

    /**
     * User picture columnn.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_userid(\stdClass $row) {
        global $DB, $OUTPUT;
        $s = '';
        if ($row->contexturl && $user = $DB->get_record('user', ['id' => $row->userid])) {
            // TODO: \core\user_fields::for_userpic()->get_required_fields());.
            $s = ($this->download) ? fullname($user) : $OUTPUT->user_picture($user);
        }
        return $s;
    }

    /**
     * Columnn with buttons.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_action(\stdClass $row) {
        $s = '';
        if (!$this->is_downloading() and $row->contexturl) {
            $arr = $this->baseurl->params();
            $arr['action'] = 'delete';
            $arr['sesskey'] = sesskey();
            $url = new \moodle_url($this->baseurl->out_omit_querystring(), $arr);
            $s = \html_writer::empty_tag('input', ['type' => 'submit', 'formaction' => $url, 'value' => get_string('delete')]);
        }
        return $s;
    }
}
