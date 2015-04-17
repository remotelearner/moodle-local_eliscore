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
 * @copyright  (C) 2014 Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

defined('MOODLE_INTERNAL') || die;

$ADMIN->add('root', new admin_category('local_eliscore', get_string('pluginname', 'local_eliscore'), true));
$settings = new admin_settingpage('local_eliscore_settings', get_string('eliscore_etl', 'eliscore_etl'), 'moodle/site:config');
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox('eliscore_etl/etl_disabled', get_string('etl_disabled', 'eliscore_etl'),
            get_string('etl_disabled_description', 'eliscore_etl'), 0));
    $ADMIN->add('local_eliscore', $settings);
}
