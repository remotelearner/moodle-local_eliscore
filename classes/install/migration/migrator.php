<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    local_elisprogram
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

namespace local_eliscore\install\migration;

/**
 * Migrates components from one component to another.
 */
class migrator {

    /** @var string The old component name */
    protected $oldcomponent = null;

    /** @var string The new component name */
    protected $newcomponent = null;

    /** @var string Name of a function that will run old upgrade steps. Must take version int as parameter. */
    protected $upgradestepfuncname = null;

    /** @var array An array of table names to change. In the form $oldtablename => $newtablename */
    protected $tablechanges = null;

    /** @var bool|null Whether the old component is installed or not. Null if it has not yet been determined. */
    protected $oldcomponentinstalled = null;

    /** @var int|null The currently installed version of the old component, or null if it has not yet been determined */
    protected $oldversion = null;

    /**
     * Constructor.
     *
     * @param string $oldcomponent The old component name.
     * @param string $newcomponent The new component name.
     * @param string $upgradestepfuncname Name of a function that will run old upgrade steps. Must take version int as parameter.
     * @param array $tablechanges An array of table names to change. In the form $oldtablename => $newtablename.
     */
    public function __construct($oldcomponent = '', $newcomponent = '', $upgradestepfuncname = '', array $tablechanges = array()) {
        $this->oldcomponent = $oldcomponent;
        $this->newcomponent = $newcomponent;

        if (!empty($upgradestepfuncname)) {
            $this->upgradestepfuncname = $upgradestepfuncname;
        }

        if (!empty($tablechanges) && is_array($tablechanges)) {
            $this->tablechanges = $tablechanges;
        }
    }

    /**
     * Runs all old upgrade steps based on currently installed version.
     */
    public function run_old_upgrade_steps_if_necessary() {
        global $DB;

        // If we have not yet determined if the old component is installed, do that now.
        if ($this->oldcomponentinstalled === null) {
            $this->old_component_installed();
        }

        // Validate upgrade func name.
        $validfuncname = (!empty($this->upgradestepfuncname) && is_callable($this->upgradestepfuncname)) ? true : false;

        // Ensure old component is installed.
        $oldcomponentinstalled = ($this->oldcomponentinstalled === true && !empty($this->oldversion)) ?  true : false;

        // Run upgrade function if everything checks out.
        if ($validfuncname === true && $oldcomponentinstalled === true) {
            call_user_func($this->upgradestepfuncname, $this->oldversion);
        }
    }

    /**
     * Rename tables as needed.
     */
    public function migrate_tables() {
        global $DB;
        $dbman = $DB->get_manager();
        foreach ($this->tablechanges as $oldname => $newname) {
            if ($dbman->table_exists($oldname)) {
                if ($dbman->table_exists($newname)) {
                    $newtable = new \xmldb_table($newname);
                    $dbman->drop_table($newtable);
                }
                $oldtable = new \xmldb_table($oldname);
                $dbman->rename_table($oldtable, $newname);
            }
        }

        // Check for a few old unused tables and just drop them if they exist.
        $removetables = array('crlm_config', 'crlm_usercluster');
        foreach ($removetables as $removetable) {
            $oldtable = new \xmldb_table($removetable);
            if ($dbman->table_exists($oldtable)) {
                $dbman->drop_table($oldtable);
            }
        }
    }

    /**
     * Migrates all settings in config_plugins from the old component to the new component.
     */
    public function migrate_settings() {
        global $DB;

        $oldconfig = $DB->get_recordset('config_plugins', array('plugin' => $this->oldcomponent));
        foreach ($oldconfig as $oldconfigrec) {
            // We don't want version records.
            if ($oldconfigrec->name === 'version') {
                continue;
            }

            // Check if a setting already exists for this name, and delete if it does.
            $newrec = $DB->get_record('config_plugins', array('plugin' => $this->newcomponent, 'name' => $oldconfigrec->name));
            if (!empty($newrec)) {
                $DB->delete_records('config_plugins', array('id' => $newrec->id));
            }
            $updatedrec = new \stdClass;
            $updatedrec->id = $oldconfigrec->id;
            $updatedrec->plugin = $this->newcomponent;
            $DB->update_record('config_plugins', $updatedrec);
        }

        unset_all_config_for_plugin($this->oldcomponent);
    }

    /**
     * Migrates all custom language strings from old component to the new component.
     * @return bool true on success, false otherwise
     */
    public function migrate_language_strings() {
        global $CFG;
        if ($this->oldcomponent == $this->newcomponent) {
            return true;
        }
        $result = true;
        $langstringdir = $CFG->dataroot.'/lang/';
        foreach (glob("{$langstringdir}??_local") as $langdir) {
            $subdirparts = explode('_', substr($langdir, strlen($langstringdir)));
            $langcode = trim($subdirparts[0], '/');
            $oldfile = "{$langdir}/{$this->oldcomponent}.php";
            if (file_exists($oldfile)) {
                $newdir = "{$langstringdir}/{$langcode}_local";
                if ((!file_exists($newdir) && !@mkdir($newdir)) || !@link($oldfile, "{$newdir}/{$this->newcomponent}.php")) {
                    $result = false;
                    continue;
                }
                @unlink($oldfile);
            }
        }
        return $result;
    }

    /**
     * Determines whether the old component is installed.
     *
     * @return bool Whether the old component is installed (true) or not (false)
     */
    public function old_component_installed() {
        global $DB;
        $oldversion = (int)$DB->get_field('config_plugins', 'value', array('plugin' => $this->oldcomponent, 'name' => 'version'));
        if (!empty($oldversion)) {
            $this->oldcomponentinstalled = true;
            $this->oldversion = $oldversion;
        } else {
            $this->oldcomponentinstalled = false;
        }
        return $this->oldcomponentinstalled;
    }

    /**
     * Uninstall the old plugin.
     */
    public function uninstall_old_plugin() {
        global $CFG, $DB;

        // Uninstall old component.
        require_once($CFG->dirroot.'/lib/weblib.php');
        require_once($CFG->dirroot.'/lib/classes/plugin_manager.php');
        $pluginman = \core_plugin_manager::instance();
        $pluginman->uninstall_plugin($this->oldcomponent, new \null_progress_trace());
        // Delete event handlers for old component not removed by Moodle
        $DB->delete_records('events_handlers', array('component' => $this->oldcomponent));
    }

    /**
     * Perform all migrations.
     */
    public function migrate() {
        if (!empty($this->upgradestepfuncname)) {
            $this->run_old_upgrade_steps_if_necessary();
        }

        if (!empty($this->tablechanges)) {
            $this->migrate_tables();
        }

        $this->migrate_settings();
        $this->migrate_language_strings();
        $this->uninstall_old_plugin();
    }
}
