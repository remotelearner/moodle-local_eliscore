<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2015 Remote Learner.net Inc http://www.remote-learner.net
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
 * @copyright  (C) 2015 Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

/**
 * Trait providing scheduling workflow settings for scheduled jobs.
 */
trait elisschedulingworkflowtrait {
    /** @const Recurrence constants */
    public static $RECURRENCE_SIMPLE = 'simple';
    public static $RECURRENCE_CALENDAR = 'calendar';
    public static $RECURRENCE_PERIOD = 'period'; // DataHub historic format.

    /** @const Frequency constants */
    public static $FREQ_MIN = 'minute';
    public static $FREQ_HOUR = 'hour';
    public static $FREQ_DAY = 'day';
    public static $FREQ_MONTH = 'month';

    /** @var the workflow instance object */
    protected $workflowinst = null;

    /**
     * Method to initialize trait params.
     * @param object $workflowinst  The workflow instance.
     */
    public function init_schedule_trait($workflowinst) {
        $this->workflowinst = $workflowinst;
    }

    /**
     * Method to save values for step schedule.
     * @param object $values the form element values.
     * @return array Form element validation errors.
     */
    public function save_values_for_step_schedule($values) {
        // Common scheduling parameters.
        $schedule = array();
        $errors = array();

        $data = $this->workflowinst->unserialize_data(array());
        if (!empty($values->period)) {
            if (schedule_period_minutes($values->period) < 5) {
                $errors['period'] = get_string('form_period_error', 'local_eliscore');
                return $errors;
            }
            $data['recurrencetype'] = static::$RECURRENCE_PERIOD;
            $schedule['period'] = $data['period'] = $values->period;
        } else {
            $data['period'] = '';
            $data['timezone'] = isset($values->timezone) ? $values->timezone : 99;
            // NOTE: CANNOT test $values->timezone using empty, as 0 is a valid timezone!
            $data['startdate'] = empty($values->startdate) ? null : $values->startdate;
            $data['recurrencetype'] = (!empty($values->recurrencetype) && $values->recurrencetype == static::$RECURRENCE_CALENDAR) ? static::$RECURRENCE_CALENDAR : static::$RECURRENCE_SIMPLE;
            if ($data['recurrencetype'] != static::$RECURRENCE_CALENDAR) {
                $schedule['runsremaining'] = empty($values->runsremaining) ? null : $values->runsremaining;
                $schedule['frequency'] = empty($values->frequency) ? 1 : $values->frequency;
                $schedule['frequencytype'] = empty($values->frequencytype) ? static::$FREQ_DAY : $values->frequencytype;
                $schedule['enddate'] = empty($values->enddate) ? null : $values->enddate;
                if ($schedule['frequencytype'] == static::$FREQ_MIN && $schedule['frequency'] < 5) {
                    $errors['runtype'] = get_string('form_frequency_error', 'local_eliscore');
                    return $errors;
                }
            } else {
                $schedule['runsremaining'] = null;
               /* ***
                ob_start();
                var_dump($values);
                $tmp = ob_get_contents();
                ob_end_clean();
                debug_error_log("save_values_for_step_schedule(values) => {$tmp}");
               *** */
                if (empty($values->dayofweek)) {
                    $errors['dayofweek'] = get_string('required');
                }
                if (empty($values->day)) {
                    $errors['day'] = get_string('required');
                }
                if ($values->caldaystype == '2') {
                    $regex = "/^(\\d|[12]\\d|30|31)(,(\\d|[12]\\d|30|31))*$/";
                    //$regex = "/^\d+(,\d+)*$/";
                    if (!preg_match($regex,$values->monthdays)) {
                        $errors['daytype'] = get_string('validmonthdays','local_elisreports');
                    }
                }
                if (empty($values->month) && empty($values->allmonths)) {
                    $errors['month'] = get_string('required');
                }
                if (!empty($errors)) {
                    return $errors;
                }

                //debug_error_log("/local/elisreports/lib/schedulelib.php::save_values_for_step_schedule(): hour={$values->hour}  min={$values->minute} timezone={$values->timezone}");
                $schedule['hour'] = empty($values->hour) ? 0 : $values->hour;
                $schedule['minute'] = empty($values->minute) ? 0 : $values->minute;
                $schedule['dayofweek'] = $values->dayofweek;
                $schedule['day'] = $values->day;
                if (!empty($values->allmonths) || $values->month == '1,2,3,4,5,6,7,8,9,10,11,12') {
                    $schedule['month'] = '*';
                } else {
                    $schedule['month'] = $values->month;
                }
                $schedule['enddate'] = empty($values->calenddate) ? null : $values->calenddate;
            }
        }
        $data['schedule'] = $schedule;
        $this->workflowinst->set_data($data);
        $this->workflowinst->save();
        return null;
    }

