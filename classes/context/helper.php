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
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

namespace local_eliscore\context;

defined('MOODLE_INTERNAL') || die();

/**
 * Implement some of the methods from the Moodle 'context_helper' class that are specific to the properties defined
 * within this class.
 */
class helper extends \context_helper {
    /**
     * @var array An array mapping ELIS context names to their context levels
     */
    public static $namelevelmap = array();

    /**
     * @var array A public version of parent's private $alllevels
     */
    public static $puballlevels = array();

    /** @var array Holds definition data of custom context levels. */
    public static $customlevels = array();

    /**
     * Returns a context level given a context 'name'
     *
     * @static
     * @param string $contextname ('curriculum', 'track', etc)
     * @throws coding_exception
     * @return int context level
     */
    public static function get_level_from_name($ctxname) {
        if (isset(self::$namelevelmap[$ctxname])) {
            return self::$namelevelmap[$ctxname];
        } else if (empty(self::$namelevelmap)) {
            return CONTEXT_SYSTEM;
        } else {
            throw new \coding_exception('Invalid context level ['.$ctxname.'] specified');
        }
    }

    /**
     * Reset internal context levels array.
     *
     * @static
     */
    public static function reset_levels() {
        self::$puballlevels = null;
        parent::reset_levels();
    }

    /**
     * Set the custom context levels used for this class.
     *
     * @static
     * @throws coding_exception If a level is not properly defined.
     * @param array $levels An array of custom context level definitions.
     */
    public static function set_custom_levels(array $levels) {
        self::$customlevels = array();
        foreach ($levels as $level) {
            if (empty($level) || !is_array($level)) {
                throw new \coding_exception('Invalid context level definition - invalid or empty definition received.');
            }

            if (empty($level['level']) || !is_int($level['level'])) {
                throw new \coding_exception('Invalid context level definition - invalid context level received.');
            }

            if (empty($level['constant']) || !is_string($level['constant'])) {
                throw new \coding_exception('Invalid context level definition - invalid constant name received.');
            }

            if (empty($level['name']) || !is_string($level['name'])) {
                throw new \coding_exception('Invalid context level definition - invalid context name received.');
            }

            if (empty($level['class']) || !is_string($level['class'])) {
                throw new \coding_exception('Invalid context level definition - invalid context class received.');
            }

            self::$customlevels[$level['level']] = $level;
        }
    }

    /**
     * Install whatever levels are set in self::$customlevels into the custom_context_classes config property.
     *
     * @return bool Success/Failure.
     */
    public static function install_custom_levels() {
        global $CFG;
        if (empty(self::$customlevels)) {
            return true;
        }

        // Build a context level => context class map.
        if (isset($CFG->custom_context_classes) && is_array($CFG->custom_context_classes)) {
            $levelclassmap = $CFG->custom_context_classes;
        } else {
            $levelclassmap = array();
        }
        foreach (self::$customlevels as $level => $data) {
            $levelclassmap[$level] = $data['class'];
        }

        set_config('custom_context_classes', serialize($levelclassmap));
        $CFG->custom_context_classes = $levelclassmap;
        return true;
    }

    /**
     * Initialize internal context level arrays.
     *
     * @static
     */
    public static function init_levels() {
        if (empty(self::$puballlevels)) {
            self::$puballlevels = parent::get_all_levels();
        }

        $contextnameclassmap = array();
        foreach (self::$customlevels as $level => $data) {
            $contextnameclassmap[$data['class']] = $data['name'];
        }

        $namelevelmap = array();
        foreach (self::get_all_levels() as $contextlevel => $contextclass) {
            if (isset($contextnameclassmap[$contextclass])) {
                $namelevelmap[$contextnameclassmap[$contextclass]] = $contextlevel;
            }
        }

        self::$namelevelmap = $namelevelmap;
        base::$namelevelmap = $namelevelmap;
        self::define_context_constants();
    }

    /**
     * Define context constants for ELIS context levels.
     *
     * @static
     */
    public static function define_context_constants() {
        foreach (self::$customlevels as $level => $data) {
            if (!defined($data['constant'])) {
                try {
                    $level = static::get_level_from_name($data['name']);
                    if ($level !== CONTEXT_SYSTEM) {
                        define($data['constant'], $level);
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }

    /**
     * Returns a list of legacy context names and their associated context level int
     *
     * @static
     * @return array string=>int (level legacy name=>level)
     */
    public static function get_legacy_levels() {
        return self::$namelevelmap;
    }

    /**
     * Returns a class name of the context level class
     *
     * @static
     * @param int $contextlevel (CONTEXT_SYSTEM, etc.)
     * @return string class name of the context class
     */
    public static function get_class_for_level($contextlevel) {
        if (empty(self::$puballlevels)) {
            self::$puballlevels = parent::get_all_levels();
        }
        if (isset(self::$puballlevels[$contextlevel])) {
            return self::$puballlevels[$contextlevel];
        } else {
            throw new \coding_exception('Invalid context level ['.$contextlevel.'] specified');
        }
    }

    /**
     * Return the context level associated with the context class name
     * @param $classname the class name
     * @throws coding_exception
     * @return int context level for given class name
     */
    public static function get_level_from_class_name($classname) {
        if (empty(self::$puballlevels)) {
            self::$puballlevels = parent::get_all_levels();
        }

        // Function get_called_class() does not prefix the result with a '\', as in the internal classnames.
        if ($classname{0} !== '\\') {
            $classname = '\\'.$classname;
        }

        $contextlevel = array_search($classname, self::$puballlevels);

        if (false !== $contextlevel) {
            return $contextlevel;
        }

        throw new \coding_exception('Context class name ['.$classname.'] not found in defined custom contexts.');
    }
}
