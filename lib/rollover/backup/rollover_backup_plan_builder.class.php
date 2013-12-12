<?php
/**
 * Class that specifies the tasks that belong to the rollover's backup plan
 *
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
 * @copyright  (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/backup/moodle2/backup_plan_builder.class.php'); // backup for other file
require_once(elis::lib('rollover/backup/rollover_backup_final_task.class.php'));

class elis_course_backup extends backup_plan_builder {
    /**
     * public static method to build_course_plan.
     * @param backup_controller $controller An instance of backup_controller class.
     * @param int $id course id.
     */
    static public function build_course_plan($controller, $id) {
        parent::build_course_plan($controller, $id);
    }
}

/**
 * Class responsible for compiling the tasks associated with the plan used in the
 * backup portion of the rollover
 */
abstract class rollover_backup_plan_builder {

    /**
     * Dispatches, based on type to specialised builders
     *
     * @param object $controller The backup controller whose plan we are building
     */
    static public function build_plan($controller) {
        $plan = $controller->get_plan();

        //task for initializing backup
        $plan->add_task(new backup_root_task('root_task'));

        //task for main backup work
        elis_course_backup::build_course_plan($controller, $controller->get_id());

        //customized cleanup task
        $plan->add_task(new rollover_backup_final_task('final_task'));
    }
}
