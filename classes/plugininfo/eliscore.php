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

namespace local_eliscore\plugininfo;

defined('MOODLE_INTERNAL') || die();

/**
 * This class defines eliscore subplugininfo
 */
class eliscore extends \core\plugininfo\base {
    /** @var string the plugintype name, eg. mod, auth or workshopform */
    public $type = 'local';
    /** @var string full path to the location of all the plugins of this type */
    public $typerootdir = '/local/eliscore/plugins/';
    /** @var string the plugin name, eg. assignment, ldap */
    public $name = 'eliscore';
    /** @var string the localized plugin name */
    public $displayname = 'ELIS Core subplugins';
    /** @var string the plugin source, one of core_plugin_manager::PLUGIN_SOURCE_xxx constants */
    public $source;
    /** @var string fullpath to the location of this plugin */
    public $rootdir;
    /** @var int|string the version of the plugin's source code */
    public $versiondisk;
    /** @var int|string the version of the installed plugin */
    public $versiondb;
    /** @var int|float|string required version of Moodle core  */
    public $versionrequires;
    /** @var mixed human-readable release information */
     /** @var mixed human-readable release information */
    public $release = '2.6.0.0';
    /** @var array other plugins that this one depends on, lazy-loaded by {@link get_other_required_plugins()} */
    public $dependencies;
    /** @var int number of instances of the plugin - not supported yet */
    public $instances;
    /** @var int order of the plugin among other plugins of the same type - not supported yet */
    public $sortorder;
    /** @var array|null array of {@link \core\update\info} for this plugin */
    public $availableupdates;
}
