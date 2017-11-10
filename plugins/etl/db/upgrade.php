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
 * Upgrade for eliscore_etl
 * @return boolean
 */
function xmldb_eliscore_etl_upgrade($oldversion) {
    global $CFG, $DB;
    $result = true;
    $dbman = $DB->get_manager();

    if ($result && $oldversion < 2015102201) {
        if (get_config('eliscore_etl', 'upgrade_reset') === false) {
            // ELIS-9209: Reset ETL to just after it stopped working.
            $status = 'no-action';
            $lastgood = $DB->get_field_select('eliscore_etl_useractivity', 'MAX(id)', 'courseid > 1');
            if ($lastgood) {
                $rec = $DB->get_record('eliscore_etl_useractivity', ['id' => $lastgood]);
                $lasttime = $rec->hour + $rec->duration;
                $lastetl = get_config('eliscore_etl', 'last_run');
            }
            if (!$lastgood) {
                $status = 'restarted';
                $DB->execute('TRUNCATE TABLE {eliscore_etl_useractivity}');
                $DB->execute('TRUNCATE TABLE {eliscore_etl_modactivity}');
                set_config('last_run', '0', 'eliscore_etl');
                set_config('state', '0', 'eliscore_etl');
            } else if (!$lastetl || $lastetl > $lasttime) {
                $status = 'reset';
                $DB->execute('DELETE FROM {eliscore_etl_useractivity} WHERE id > ?', [$lastgood]);
                $DB->execute('DELETE FROM {eliscore_etl_modactivity} WHERE hour > ?', [$lasttime]);
                set_config('last_run', $lasttime, 'eliscore_etl');
                set_config('state', '0', 'eliscore_etl');
            }
            set_config('upgrade_reset', $status, 'eliscore_etl');
        }
        upgrade_plugin_savepoint($result, 2015102201, 'eliscore', 'etl');
    }

    if ($result && $oldversion < 2016052301) {
        // ELIS-9478: avoid duration overflow.
        $tables = [new xmldb_table('eliscore_etl_useractivity'),
                new xmldb_table('eliscore_etl_modactivity')];
        foreach ($tables as $table) {
            if ($dbman->table_exists($table)) {
                $field = new xmldb_field('duration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, 0, 0, 'hour');
                $dbman->change_field_precision($table, $field);
            } else {
                $result = false;
            }
        }
        upgrade_plugin_savepoint($result, 2016052301, 'eliscore', 'etl');
    }

    // Ensure ELIS scheduled tasks is initialized.
    require_once($CFG->dirroot.'/local/eliscore/lib/tasklib.php');
    elis_tasks_update_definition('eliscore_etl');

    return $result;
}
