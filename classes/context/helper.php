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
        } else if (empty(parent::$alllevels)) {
            return CONTEXT_SYSTEM;
        } else {
            throw new coding_exception('Invalid context level specified');
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
     * Return the context level associated with the context class name
     * @param $classname the class name
     * @throws coding_exception
     * @return int context level for given class name
     */
    public static function get_level_from_class_name($classname) {
        parent::init_levels();

        $contextlevel = array_search($classname, parent::$alllevels);

        if (false !== $contextlevel) {
            return $contextlevel;
        }

        throw new coding_exception('Context class name not found in defined custom contexts.');
    }
}
