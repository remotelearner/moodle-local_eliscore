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
 * @package    local_eliscore
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

require_once(dirname(__FILE__) . '/../../../config.php');
global $CFG;
require_once($CFG->dirroot.'/local/eliscore/lib.php');

/**
 * Global ELIS management object.
 */
class elis {
    /**
     * The ELIS DB version
     */
    public static $version;

    /**
     * The ELIS human-readable release
     */
    public static $release;

    /**
     * The base directory for the ELIS code.
     */
    public static $basedir;

    /**
     * Return the full path name for a ELIS file.
     */
    public static function file($file) {
        return self::$basedir . '/' . $file;
    }

    /**
     * Return the full path name for a file in a component.
     */
    public static function component_file($component, $file) {
        return self::file("{$component}/{$file}");
    }

    /**
     * Return the full path name for a file in a plugin.
     */
    public static function plugin_file($plugin, $file) {
        list($plugintype, $name) = core_component::normalize_component($plugin);
        return core_component::get_plugin_directory($plugintype, $name)."/{$file}";
    }

    /**
     * The base directory for the ELIS libraries.
     */
    public static $libdir;

    /**
     * Return the full path name for a ELIS library file.
     */
    public static function lib($file) {
        return self::file("eliscore/lib/{$file}");
    }

    /**
     * plugin configuration options
     */
    public static $config;
}

class elis_config {
    private $configs = array();

    public function &__get($name) {
        global $DB;
        if (!isset($this->configs[$name])) {
            $config = new stdClass;

            // load the defaults
            if (file_exists(elis::plugin_file($name, 'defaults.php'))) {
                $defaults = array();
                include(elis::plugin_file($name, 'defaults.php'));
                foreach ($defaults as $key => $value) {
                    $config->$key = $value;
                }
            }

            $configrecs = $DB->get_recordset('config_plugins', array('plugin' => $name));
            foreach ($configrecs as $rec) {
                $key = $rec->name;
                $config->$key = $rec->value;
            }
            unset($configrecs);

            $this->configs[$name] = $config;
        }
        return $this->configs[$name];
    }
}

global $CFG;
elis::$basedir = "{$CFG->dirroot}/local";
elis::$libdir = elis::file('eliscore/lib');

elis::$config = new elis_config();

/**
 * Must create a function to limit scope of $plugin variable getting confused in install
 * see: ELIS-8850
 */
function eliscore_version_init() {
    $plugin = new stdClass;
    require(elis::file('eliscore/version.php'));
    elis::$version = $plugin->version;
    elis::$release = $plugin->release;
}
eliscore_version_init();
