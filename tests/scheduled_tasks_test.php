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
 * @copyright  (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

require_once(dirname(__FILE__).'/../test_config.php');
global $CFG;
require_once($CFG->dirroot.'/local/eliscore/lib/setup.php');
require_once(elis::lib('data/data_object.class.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once(elis::lib('tasklib.php'));

/**
 * Class to test scheduled tasks.
 * @group local_eliscore
 */
class scheduled_tasks_testcase extends elis_database_test {
    /**
     * Validate scheduled tasks.
     */
    public function test_elis_tasks_get_cached() {
        $dataset = $this->createCsvDataSet(array(
            'local_eliscore_sched_tasks' => elis::component_file('eliscore', 'tests/fixtures/elis_scheduled_tasks.csv')
        ));
        $this->loadDataSet($dataset);

        $cachedtasks = elis_tasks_get_cached('elis_program');

        $this->assertNotEmpty($cachedtasks);
        $this->assertInternalType('array', $cachedtasks);
        $this->assertArrayHasKey('s:7:"pm_cron";', $cachedtasks);
        $this->assertNotEmpty($cachedtasks['s:7:"pm_cron";']);
        $this->assertInternalType('array', $cachedtasks['s:7:"pm_cron";']);
    }

    /**
     * test_elis_tasks_cron_next_run_time() data providea
     * @return array the test data
     */
    public function elis_tasks_cron_next_run_time_data() {
        return array(
                array(array('minute' => '00', 'hour' => '00', 'day' => '*', 'dayofweek' => '*', 'month' => '*', 'timezone' => '99',
                    'lastruntime' => array(2013, 1, 1, 1, 15)), array(2013, 1, 2, 0, 0)),
                array(array('minute' => '00', 'hour' => '00', 'day' => '*', 'dayofweek' => '*', 'month' => '*', 'timezone' => '99',
                    'lastruntime' => array(2012, 2, 19, 13, 13)), array(2012, 2, 20, 0, 0)),
                array(array('minute' => '00', 'hour' => '00', 'day' => '*', 'dayofweek' => '*', 'month' => '*', 'timezone' => '99',
                    'lastruntime' => array(2013, 10, 8, 20, 30)), array(2013, 10, 9, 0, 0)),
                array(array('minute' => '15', 'hour' => '*/12', 'day' => '*', 'dayofweek' => '*', 'month' => '*', 'timezone' => '99',
                    'lastruntime' => array(2013, 10, 8, 20, 15)), array(2013, 10, 9, 0, 15)),
                array(array('minute' => '25', 'hour' => '14', 'day' => '*/2', 'dayofweek' => '*', 'month' => '*', 'timezone' => '99',
                    'lastruntime' => array(2013, 10, 8, 14, 25)), array(2013, 10, 10, 14, 25)),
                array(array('minute' => '55', 'hour' => '16', 'day' => '*', 'dayofweek' => '*', 'month' => '10', 'timezone' => '99',
                    'lastruntime' => array(2013, 10, 31, 16, 55)), array(2014, 10, 1, 16, 55)),
        );
    }

    /**
     * Validate cron_next_run_time() function for tasks with '00' hour and/or minute
     * @param array $job array of cron task parameters
     * @param array $expnextrun the expected next run time
     * @dataProvider elis_tasks_cron_next_run_time_data
     */
    public function test_elis_tasks_cron_next_run_time($job, $expnextrun) {
        $lastrun = $job['lastruntime'];
        $this->assertEquals(make_timestamp($expnextrun[0], $expnextrun[1], $expnextrun[2], $expnextrun[3], $expnextrun[4]),
                cron_next_run_time(make_timestamp($lastrun[0], $lastrun[1], $lastrun[2], $lastrun[3], $lastrun[4]), $job));
    }

    /**
     * Data provider for test_schedule_period_minutes()
     */
    public static function period_minutes_provider() {
        return array(
            array('1x', -1),
            array('1m', 1),
            array('5m', 5),
            array('10m', 10),
            array('1h', HOURSECS/60),
            array('1d', DAYSECS/60),
            array('2d3h4m', DAYSECS/30 + (HOURSECS * 3)/60 + 4),
            array('9m 8d 7h', (DAYSECS * 8)/60 + (HOURSECS * 7)/60 + 9),
            array('9h  8m  7d', (DAYSECS * 7)/60 + (HOURSECS * 9)/60 + 8),
            array('4  d 5h  6m', (DAYSECS * 4)/60 + (HOURSECS * 5)/60 + 6),
            array('7 d 8 h 9 m', (DAYSECS * 7)/60 + (HOURSECS * 8)/60 + 9),
            array('20d23h45m', DAYSECS/3 + (HOURSECS * 23)/60 + 45),
            array('2a3b4c', -1)
        );
    }

    /**
     * Test library function: schedule_period_minutes()
     * @dataProvider period_minutes_provider
     */
    public function test_rlip_schedule_period_minutes($a, $b) {
        $this->assertEquals(schedule_period_minutes($a), $b);
    }

    /**
     * Test that the next runtime is aligned to the correct boundary for 'period' jobs.
     */
    public function test_nextruntimeboundry() {
        $targetstarttime = mktime(12, 0, 0, 1, 1, 2012);    // 12:00.

        $job = array('period' => '5m');
        $nextruntime = cron_next_run_time($targetstarttime, $job);
        $timenow = $nextruntime;
        $nextruntime = cron_next_run_time($nextruntime, $job);
        $this->assertLessThan(60, $nextruntime - ($timenow + 5 * 60)); // hh:05:ss.

        $nextruntime = cron_next_run_time($nextruntime, $job);
        $this->assertLessThan(60, $nextruntime - ($timenow + 10 * 60)); // hh:10:ss.

        $nextruntime = cron_next_run_time($nextruntime, $job);
        $this->assertLessThan(60, $nextruntime - ($timenow + 15 * 60)); // hh:15:ss.
    }
}
