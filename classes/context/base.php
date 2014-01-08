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
 * This class will allow for further changes in how ELIS custom contexts are implemented
 */
abstract class base extends \context {
    /**
     * @var array An array mapping ELIS context names to their context levels
     */
    public static $namelevelmap = array();

    /**
     * Get a context instance as an object, from a given context id.
     *
     * @static
     * @param int $id context id
     * @param int $strictness IGNORE_MISSING means compatible mode, false returned if record not found, debug message if more found;
     *                        MUST_EXIST means throw exception if no record found
     * @return context|bool the context object or false if not found
     */
    public static function instance_by_id($id, $strictness = MUST_EXIST) {
        global $DB;

        if (get_called_class() !== 'local_eliscore\context\base' and get_called_class() !== 'local_eliscore\context\helper') {
            // Some devs might confuse context->id and instanceid, better prevent these mistakes completely.
            throw new \coding_exception('use only \local_eliscore\context\base::instance_by_id() for real context levels use ::instance() methods');
        }

        if ($id == SYSCONTEXTID) {
            return \context_system::instance(0, $strictness);
        }

        if (is_array($id) || is_object($id) || empty($id)) {
            throw new \coding_exception('Invalid context id ['.$id.'] specified \local_eliscore\context\base::instance_by_id()');
        }

        if ($context = static::cache_get_by_id($id)) {
            return $context;
        }

        if ($record = $DB->get_record('context', array('id' => $id), '*', $strictness)) {
            return static::create_instance_from_record($record);
        }

        return false;
    }

    /**
     * This function is also used to work around 'protected' keyword problems in context_helper.
     *
     * @static
     * @param stdClass $record
     * @return context instance
     */
    protected static function create_instance_from_record(\stdClass $record) {
        $classname = helper::get_class_for_level($record->contextlevel);

        if ($context = static::cache_get_by_id($record->id)) {
            return $context;
        }

        $context = new $classname($record);
        static::cache_add($context);

        return $context;
    }
}
