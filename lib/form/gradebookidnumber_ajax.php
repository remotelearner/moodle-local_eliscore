<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    local_eliscore
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

require_once('../../lib/setup.php');

$textelemid = required_param('textelemid', PARAM_ACTION);
$course = optional_param('course', null, PARAM_ACTION);
$mode = optional_param('mode', 'course', PARAM_ACTION);

if ($mode == 'course') {
    require_login();
    $PAGE->set_url('/local/eliscore/lib/form/gradebookidnumber_ajax.php', array('course' => $course, 'mode' => 'course'));
    $lockcourse = optional_param('lockcourse', false, PARAM_BOOL);
    if ($lockcourse) {
        if (empty($course)) {
            print_string('nocourseselected', 'local_eliscore');
        } else {
            $c = $DB->get_record('course', array('id' => $course));
            echo s($c->fullname) . ' (' . s($c->shortname) . ')';
        }
        die;
    }
    echo html_writer::start_tag('select');
    $attributes = array('value' => '');
    if (empty($course)) {
        $attributes['selected'] = 1;
    }
    echo html_writer::tag('option', get_string('select'), $attributes);

    $courses = $DB->get_recordset('course', null, 'fullname');
    foreach ($courses as $c) {
        if ($c->id == SITEID) {
            continue;
        }
        $attributes = array('value' => $c->id);
        if ($c->id == $course) {
            $attributes['selected'] = 1;
        }
        echo html_writer::tag('option', s($c->fullname) . ' (' . s($c->shortname) . ')', $attributes);
    }
    echo html_writer::end_tag('select');
    die;
} else if (empty($course)) {
    print_string('selectacourse', 'backup');
    die;
}

require_login($course);
$PAGE->set_url('/local/eliscore/lib/form/gradebookidnumber_ajax.php', array('course' => $course, 'mode' => $mode));

function print_item($item) {
    global $textelemid;

    $name = empty($item->fullname) ? $item->itemname : $item->fullname;
    if (empty($item->idnumber)) {
        $item->idnumber = get_string('noidnumber', 'local_eliscore');
    } else {
        $link = true;
    }

    echo html_writer::start_tag('li');
    if (!empty($link)) {
        echo html_writer::start_tag('a', array('onclick' => 'return ' . js_writer::function_call('M.local_eliscore.set_value', array($textelemid, $item->idnumber)),
                                               'href' => ''));
    }
    echo html_writer::empty_tag('img', array('src' => $item->icon));
    echo ' ' . s($name) . ' (' . s($item->idnumber) . ')';
    if (!empty($link)) {
        echo html_writer::end_tag('a');
    }
    echo html_writer::end_tag('li');
}

if ($mode != 'gradebook') {
    $items = $DB->get_recordset('grade_items', array('courseid' => $course,
                                                     'itemtype' => 'mod'),
                                'itemname');

    if ($items->valid()) {
        echo html_writer::start_tag('ul');
        foreach ($items as $item) {
            $item->icon = $OUTPUT->pix_url('icon', 'mod_'.$item->itemmodule);
            print_item($item);
        }
        echo html_writer::end_tag('ul');
    } else {
        print_string('noactivities', 'local_eliscore');
    }
} else {
    $sql = "SELECT gi.*, gc.fullname
              FROM {grade_items} gi
         LEFT JOIN {grade_categories} gc ON gi.iteminstance = gc.id
             WHERE gi.courseid = ?
               AND gi.itemtype NOT IN ('mod','course')
          ORDER BY COALESCE(gc.fullname, gi.itemname)";
    $items = $DB->get_recordset_sql($sql, array($course));

    if ($items->valid()) {
        echo html_writer::start_tag('ul');
        foreach ($items as $item) {
            switch ($item->itemtype) {
                case 'category':
                    $item->icon = $OUTPUT->pix_url('f/folder');
                    break;
                case 'manual':
                    $item->icon = $OUTPUT->pix_url('t/manual_item');
                    break;
                default:
                    $item->icon = $OUTPUT->pix_url('f/unknown');
            }
            print_item($item);
        }
        echo html_writer::end_tag('ul');
    } else {
        print_string('nogradeitems', 'local_eliscore');
    }
}
