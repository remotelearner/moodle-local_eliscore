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
 * @package    eliscore_etl
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../../lib/setup.php');
require_once(dirname(__FILE__).'/lib.php');

define('ETL_TABLE',     'eliscore_etl_useractivity');
define('ETL_MOD_TABLE', 'eliscore_etl_modactivity');
define('MDL_LOG_TABLE', 'logstore_standard_log');

// process 10,000 records at a time
define ('USERACT_RECORD_CHUNK', 10000);
// max out at 2 minutes (= 120 seconds)
define ('USERACT_TIME_LIMIT', 120);

// define ('ETLUA_EXTRA_DEBUG', 1);

define('ETL_BLOCKED_MAX_TIME', 7 * DAYSECS); // An arbitrary long time to use when setting cron task blocking.

/**
 * ETL user activity
 */
class eliscore_etl_useractivity {
    public $state;
    public $duration;

    /** @var array $innerstate the internal processing state. */
    protected $innerstate;
    /**
     * format of $innerstate array is:
     * array(
     *    'userid' => array(
     *        'course' => array(
     *            'id'    => courseid,
     *            'start' => starttime),
     *        'module' => array( // optional!
     *            'id'    => moduleid,
     *            'start' => starttime)
     *    ));
     */

    /**
     * ETL user activity constructor
     * @param int $duration The amount of time to the run the cron
     * @param bool $outputmtrace Flag to show mtrace output
     */
    public function __construct($duration = 0, $outputmtrace = true) {
        $this->duration = $duration;
        $this->user_activity_task_init($outputmtrace);
    }

    /**
     * Add a session to the user activity ETL table.
     *
     * @param int $userid the user to add the session for
     * @param int $courseid the course to add the session for
     * @param int $cmid the course module to add the session for, 'empty' for none.
     * @param int $session_start the start time of the session
     * @param int $session_end the end time of the session
     * @uses $CFG;
     * @uses $DB;
     */
    public function user_activity_add_session($userid, $courseid, $cmid, $sessionstart, $sessionend) {
        global $CFG, $DB;
        if ($userid && $sessionstart && $sessionend) {
            $tablename = !empty($cmid) ? ETL_MOD_TABLE : ETL_TABLE;
            $length = $sessionend - $sessionstart;
            if (defined('ETLUA_EXTRA_DEBUG') && $CFG->debug >= DEBUG_DEVELOPER) {
                mtrace("** adding {$length} second session for user {$userid} in course {$courseid}, module {$cmid}");
            }
            // split the session into hours
            $starthour = floor($sessionstart / HOURSECS) * HOURSECS;
            $first = true;
            while ($sessionend > $starthour + HOURSECS) {
                $sessionhourduration = $starthour + HOURSECS - $sessionstart;
                $params = array(
                    'userid' => $userid,
                    'hour' => $starthour
                );
                if (!empty($cmid)) {
                    $params['cmid'] = $cmid;
                }
                if ($rec = $DB->get_record($tablename, $params)) {
                    $rec->duration += $sessionhourduration;
                    $DB->update_record($tablename, $rec);
                } else {
                    $rec = new stdClass;
                    $rec->userid = $userid;
                    $rec->courseid = $courseid;
                    if (!empty($cmid)) {
                        $rec->cmid = $cmid;
                    }
                    $rec->hour = $starthour;
                    $rec->duration = $sessionhourduration;
                    $DB->insert_record($tablename, $rec);
                }
                $starthour += HOURSECS;
                $sessionstart = $starthour;
                $first = false;
            }
            $remainder = $sessionend - $sessionstart;
            $params = array(
                'userid' => $userid,
                'hour' => $starthour
            );
            if (!empty($cmid)) {
                $params['cmid'] = $cmid;
            }
            if ($rec = $DB->get_record($tablename, $params)) {
                $rec->duration += $remainder;
                $DB->update_record($tablename, $rec);
            } else {
                $rec = new stdClass;
                $rec->userid = $userid;
                $rec->courseid = $courseid;
                if (!empty($cmid)) {
                    $rec->cmid = $cmid;
                }
                $rec->hour = $starthour;
                $rec->duration = $remainder;
                $DB->insert_record($tablename, $rec);
            }
        }
    }

    /**
     * Splits the Moodle log into sessions for each user, tracking how long they have spent in each Moodle course.
     * Processes approx 40k records / minute
     * @uses $DB
     */
    public function cron() {
        global $DB;

        $timenow = time();
        $rununtil = $timenow + (($this->duration > 0) ? $this->duration : USERACT_TIME_LIMIT);

        // Block other ETL cron tasks.
        $this->set_etl_task_blocked($timenow + ETL_BLOCKED_MAX_TIME);

        if (!isset($this->state['last_processed_time']) || (time() - $this->state['last_processed_time']) >= DAYSECS) {
            $this->state['recs_last_processed'] = 0;
            $this->state['last_processed_time'] = time();
            $this->state['log_entries_per_day'] = (float)$DB->count_records_select('log', 'time >= ? AND time < ?',
                    array($this->state['last_processed_time'] - 10 * DAYSECS, $this->state['last_processed_time'])) / 10.0;
        }
        do {
            list($completed, $total) = $this->user_activity_task_process();
            $this->state['recs_last_processed'] += $completed;
        } while (time() < $rununtil && $completed < $total);

        if ($completed < $total) {
            $this->user_activity_task_save();
        } else {
            $this->user_activity_task_finish();
        }

        // Clear blocking.
        $this->set_etl_task_blocked(0);
    }

