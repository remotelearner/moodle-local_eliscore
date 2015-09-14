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
require_once($CFG->dirroot.'/local/eliscore/accesslib.php');
require_once($CFG->dirroot.'/user/profile/lib.php');
require_once($CFG->dirroot.'/local/eliscore/lib/filtering/userprofiledatetime.php');

/**
 * Test the userprofiledatetime filter.
 * @group local_eliscore
 */
class userprofiledatetime_filter_test extends elis_database_test {

    /**
     * Dataprovier for creating/verifying userprofiledatetime filter.
     * @return array Test data.
     * format:
     *          array( array(user_info_field), (numeric)userdatetime_field_value, (numeric)datebefore, (numeric)dateafter, (bool|string)includenever )
     */
    public function userprofiledatetime_filter_data() {
        return array(
            'nullresult' =>
                array( // null result.
                        array( // user_info_field.
                            'shortname' => 'test_userprofiledatetime',
                            'name' => 'Test Date/Time',
                            'datatype' => 'datetime',
                            'description' => '<p>Test Date/Time Filter Description</p>',
                            'descriptionformat' => '1',
                            'categoryid' => '1',
                            'sortorder' => '3',
                            'required' => '1',
                            'locked' => '0',
                            'visible' => '2',
                            'forceunique' => false,
                            'signup' => '0',
                            'defaultdata' => '', // no default
                            'defaultdataformat' => '0', // TBD?
                            'param1' => '1971',
                            'param2' => '2038',
                            'param3' => '0',
                            'param4' => null,
                            'param5' => null,
                        ),
                        null , // userdatetime_field_value.
                        '' , // datebefore.
                        '' , // dateafter.
                        '' , // includenever.
                ),
            'neverincluded' =>
                array( // test never_included
                        array( // user_info_field.
                            'shortname' => 'test_userprofiledatetime',
                            'name' => 'Test Date/Time',
                            'datatype' => 'datetime',
                            'description' => '<p>Test Date/Time Filter Description</p>',
                            'descriptionformat' => '1',
                            'categoryid' => '1',
                            'sortorder' => '3',
                            'required' => '1',
                            'locked' => '0',
                            'visible' => '2',
                            'forceunique' => false,
                            'signup' => '0',
                            'defaultdata' => '', // no default
                            'defaultdataformat' => '0',
                            'param1' => '1971',
                            'param2' => '2038',
                            'param3' => '0',
                            'param4' => null,
                            'param5' => null,
                        ),
                        '0', // userdatetime_field_value.
                        '3', // datebefore.
                        '2', // dateafter.
                        true, // includenever.
                ),
            'neverincluded_eq_default' =>
                array( // test never_included == default
                        array( // user_info_field.
                            'shortname' => 'test_userprofiledatetime',
                            'name' => 'Test Date/Time',
                            'datatype' => 'datetime',
                            'description' => '<p>Test Date/Time Filter Description</p>',
                            'descriptionformat' => '1',
                            'categoryid' => '1',
                            'sortorder' => '3',
                            'required' => '1',
                            'locked' => '0',
                            'visible' => '2',
                            'forceunique' => false,
                            'signup' => '0',
                            'defaultdata' => '0',
                            'defaultdataformat' => '0',
                            'param1' => '1971',
                            'param2' => '2038',
                            'param3' => '0',
                            'param4' => null,
                            'param5' => null,
                        ),
                        null, // userdatetime_field_value.
                        '3', // datebefore.
                        '2', // dateafter.
                        true, // includenever.
                ),
            'neverincluded_neq_default' =>
                array( // test never_included != default
                        array( // user_info_field.
                            'shortname' => 'test_userprofiledatetime',
                            'name' => 'Test Date/Time',
                            'datatype' => 'datetime',
                            'description' => '<p>Test Date/Time Filter Description</p>',
                            'descriptionformat' => '1',
                            'categoryid' => '1',
                            'sortorder' => '3',
                            'required' => '1',
                            'locked' => '0',
                            'visible' => '2',
                            'forceunique' => false,
                            'signup' => '0',
                            'defaultdata' => '0',
                            'defaultdataformat' => '0',
                            'param1' => '1971',
                            'param2' => '2038',
                            'param3' => '0',
                            'param4' => null,
                            'param5' => null,
                        ),
                        null, // userdatetime_field_value.
                        '3', // datebefore.
                        '2', // dateafter.
                        false, // includenever.
                ),
            'default_a' =>
                array( // test default A
                        array( // user_info_field.
                            'shortname' => 'test_userprofiledatetime',
                            'name' => 'Test Date/Time',
                            'datatype' => 'datetime',
                            'description' => '<p>Test Date/Time Filter Description</p>',
                            'descriptionformat' => '1',
                            'categoryid' => '1',
                            'sortorder' => '3',
                            'required' => '1',
                            'locked' => '0',
                            'visible' => '2',
                            'forceunique' => false,
                            'signup' => '0',
                            'defaultdata' => '2',
                            'defaultdataformat' => '0',
                            'param1' => '1971',
                            'param2' => '2038',
                            'param3' => '0',
                            'param4' => null,
                            'param5' => null,
                        ),
                        null, // userdatetime_field_value.
                        '3', // datebefore.
                        '1', // dateafter.
                        false, // includenever.
                ),
            'default_b' =>
                array( // test default B
                        array( // user_info_field.
                            'shortname' => 'test_userprofiledatetime',
                            'name' => 'Test Date/Time',
                            'datatype' => 'datetime',
                            'description' => '<p>Test Date/Time Filter Description</p>',
                            'descriptionformat' => '1',
                            'categoryid' => '1',
                            'sortorder' => '3',
                            'required' => '1',
                            'locked' => '0',
                            'visible' => '2',
                            'forceunique' => false,
                            'signup' => '0',
                            'defaultdata' => '2',
                            'defaultdataformat' => '0',
                            'param1' => '1971',
                            'param2' => '2038',
                            'param3' => '0',
                            'param4' => null,
                            'param5' => null,
                        ),
                        null, // userdatetime_field_value.
                        '3', // datebefore.
                        '1', // dateafter.
                        true, // includenever.
                ),
            'default_c' =>
                array( // test default C
                        array( // user_info_field.
                            'shortname' => 'test_userprofiledatetime',
                            'name' => 'Test Date/Time',
                            'datatype' => 'datetime',
                            'description' => '<p>Test Date/Time Filter Description</p>',
                            'descriptionformat' => '1',
                            'categoryid' => '1',
                            'sortorder' => '3',
                            'required' => '1',
                            'locked' => '0',
                            'visible' => '2',
                            'forceunique' => false,
                            'signup' => '0',
                            'defaultdata' => '5',
                            'defaultdataformat' => '0',
                            'param1' => '1971',
                            'param2' => '2038',
                            'param3' => '0',
                            'param4' => null,
                            'param5' => null,
                        ),
                        null, // userdatetime_field_value.
                        '3', // datebefore.
                        '1', // dateafter.
                        true, // includenever.
                ),
            'filter_a' =>
                array( // test filter A
                        array( // user_info_field.
                            'shortname' => 'test_userprofiledatetime',
                            'name' => 'Test Date/Time',
                            'datatype' => 'datetime',
                            'description' => '<p>Test Date/Time Filter Description</p>',
                            'descriptionformat' => '1',
                            'categoryid' => '1',
                            'sortorder' => '3',
                            'required' => '1',
                            'locked' => '0',
                            'visible' => '2',
                            'forceunique' => false,
                            'signup' => '0',
                            'defaultdata' => '1',
                            'defaultdataformat' => '0',
                            'param1' => '1971',
                            'param2' => '2038',
                            'param3' => '0',
                            'param4' => null,
                            'param5' => null,
                        ),
                        '3', // userdatetime_field_value.
                        '4', // datebefore.
                        '2', // dateafter.
                        false, // includenever.
                ),
            'filter_b' =>
                array( // test filter B
                        array( // user_info_field.
                            'shortname' => 'test_userprofiledatetime',
                            'name' => 'Test Date/Time',
                            'datatype' => 'datetime',
                            'description' => '<p>Test Date/Time Filter Description</p>',
                            'descriptionformat' => '1',
                            'categoryid' => '1',
                            'sortorder' => '3',
                            'required' => '1',
                            'locked' => '0',
                            'visible' => '2',
                            'forceunique' => false,
                            'signup' => '0',
                            'defaultdata' => '1',
                            'defaultdataformat' => '0',
                            'param1' => '1971',
                            'param2' => '2038',
                            'param3' => '0',
                            'param4' => null,
                            'param5' => null,
                        ),
                        '3', // userdatetime_field_value.
                        '4', // datebefore.
                        '2', // dateafter.
                        true, // includenever.
                ),
            'filter_c' =>
                array( // test filter C
                        array( // user_info_field.
                            'shortname' => 'test_userprofiledatetime',
                            'name' => 'Test Date/Time',
                            'datatype' => 'datetime',
                            'description' => '<p>Test Date/Time Filter Description</p>',
                            'descriptionformat' => '1',
                            'categoryid' => '1',
                            'sortorder' => '3',
                            'required' => '1',
                            'locked' => '0',
                            'visible' => '2',
                            'forceunique' => false,
                            'signup' => '0',
                            'defaultdata' => '1',
                            'defaultdataformat' => '0',
                            'param1' => '1971',
                            'param2' => '2038',
                            'param3' => '0',
                            'param4' => null,
                            'param5' => null,
                        ),
                        '3', // userdatetime_field_value.
                        '5', // datebefore.
                        '4', // dateafter.
                        true, // includenever.
                )
        );
    }

