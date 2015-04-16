<?php
/**
 * Run scheduled tasks according to a cron spec.  Based on
 * http://docs.moodle.org/en/Development:Scheduled_Tasks_Proposal
 *
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

require_once(dirname(__FILE__).'/lib/setup.php');

define('ELIS_TASKS_CRONSECS', 10 * 60); // TBD: 10 min max total runtime (save 1 min for other cron?)

/**
 * Run scheduled tasks according to a cron spec.
 *
 * uses  $CFG, $DB
 */
function local_eliscore_cron_scheduledtask() {
    global $CFG, $DB;

    require($CFG->dirroot.'/local/eliscore/lib/tasklib.php');

    $timenow = time();
    // get all tasks that are (over-)due
    $params = array('timenow' => $timenow);
    $tasks = $DB->get_recordset_select('local_eliscore_sched_tasks', 'nextruntime <= :timenow', $params, 'nextruntime ASC');
    $numtasks = $DB->count_records_select('local_eliscore_sched_tasks', 'nextruntime <= :timenow', $params);

    // Check if the maximum cron run time is overridden
    $remtime = ELIS_TASKS_CRONSECS;
    if (isset($CFG->elistaskscronsecs) && is_int($CFG->elistaskscronsecs) && 0 < $CFG->elistaskscronsecs) {
        $remtime = $CFG->elistaskscronsecs;
    }

    if (empty($tasks) || !$tasks->valid()) {
        return;
    }

    foreach ($tasks as $task) {
        $starttime = microtime();
        mtrace("Running {$task->callfunction}({$task->taskname}) from {$task->plugin}...");

        if ($task->enddate !== null && $task->enddate < $timenow) {
            mtrace('* Cancelling task: past end date');
            $DB->delete_records('local_eliscore_sched_tasks', array('id' => $task->id));
            --$numtasks;
            continue;
        }

        // Check for blocking tasks.
        if (!empty($task->blocked) && $timenow < $task->blocked) {
            // Task is still running - do not start another instance of it.
            mtrace("{$task->plugin}: Previous {$task->taskname} process has not yet completed - aborting!");
            continue;
        }

        // FIXME: check if task is locked

        // See if some other cron has already run the function while we were
        // doing something else -- if so, skip it.
        $nextrun = $DB->get_field('local_eliscore_sched_tasks', 'nextruntime', array('id' => $task->id));
        if ($nextrun > $timenow) {
            mtrace('* Skipped (someone else already ran it)');
            --$numtasks;
            continue;
        }

        // calculate the next run time
        $newtask = new stdClass;
        $newtask->id = $task->id;
        $newtask->lastruntime = time();
        $newtask->nextruntime = cron_next_run_time($newtask->lastruntime, (array)$task);
        // see if we have any runs left
        if ($task->runsremaining !== null) {
            $newtask->runsremaining = $task->runsremaining - 1;
            if ($newtask->runsremaining <= 0) {
                mtrace('* Cancelling task: no runs left');
                $DB->delete_records('local_eliscore_sched_tasks', array('id' => $task->id));
            } else {
                $DB->update_record('local_eliscore_sched_tasks', $newtask);
            }
        } else {
            $DB->update_record('local_eliscore_sched_tasks', $newtask);
        }

        // load the file and call the function
        if ($task->callfile) {
            $callfile = $CFG->dirroot.$task->callfile;
            if (!is_readable($callfile)) {
                mtrace('* Skipped (file not found)');
                --$numtasks;
                continue;
            }
            require_once ($callfile);
        }

        $starttask = time();
        $denom = ($numtasks > 0) ? $numtasks-- : 1; // prevent div by 0
        $runtime = floor((float)$remtime / (float)$denom);
        call_user_func(unserialize($task->callfunction), $task->taskname, $runtime);
        $remtime -= time() - $starttask;

        $difftime = microtime_diff($starttime, microtime());
        mtrace("* {$difftime} seconds");

        // TBD: exit if over cron processing time
        if ($remtime <= 0) {
            break;
        }
    }
}

/**
 * Returns a persons full name.
 *
 * Wrapper for the Moodle fullname() function that ensures all user fields exist.
 *
 * @param stdClass $user A {@link $USER} object to get full name of.
 * @param bool $override If true then the name will be firstname followed by lastname rather than adhering to fullnamedisplay.
 * @return string
 */