    /**
     * Initialize the task state for the ETL process
     *
     * @uses $DB;
     * @param bool $outputmtrace Flag to show mtrace output
     */
    protected function user_activity_task_init($outputmtrace = true) {
        global $DB;
        if ($outputmtrace) {
            mtrace('Calculating user activity from Moodle log');
        }

        $state = isset(elis::$config->eliscore_etl->state) ? elis::$config->eliscore_etl->state : '';
        if (!empty($state)) {
            // We already have some state saved.  Use that.
            $this->state = unserialize($state);
        } else {
            $state = array();
            // ETL parameters
            $state['sessiontimeout'] = elis::$config->eliscore_etl->session_timeout;
            $state['sessiontail'] = elis::$config->eliscore_etl->session_tail;

            // the last run time that we have processed until
            $lastrun = isset(elis::$config->eliscore_etl->last_run) ? elis::$config->eliscore_etl->last_run : 0;
            $state['starttime'] = !empty($lastrun) ? (int)$lastrun : 0;

            $startrec = $DB->get_field_select(MDL_LOG_TABLE, 'MAX(id)', 'timecreated <= ?', array($state['starttime']));
            $startrec = empty($startrec) ? 0 : $startrec;
            $state['startrec'] = $startrec;

            $this->state = $state;
        }
    }

    /**
     * Get userid method for log table record
     * @param object $rec the log table record.
     * @return int the log record userid.
     */
    public function get_rec_userid($rec) {
        return $rec->userid;
    }

    /**
     * Get course method for log table record
     * @param object $rec the log table record.
     * @return int the log record courseid.
     */
    public function get_rec_course($rec) {
        return $rec->courseid;
    }

    /**
     * Get module method for log table record
     * @param object $rec the log table record.
     * @return mixed|int the log record moduleid, 'empty' for none.
     */
    public function get_rec_module($rec) {
        return ($rec->contextlevel == CONTEXT_MODULE) ? $rec->contextinstanceid : false; // TBD.
    }

    /**
     * Get time method for log table record
     * @param object $rec the log table record.
     * @return int the log record timestamp.
     */
    public function get_rec_time($rec) {
        return $rec->timecreated;
    }

    /**
     * Method to determine if log table record is a 'start' record.
     * @param object $rec the log table record.
     * @return bool true if the log record is a 'start' record, false otherwise.
     */
    public function is_start_rec($rec) {
        return ($rec->contextlevel == CONTEXT_COURSE || $rec->contextlevel == CONTEXT_MODULE);
    }

    /**
     * Method to determine if log table record is a 'stop' record.
     * @param object $rec the log table record.
     * @return bool true if the log record is a 'stop' record, false otherwise.
     */
    public function is_stop_rec($rec) {
        static $stopactions = array('loggedin', 'loggedout' /* , 'sent' */); // TBD.
        if (in_array($rec->action, $stopactions)) {
            return true;
        }
        // TBD.
        return false;
    }