    /**
     * Test userprofiledatetime filter.
     *
     * @param object $mfielddata Data to create the initial moodle profile with.
     * @param int|null $usercfvalue the value to set for the user's datatime user profile field.
     * @param string $datebefore the numeric datebefore filter setting.
     * @param string $dateafter the numeric dateafter filter setting.
     * @param bool $includenever the filter setting.
     s @dataProvider userprofiledatetime_filter_data
     */
    public function test_userprofiledatetime_filter($mfielddata, $usercfvalue, $datebefore, $dateafter, $includenever) {
        global $DB;
        $expected = null;
        $mfieldid = $DB->insert_record('user_info_field', (object)$mfielddata);
        $user = $this->getDataGenerator()->create_user();
        if (!empty($datebefore) || !empty($dateafter)) {
            $expected = false;
            if (is_numeric($usercfvalue)) {
                // $user->{$mfielddata['shortname']} = $usercfvalue;
                // profile_save_data($user); // TBD: flakey!
                $DB->insert_record('user_info_data', (object)array(
                    'userid' => $user->id,
                    'fieldid' => $mfieldid,
                    'data' => $usercfvalue,
                    'dataformat' => '0'));
                $expected = ($usercfvalue >= $dateafter && $usercfvalue <= $datebefore) || ($includenever && $usercfvalue == 0);
            }
            if (is_numeric($mfielddata['defaultdata'])) {
                $expected = $expected || (($mfielddata['defaultdata'] >= $dateafter && $mfielddata['defaultdata'] <= $datebefore) ||
                        ($includenever && $mfielddata['defaultdata'] == 0));
            }
        }
        $userprofiledatetimefilter = new generalized_filter_userprofiledatetime('uniqueid', '', 'data', 'Date/Time filter:', false, 'data', array(
            'never_included' => $includenever,
            'fieldid' => $mfieldid,
            'tables' => array('user' => 'muser')));
        $filter = $userprofiledatetimefilter->get_sql_filter(array('before' => $datebefore, 'after' => $dateafter, 'never' => $includenever));
        if (empty($filter)) {
            $this->assertEquals($expected, null, 'empty filter');
        } else {
            $sql = "SELECT id
                      FROM {user} muser
                     WHERE {$filter[0]}";
            $results = $DB->record_exists_sql($sql, $filter[1]);
            if ($results != $expected) {
                ob_start();
                var_dump($DB->get_records('user_info_data'));
                $tmp = ob_get_contents();
                ob_end_clean();
                error_log("filter_sql => {$filter[0]}; user_info_data => {$tmp}");
            }
            $this->assertEquals($expected, $results);
        }
    }
}