    /**
     * Method to be called to create/update elis scheduled tasks record.
     * @param string $taskname The taskname.
     * @param string $component The component being scheduled.
     * @param string $callfile The call-file where call-function resides.
     * @param string $callfunction The function to call for schedule run.
     * @param array $data The scheduling data.
     * @return int|bool The local_eliscore_sched_tasks record DB id (> 0) on success, or false on error.
     */
    public function save_elis_scheduled_task($taskname, $component, $callfile, $callfunction, $data) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/eliscore/lib/tasklib.php');
        $DB->delete_records('local_eliscore_sched_tasks', array('taskname' => $taskname));
        $runsremaining = null;
        $recurrencetype = static::$RECURRENCE_PERIOD;
        $minute    = 0;
        $hour      = 0;
        $day       = '*';
        $month     = '*';
        $dayofweek = '*';
        $tz        = 99;
        $startdate = null;
        $enddate   = null;
        if (!empty($data['timemodified'])) {
            $timenow = $data['timemodified'];
        } else {
            $timenow = time();
        }
        if (empty($data['period'])) {
            $tz = $data['timezone'];
            // Thou startdate is checked in runschedule.php, the confirm form
            // would display an incorrect 'will run next at' time (eg. current time)
            // if we don't calculate it - see below ...
            $startdate = empty($data['startdate']) ? $timenow : $data['startdate'];
            $recurrencetype = $data['recurrencetype'];
            if ($recurrencetype == static::$RECURRENCE_SIMPLE) {
                // Simple - hour/minute are from time modified.
                $minute = (int)strftime('%M', $startdate);
                $hour   = (int)strftime('%H', $startdate);
                $freq   = (!empty($data['schedule']['frequency']) && $data['schedule']['frequency'] > 1) ? '*/'.$data['schedule']['frequency'] : '*';
                switch ($data['schedule']['frequencytype']) {
                    case self::$FREQ_MIN:
                        $minute = $freq;
                        $hour = '*';
                        break;
                    case self::$FREQ_HOUR:
                        $hour = $freq;
                        break;
                    case self::$FREQ_MONTH:
                        $month = $freq;
                        $day = (int)strftime('%d', $startdate);
                        break;
                    default: // self::$FREQ_DAY.
                        $day = $freq;
                        break;
                }
            } else { // Calendar.
                $minute    = $data['schedule']['minute'];
                $hour      = $data['schedule']['hour'];
                $day       = $data['schedule']['day'];
                $month     = $data['schedule']['month'];
                $dayofweek = $data['schedule']['dayofweek'];
            }
            $runsremaining = empty($data['schedule']['runsremaining']) ? null : $data['schedule']['runsremaining'];
            $enddate = empty($data['schedule']['enddate']) ? null : $data['schedule']['enddate'];
        }

        $task                = new stdClass;
        $task->plugin        = $component;
        $task->taskname      = $taskname;
        $task->callfile      = $callfile;
        $task->callfunction  = serialize($callfunction);
        $task->period        = '';
        $task->lastruntime   = 0;
        $task->blocking      = 0;
        $task->minute        = $minute;
        $task->hour          = $hour;
        $task->day           = $day;
        $task->month         = $month;
        $task->dayofweek     = $dayofweek;
        $task->timezone      = $tz;
        $task->startdate     = $startdate;
        $task->enddate       = ($enddate != null) ? ($enddate + DAYSECS - 1): null;
        debug_error_log("schedulelib.php::finish() startdate = {$startdate}; timemodified = {$timenow}");

        if ($recurrencetype == static::$RECURRENCE_PERIOD) {
            $task->period = $data['period'];
            $task->nextruntime = $timenow;
        } else if ($startdate < $timenow) {
            $inittime = $startdate;
            do {
                debug_error_log("schedulelib.php::finish() looping to advance startdate {$inittime} to >= {$timenow}");
            } while (($inittime = cron_next_run_time($inittime, (array)$task)) < $timenow);
            $task->nextruntime = $inittime;
        } else {
            $task->nextruntime = ($recurrencetype == static::$RECURRENCE_SIMPLE) ? $startdate : cron_next_run_time($startdate - 61, (array)$task);
        }
        $task->runsremaining = $runsremaining;

