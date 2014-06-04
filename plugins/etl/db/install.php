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
 * @package    eliscore_etl
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Install for eliscore_etl
 * @return boolean
 */
function xmldb_eliscore_etl_install() {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    // Run upgrade steps from old plugin

    // Convert old tables to new
    static $tablemap = array(
        'etl_user_activity' => 'eliscore_etl_useractivity',
        'etl_user_module_activity' => 'eliscore_etl_modactivity'
    );
    foreach ($tablemap as $oldtable => $newtable) {
        $oldtableobj = new xmldb_table($oldtable);
        if ($dbman->table_exists($oldtableobj)) {
            $newtableobj = new xmldb_table($newtable);
            $dbman->drop_table($newtableobj);
            $dbman->rename_table($oldtableobj, $newtable);
        }
    }

    // Copy any settings from old plugin
    $oldconfig = get_config('eliscoreplugins_user_activity');
    foreach ($oldconfig as $name => $value) {
        // We don't want version records.
        if ($name === 'version') {
            continue;
        }
        set_config($name, $value, 'eliscore_etl');
    }
    unset_all_config_for_plugin('eliscoreplugins_user_activity');

    // Ensure ELIS scheduled tasks is initialized.
    require_once($CFG->dirroot.'/local/eliscore/lib/tasklib.php');
    $DB->delete_records('local_eliscore_sched_tasks', array('plugin' => 'eliscoreplugins_user_activity'));
    elis_tasks_update_definition('eliscore_etl');

    return true;
}