function elis_fullname($user, $override = false) {
    if ($user instanceof elis_data_object) {
        $user = $user->to_object();
    }

    if (!isset($user->firstname) && !isset($user->lastname)) {
        return '';
    }

    $allnames = get_all_user_name_fields();
    foreach ($allnames as $allname) {
        if (!property_exists($user, $allname)) {
            $user->$allname = null;
        }
    }
    return fullname($user, $override);
}

/**
 * Get scheduled period in minutes
 *
 * @param  string $period The schedule period in: *d*h*m format
 * @return int The schedule period in minutes, -1 on error
 */
function schedule_period_minutes($period) {
    $period_elems = array('d' => DAYSECS/60, 'h' => HOURSECS/60, 'm' => 1);
    $parray = str_split($period);
    $num = '';
    $min = 0;
    foreach ($parray as $char) {
        if (ctype_space($char)) {
            continue;
        } else if (ctype_digit($char)) {
            $num .= $char;
        } else {
            if (!array_key_exists($char, $period_elems)) {
                return -1; // error
            }
            $multiplier = $period_elems[$char];
            $min += intval($num) * $multiplier;
            $num = '';
        }
    }
    return $min;
}

/**
 * This function adjusts a GMT timestamp to timezone
 * @param $timestamp
 * @param $timezone
 * @param mixed $dstdate default null uses $timestamp (param1) for dst calc
 *              false disables dst offset,
 *              otherwise dstdate value used in place of timestamp for dst calc
 * @return int  timestamp (secs since epoch) in timezone
 */
function from_gmt($timestamp, $timezone = 99, $dstdate = null) {
    $tz = get_user_timezone_offset($timezone);
    $ts = (abs($tz) > 13) ? $timestamp : ($timestamp + ($tz * HOURSECS));
    if ($dstdate === null) {
        $dstdate = $timestamp;
    }
    $dstoffset = null;
    if (!empty($dstdate) && ($timezone == 99 || !is_numeric($timezone))) {
        $dstdate = (abs($tz) > 13) ? $dstdate : ($dstdate + ($tz * HOURSECS));
        $strtimezone = is_numeric($timezone) ? NULL : $timezone;
        $dstoffset = dst_offset_on($dstdate, $strtimezone);
        $ts += $dstoffset; // TBD or -= see: to_gmt()
    }
    //debug_error_log("/local/elisreports/shardlib.php::from_gmt({$timestamp}, {$timezone}): tz = {$tz} dstdate = {$dstdate} dstoffset = {$dstoffset} => {$ts}");
    return $ts;
}

/**
 * This function converts a timestamp in timezone to GMT (UTC)
 * @param $timestamp
 * @param $timezone
 * @param mixed $dstdate default null uses $timestamp (param1) for dst calc
 *              false disables dst offset,
 *              otherwise dstdate value used in place of timestamp for dst calc
 * @return int  adjusted timestamp (secs since epoch)
 */
function to_gmt($timestamp, $timezone = 99, $dstdate = null) {
    if ($dstdate == null) {
        $dstdate = $timestamp;
    }
    $ts = $timestamp;
    $dstoffset = null;
    if (!empty($dstdate) && ($timezone == 99 || !is_numeric($timezone))) {
        $strtimezone = is_numeric($timezone) ? NULL : $timezone;
        $dstoffset = dst_offset_on($dstdate, $strtimezone);
        $ts -= $dstoffset; // or += see to_gmt()
    }
    $tz = get_user_timezone_offset($timezone);
    $ts = (abs($tz) > 13) ? $ts : ($ts - ($tz * HOURSECS));
    //debug_error_log("/local/elisreports/sharedlib.php::to_gmt({$timestamp}, {$timezone}): tz = {$tz} dstdate = {$dstdate} dstoffset = {$dstoffset} => $ts");
    return $ts;
}

/**
 * Function to check debug level for DEBUG_DEVELOPER
 * and output string to web server error log file.
 */
function debug_error_log($str) {
    if ((!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) && debugging('', DEBUG_DEVELOPER)) {
        error_log($str);
    }
}