        return $DB->insert_record('local_eliscore_sched_tasks', $task);
    }
}

/**
 * Trait providing scheduling page methods for scheduled jobs.
 */
trait elisschedulingpagetrait {
    /** @var the scheduling page instance object */
    protected $page = null;

    /**
     * Method to initialize trait params.
     * @param object $schedulingpage  The scheduling page instance.
     */
    public function init_schedule_trait($schedulingpage) {
        $this->page = $schedulingpage;
    }

    /**
     * Method to display step schedule.
     * @param array $errors The form errors array.
     */
    public function display_step_schedule($errors) {
        $form = new $this->page->schedule_form(null, $this->page);
        if ($errors) {
            foreach ($errors as $element => $msg) {
                $form->setElementError($element, $msg);
            }
        }
        $workflowdata = $this->page->workflow->unserialize_data(array());
        $data = new stdClass;
        // Create appropriate values for forms.
        if (!empty($workflowdata['period'])) {
            $data->period = $workflowdata['period'];
            if (!isset($workflowdata['recurrencetype']) || $workflowdata['recurrencetype'] != elisschedulingworkflowtrait::$RECURRENCE_PERIOD) {
                $workflowdata['recurrencetype'] = elisschedulingworkflowtrait::$RECURRENCE_PERIOD;
            }
        }
        $data->timezone = 99;
        if (isset($workflowdata['timezone'])) {
            $data->timezone = $workflowdata['timezone'];
        }
        if (isset($workflowdata['startdate'])) {
            $data->starttype = 1;
            $data->startdate = from_gmt($workflowdata['startdate'], $data->timezone);
            debug_error_log("/lib/schedulelib.php::display_step_schedule() adjusting startdate from {$workflowdata['startdate']} to {$data->startdate}, {$data->timezone}");
        } else {
            $data->starttype = 0;
        }
        if (isset($workflowdata['recurrencetype'])) {
            $data->recurrencetype = $workflowdata['recurrencetype'];
        }

        // Common scheduling parameters.
        if (isset($workflowdata['schedule']['runsremaining'])) {
            $data->runsremaining = $workflowdata['schedule']['runsremaining'];
        }
        if (isset($workflowdata['schedule']['frequency'])) {
            $data->frequency = $workflowdata['schedule']['frequency'];
        }
        if (isset($workflowdata['schedule']['frequencytype'])) {
            $data->frequencytype = $workflowdata['schedule']['frequencytype'];
        }
        $hour = isset($workflowdata['schedule']['hour']) ? $workflowdata['schedule']['hour'] : 0;
        $min = isset($workflowdata['schedule']['minute']) ? $workflowdata['schedule']['minute'] : 0;
        $data->time['hour'] = $hour;
        $data->time['minute'] = $min;

        if (isset($data->recurrencetype)) {
            if ($data->recurrencetype == elisschedulingworkflowtrait::$RECURRENCE_SIMPLE) {
                // Set up runtype, frequencytype, enddate(day, month, year) <= timezone conversion req'd.
                if (isset($workflowdata['schedule']['enddate'])) {
                    $data->runtype = 1;
                    $data->enddate = from_gmt($workflowdata['schedule']['enddate'], $data->timezone);
                    debug_error_log("/lib/schedulelib.php::display_step_schedule() adjusting enddate from {$workflowdata['schedule']['enddate']} to {$data->enddate}, {$data->timezone}");
                } else {
                    $data->runtype = (!empty($workflowdata['schedule']['runsremaining']) || $data->frequency != 1 || $data->frequencytype != elisschedulingworkflowtrait::$FREQ_DAY) ? 2 : 0;
                }
            } else if ($data->recurrencetype == elisschedulingworkflowtrait::$RECURRENCE_CALENDAR) {
                // Get time/day/month and convert to
                // calenddate, time, caldaystype - 0 (every day), 1(week days), 2(month days)
                // dayofweek 1-7, allmonths (1) and month array.
                if (isset($workflowdata['schedule']['enddate'])) {
                    $data->calenddate = $workflowdata['schedule']['enddate'];
                    $data->calenddate = from_gmt($data->calenddate, $data->timezone);
                    debug_error_log("/lib/schedulelib.php::display_step_schedule() adjusting calenddate from {$workflowdata['schedule']['enddate']} to {$data->calenddate}, {$data->timezone}");
                }
                if ($workflowdata['schedule']['dayofweek'] == '*') {
                     if ($workflowdata['schedule']['day'] == '*') {
                        $data->caldaystype = 0;
                     } else {
                        $data->caldaystype = 2;
                        // Get the month days.
                        $data->monthdays = $workflowdata['schedule']['day'];
                    }
                } else {
                    $data->caldaystype = 1;
                    // Get all the days of the week.
                    $daysofweek = explode(',', $workflowdata['schedule']['dayofweek']);
                    foreach ($daysofweek as $day) {
                        if ((int) $day) {
                            $data->dayofweek[(int) $day] = 1;
                        }
                    }
                }

                if ($workflowdata['schedule']['month'] == '1,2,3,4,5,6,7,8,9,10,11,12' || $workflowdata['schedule']['month'] == '*') {
                    $data->allmonths = 1;
                    //$workflowdata['schedule']['month'] = '1,2,3,4,5,6,7,8,9,10,11,12';
                } else {
                    $months = explode(',', $workflowdata['schedule']['month']);
                    foreach ($months as $month) {
                        if ((int) $month) {
                            $data->month[(int) $month] = 1;
                        }
                    }
                }

            }
        }
        $form->set_data($data);
        $form->display();
    }