    /**
     * Process a chunk of the task
     *
     * @return array Completed and total records
     * @uses $CFG;
     * @uses $DB;
     */
    public function user_activity_task_process() {
        global $CFG, $DB;

        $sessiontimeout = $this->state['sessiontimeout'];
        $sessiontail = $this->state['sessiontail'];

        $starttime = $this->state['starttime'];

        // find the record ID corresponding to our start time
        $startrec = $DB->get_field_select(MDL_LOG_TABLE, 'MIN(id)', 'timecreated >= ?', array($starttime));
        $startrec = empty($startrec) ? 0 : $startrec;

        // find the last record that's close to our chunk size, without
        // splitting a second between runs
        $endtime = $DB->get_field_select(MDL_LOG_TABLE, 'MIN(timecreated)', 'id >= ? AND timecreated > ?', array($startrec + USERACT_RECORD_CHUNK, $starttime));
        if (!$endtime) {
            $endtime = time();
        }

        // Get the logs between the last time we ran, and the current time.  Sort
        // by userid (so all records for a given user are together), and then by
        // time (so that we process a user's logs sequentially).
        $recstarttime = max(0, $starttime - $this->state['sessiontimeout']);
        $rs = $DB->get_recordset_select(MDL_LOG_TABLE, 'timecreated >= ? AND timecreated < ? AND userid != 0', array($recstarttime, $endtime), 'timecreated');
        if ($CFG->debug >= DEBUG_ALL) {
            mtrace("* processing records from time:{$starttime} to time:{$endtime}");
        }

        $lasttime = 0;
        $endrec = $startrec;
        $this->innerstate = array();
        if ($rs && $rs->valid()) {
            foreach ($rs as $rec) {
                $endrec = $rec->id;
                $lasttime = $this->get_rec_time($rec);
                if ($this->is_start_rec($rec)) {
                    $userid = $this->get_rec_userid($rec);
                    if (empty($userid)) {
                        continue;
                    }
                    if (isset($this->innerstate[$userid])) {
                        $mod = isset($this->innerstate[$userid]['module']) ? $this->innerstate[$userid]['module']['id'] : 0;
                        if ($mod && ($this->innerstate[$userid]['module']['id'] != $this->get_rec_module($rec) ||
                               $this->innerstate[$userid]['course']['id'] != $this->get_rec_course($rec))) {
                            $this->user_activity_add_session($this->get_rec_userid($rec), $this->innerstate[$userid]['course']['id'],
                                   $mod, $this->innerstate[$userid]['module']['start'], $lasttime);
                            unset($this->innerstate[$userid]['module']);
                        }
                        if ($this->innerstate[$userid]['course']['id'] != $this->get_rec_course($rec)) {
                            $this->user_activity_add_session($userid, $this->innerstate[$userid]['course']['id'], 0,
                                    $this->innerstate[$userid]['course']['start'], $lasttime);
                            unset($this->innerstate[$userid]);
                        }
                    }
                    if (!isset($this->innerstate[$userid]) && ($courseid = $this->get_rec_course($rec))) {
                        $this->innerstate[$userid] = array('course' => array('id' => $courseid, 'start' => $lasttime));
                    }
                    if (!isset($this->innerstate[$userid]['module']) && ($mod = $this->get_rec_module($rec))) {
                        $this->innerstate[$userid]['module'] = array('id' => $mod, 'start' => $lasttime);
                    }
                }
                if ($this->is_stop_rec($rec)) {
                    $userid = $this->get_rec_userid($rec);
                    if (isset($this->innerstate[$userid])) {
                        $mod = isset($this->innerstate[$userid]['module']) ? $this->innerstate[$userid]['module']['id'] : 0;
                        if ($mod) {
                            $this->user_activity_add_session($userid, $this->innerstate[$userid]['course']['id'], $mod,
                                    $this->innerstate[$userid]['module']['start'], $lasttime);
                        }
                        $this->user_activity_add_session($userid, $this->innerstate[$userid]['course']['id'], 0,
                                $this->innerstate[$userid]['course']['start'], $lasttime);
                        unset($this->innerstate[$userid]);
                    }
                }
            }
            $rs->close();
        }

        // flush session data
        foreach ($this->innerstate as $userid => $innerstate) {
            $mod = isset($innerstate['module']) ? $innerstate['module']['id'] : 0;
            if ($mod) {
                $this->user_activity_add_session($userid, $innerstate['course']['id'], $mod, $innerstate['module']['start'], $lasttime);
            }
            $this->user_activity_add_session($userid, $innerstate['course']['id'], 0, $innerstate['course']['start'], $lasttime);
        }

        $this->state['starttime'] = $endtime;
        $lasttime = $DB->get_field_select(MDL_LOG_TABLE, 'MAX(timecreated)', 'TRUE');
        $totalrec = $DB->get_field_select(MDL_LOG_TABLE, 'MAX(id)', 'timecreated < ?', array($lasttime));
        $totalrec = max($totalrec, $endrec);
        return array($endrec - $this->state['startrec'], $totalrec ? ($totalrec - $this->state['startrec']) : 0);
    }

    /**
     * Save the task state for later continuation
     */
    public function user_activity_task_save() {
        mtrace('* over time limit -- saving state and pausing');
        set_config('state', serialize($this->state), 'eliscore_etl');
    }

    /**
     * Finish a task
     */
    public function user_activity_task_finish() {
        mtrace('* completed');
        set_config('last_run', $this->state['starttime'], 'eliscore_etl');
        set_config('state', 0, 'eliscore_etl'); // WAS: null but not allowed in config_plugins
    }

    /**
     * Callback to save the state of the ETL when the script is terminated
     */
    public function save_current_etl_state() {
        // Save the current state.
        $this->user_activity_task_save();

        // Clear blocking.
        $this->set_etl_task_blocked(0);

        exit(0);
    }

    /**
     * Set blocked value for ETL cron.
     *
     * @param int $secs The value in seconds to set blocked time.
     * @uses $DB
     */
    public function set_etl_task_blocked($secs) {
        global $DB;

        $task = $DB->get_record('local_eliscore_sched_tasks', array('plugin' => 'eliscore_etl'));
        $task->blocked = $secs;
        $DB->update_record('local_eliscore_sched_tasks', $task);
    }
}

/**
 * Run the ETL user activity cron.
 *
 * @param string $taskname The task name
 * @param int    $duration The length of time in seconds the cron is to run for
 * @param object $etlobj The ETL user activity object
 */
function user_activity_etl_cron($taskname = '', $duration = 0, &$etlobj = null) {
    $etlcrondisabled = get_config('eliscore_etl', 'etl_disabled');
    if (!empty($etlcrondisabled)) {
        return;
    }
    if ($etlobj === null) {
        $etlobj = new eliscore_etl_useractivity($duration);
    }
    // error_log("user_activity_etl_cron('{$taskname}', {$duration}, etlobj)");
    $etlobj->cron();
}
