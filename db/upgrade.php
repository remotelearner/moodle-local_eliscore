<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_eliscore_upgrade($oldversion = 0) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();
    $result = true;

    if ($result && $oldversion < 2014082504) {
        // Update elis scheduled tasks table with new 'period' column.
        $table = new xmldb_table('local_eliscore_sched_tasks');
        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('period', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'blocked');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Update DataHub/elis schedule tasks tables period spec.
        $table = new xmldb_table('local_datahub_schedule');
        if ($dbman->table_exists($table)) {
            $dhjobs = $DB->get_recordset('local_datahub_schedule');
            if ($dhjobs && $dhjobs->valid()) {
                foreach ($dhjobs as $dhjob) {
                    $jobdata = unserialize($dhjob->config);
                    // Since datahub will upgrade before eliscore must update tasks here.
                    if ($estrec = $DB->get_record('local_eliscore_sched_tasks', array('taskname' => 'ipjob_'.$dhjob->id))) {
                        if ($jobdata['period'] != $estrec->period) {
                            $estrec->period = $jobdata['period'];
                            $DB->update_record('local_eliscore_sched_tasks', $estrec);
                        }
                    }
                }
                $dhjobs->close();
            }
        }
        upgrade_plugin_savepoint(true, 2014082504, 'local', 'eliscore');
    }

    return $result;
}