    /**
     * Method to get sunmitted values from schedule form.
     * @return object The scheduling parameters.
     */
    public function get_submitted_values_for_step_schedule() {
        $form = new $this->page->schedule_form(null, $this->page);
        $data = $form->get_data(false);

        if (!empty($data->period)) {
            $data->recurrencetype = elisschedulingworkflowtrait::$RECURRENCE_PERIOD;
            $data->startdate = null;
            $data->enddate = null;
            $data->runsremaining = null;
            return $data;
        } else {
            // set the startdate to today if set to start now
            if (empty($data->starttype)) {
                $data->startdate = time();
            } else {
                $data->startdate = to_gmt($data->startdate, $data->timezone);
            }
            debug_error_log("get_submitted_values_for_step_schedule(): startdate  = {$data->startdate}");
            if ($data->recurrencetype == elisschedulingworkflowtrait::$RECURRENCE_SIMPLE) {
                $data->runsremaining = empty($data->runsremaining) ? null : $data->runsremaining;
                if (!isset($data->runtype) || $data->runtype == 0) {
                    $data->enddate = null;
                } elseif ($data->runtype == 1) {
                    debug_error_log("get_submitted_values_for_step_schedule(): adjusting enddate from $data->enddate to " . to_gmt($data->enddate, $data->timezone));
                    $data->enddate = to_gmt($data->enddate, $data->timezone);
                } else {
                    $data->enddate = null;
                }
            } else if ($data->recurrencetype == elisschedulingworkflowtrait::$RECURRENCE_CALENDAR) {
                if ($data->calenddate) {
                    debug_error_log("get_submitted_values_for_step_schedule(): adjusting calenddate from $data->calenddate to " . to_gmt($data->calenddate, $data->timezone));
                    $data->calenddate = to_gmt($data->calenddate, $data->timezone);
                    $data->enddate = $data->calenddate;
                } else {
                    $data->enddate = null;
                }
                $data->time = isset($data->time) ? $data->time : 0;
                //debug_error_log("get_submitted_values_for_step_schedule(): data->time = {$data->time}");
                $data->hour = floor($data->time / HOURSECS) % 24;
                $data->minute = floor($data->time / MINSECS) % MINSECS;
                if (!isset($data->caldaystype) || $data->caldaystype == 0) {
                    $data->dayofweek = '*';
                    $data->day = '*';
                } elseif ($data->caldaystype == 1) {
                    $dayofweek = empty($data->dayofweek) ? array() : $data->dayofweek;
                    $data->dayofweek = array();
                    foreach ($dayofweek as $day => $dummy) {
                        $data->dayofweek[] = $day;
                    }
                    $data->dayofweek = implode(',', $data->dayofweek);
                    $data->day = '*';
                } elseif ($data->caldaystype == 2) {
                    $data->dayofweek = '*';
                    $days = explode(',',$data->monthdays);
                    $data->day = array();
                    foreach ($days as $day) {
                        if ((int) $day) {
                            $data->day[] = (int) $day;
                        }
                    }
                    $data->day = implode(',', $data->day);
                }

                $months = empty($data->month) ? array() : $data->month;
                $data->month = array();
                foreach ($months as $month => $dummy) {
                    $data->month[] = $month;
                }
                $data->month = implode(',', $data->month);
            }
        }
        return $data;
    }
}
