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
 * @copyright  (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

require_once ($CFG->libdir.'/formslib.php');
require_once ($CFG->dirroot.'/local/eliscore/lib/schedulingtraits.php');
require_once ($CFG->dirroot.'/local/eliscore/lib/form/timeselector.php');

/**
 * Javascript files required to convert enter from form cancel to next step.
 */
function require_js_files() {
    global $PAGE;
    $PAGE->requires->js('/lib/javascript-static.js'); // addonload()
    $PAGE->requires->js('/local/eliscore/js/entertonext.js');
}

/**
 * Generic scheduling step form.
 */
class scheduling_form_step_schedule extends moodleform {
    /**
     * The form definition.
     */
    function definition() {
        global $OUTPUT, $PAGE;
        require_js_files();
        $mform =& $this->_form;

        $page = $this->_customdata;
        $workflow = $page->workflow;
        // Get the workflow data for the timezone to keep the time_selector in line
        $workflowdata = $workflow->unserialize_data(array());
        if (!isset($workflowdata['recurrencetype'])) {
            $workflowdata['recurrencetype'] = empty($workflowdata['period']) ? 'simple' : 'period';
        }

        $titles = $page->get_schedule_step_title();
        if (!is_array($titles)) {
            $titles = array($titles);
        }
        foreach ($titles as $title) {
            $mform->addElement('html', "<h2>$title</h2>");
        }

        $mform->addElement('html', '<div class="scheduleform">');
        if (!empty($page->schedule_period)) {
            // Accordion implementation
            $mform->addElement('html', '<div id="accordion">');
            $mform->addElement('html', '<div>');
            $mform->addElement('html', '<h3>');
            $mform->addElement('html', '<a href="#">'.get_string('advancedscheduling', 'local_eliscore').'</a>');
            $mform->addElement('html', '</h3>');
        }

        $mform->addElement('html', '<div>');
        $mform->addElement('html', '<div class="advancedcalendar">');

        // Add javascript function to toggle the simple/recurring calendar elements
        // Also add a listener to show/hide the simple/calendar elements on page load
        $mform->addElement('html', '<script type="text/javascript">
            function switchCalendar() {
                var showHide = document.getElementsByName("recurrencetype");
                var simple = document.getElementById("id_simplerecurrencegroup");
                var calendar = document.getElementById("id_calendarrecurrencegroup");
                var simplestate = simple.className.indexOf("collapsed") >= 0 ? "collapsed" : "";
                var calendarstate = calendar.className.indexOf("collapsed") >= 0 ? "collapsed" : "";
                if (showHide["0"].checked) {
                    if (showHide["0"].value == \'calendar\') {
                        simple.className = "accesshide collapsible "+simplestate;
                        calendar.className = "clearfix collapsible "+calendarstate;
                    } else {
                        simple.className = "clearfix collapsible "+simplestate;
                        calendar.className = "accesshide collapsible "+calendarstate;
                    }
                } else {
                    if (showHide["0"].value == \'simple\') {
                        simple.className = "accesshide collapsible "+simplestate;
                        calendar.className = "clearfix collapsible "+calendarstate;
                    } else {
                        simple.className = "clearfix collapsible "+simplestate;
                        calendar.className = "accesshide collapsible "+calendarstate;
                    }
                }
            }
            function initCalendar() {
                YUI().use("yui2-event", function(Y) {
                    var YAHOO = Y.YUI2;
                    YAHOO.util.Event.onDOMReady(switchCalendar());
                });
            }
            YUI().use("yui2-event", function(Y) {
                var YAHOO = Y.YUI2;
                YAHOO.util.Event.onDOMReady(initCalendar);
            });
        </script>');

        $mform->addElement('hidden', '_wfid', $workflow->id);
        $mform->setType('_wfid', PARAM_INT);
        $currentstep = $page->get_current_step();
        $mform->addElement('hidden', '_step', $currentstep); // TBD or $page->get_next_step(?)
        $mform->setType('_step', PARAM_TEXT);
        $mform->addElement('hidden', 'action', 'save');
        $mform->setType('action', PARAM_TEXT);

        $choices = get_list_of_timezones();
        $choices['99'] = get_string('serverlocaltime');
        $mform->addElement('select', 'timezone', get_string('timezone'), $choices);
        $mform->setDefault('timezone', 99);

        $group = array();
        $group[] = $mform->createElement('radio', 'starttype', '', get_string('now', 'local_eliscore'), 0);
        // Add onclick action to toggle the calendar
        $mform->_attributes['onclick'] = 'switchCalendar();';
        $group[] = $mform->createElement('radio', 'starttype', '', get_string('time_on', 'local_eliscore'), 1);
        // Add onclick action to toggle the calendar
        $mform->_attributes['onclick'] = 'switchCalendar();';
        //Set date options: timezone = 0 so it doesn't adjust the time ...
        $date_options = array('timezone' => 0, 'optional' => false, 'startyear' => userdate(time(), '%Y', -13, false), 'stopyear' => 2038, 'applydst' => false);
        $group[] = $mform->createElement('date_time_selector', 'startdate', '', $date_options);
        $mform->addGroup($group, 'starttype', get_string('start', 'local_eliscore'), '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', false);
        $mform->setDefault('starttype', 0);
        // $mform->addRule('starttype', get_string('required_field', 'local_eliscore', get_string('start', 'local_eliscore')), 'required', null, 'client');
        $mform->disabledIf('startdate', 'starttype', 'neq', 1);
        $mform->disabledIf('startdate[day]', 'starttype', 'neq', 1);
        $mform->disabledIf('startdate[month]', 'starttype', 'neq', 1);
        $mform->disabledIf('startdate[year]', 'starttype', 'neq', 1);
        $mform->disabledIf('startdate[hour]', 'starttype', 'neq', 1);
        $mform->disabledIf('startdate[minute]', 'starttype', 'neq', 1);

        $group = array();
        $group[] = $mform->createElement('radio', 'recurrencetype', '', get_string('simple_recurrence', 'local_eliscore'), elisschedulingworkflowtrait::$RECURRENCE_SIMPLE);
        $group[] = $mform->createElement('radio', 'recurrencetype', '', get_string('calendar_recurrence', 'local_eliscore'), elisschedulingworkflowtrait::$RECURRENCE_CALENDAR);
        $mform->addGroup($group, 'recurrencetype', get_string('recurrence', 'local_eliscore'), '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', false);
        $mform->setDefault('recurrencetype', elisschedulingworkflowtrait::$RECURRENCE_SIMPLE);
        // $mform->addRule('recurrencetype', get_string('required_field', 'local_eliscore', get_string('recurrence', 'local_eliscore')), 'required', null, 'client');

        $mform->addElement('header', 'simplerecurrencegroup', get_string('simple_recurrence_settings', 'local_eliscore'));
        $group = array();
        $group[] = $mform->createElement('radio', 'runtype', '', get_string('indefinitely', 'local_eliscore'), 0);
        $group[] = $mform->createElement('static', '', '', '<br />');
        $group[] = $mform->createElement('radio', 'runtype', '', get_string('until', 'local_eliscore'), 1);
        $group[] = $mform->createElement('date_selector', 'enddate', '', $date_options);
        $group[] = $mform->createElement('static', '', '', '<br />');
        $group[] = $mform->createElement('radio', 'runtype', '', '', 2);
        $group[] = $mform->createElement('text', 'runsremaining', '', array('size' => 2));
        $group[] = $mform->createElement('static', '', '', get_string('times', 'local_eliscore'));
        $group[] = $mform->createElement('static', '', '', '<br />&nbsp;&nbsp;&nbsp;&nbsp;'.get_string('every', 'local_eliscore'));
        $group[] = $mform->createElement('text', 'frequency', '', array('size' => 2));
        $options = array(
            elisschedulingworkflowtrait::$FREQ_MIN   => get_string('freq_minutes', 'local_eliscore'),
            elisschedulingworkflowtrait::$FREQ_HOUR  => get_string('freq_hours', 'local_eliscore'),
            elisschedulingworkflowtrait::$FREQ_DAY   => get_string('freq_days', 'local_eliscore'),
            elisschedulingworkflowtrait::$FREQ_MONTH => get_string('freq_months', 'local_eliscore'),
        );
        $group[] = $mform->createElement('select', 'frequencytype', '', $options);
        $mform->addGroup($group, 'runtype', get_string('runtype', 'local_eliscore'), '', false);
        $mform->disabledIf('enddate', 'runtype', 'neq', 1);
        $mform->disabledIf('enddate[day]', 'runtype', 'neq', 1);
        $mform->disabledIf('enddate[month]', 'runtype', 'neq', 1);
        $mform->disabledIf('enddate[year]', 'runtype', 'neq', 1);
        $mform->disabledIf('runsremaining', 'runtype', 'neq', 2);
        $mform->setType('runsremaining', PARAM_INT);
        // $mform->setDefault('runsremaining', 1);
        $mform->disabledIf('frequency', 'runtype', 'neq', 2);
        $mform->disabledIf('frequencytype', 'runtype', 'neq', 2);
        $mform->setType('frequency', PARAM_INT);
        $mform->setDefault('frequency', 1);
        $mform->setDefault('frequencytype', elisschedulingworkflowtrait::$FREQ_DAY);

        $mform->addElement('header', 'calendarrecurrencegroup', get_string('calendar_recurrence_settings', 'local_eliscore'));
        $mform->addElement('date_selector', 'calenddate', get_string('enddate', 'local_eliscore'), array('optional' => true, 'timezone' => 0, 'applydst' => false));

        //Set timezone so it doesn't adjust the time
        $tsoptions = array('timezone' => '0', 'year' => 1971,
                           'applydst' => false);
        $mform->addElement('time_selector', 'time', get_string('time'), $tsoptions);

        $group = array();
        $group[] = $mform->createElement('radio', 'caldaystype', '', get_string('everyday', 'local_eliscore'), 0);
        $group[] = $mform->createElement('static', '', '', '<br />');
        $group[] = $mform->createElement('radio', 'caldaystype', '', get_string('weekdays', 'local_eliscore'), 1);
        $group[] = $mform->createElement('static', '', '', '<br />&nbsp;&nbsp;&nbsp;&nbsp;');
        $group[] = $mform->createElement('checkbox', 'dayofweek[1]', '', get_string('mon', 'calendar'));
        $group[] = $mform->createElement('checkbox', 'dayofweek[2]', '', get_string('tue', 'calendar'));
        $group[] = $mform->createElement('checkbox', 'dayofweek[3]', '', get_string('wed', 'calendar'));
        $group[] = $mform->createElement('checkbox', 'dayofweek[4]', '', get_string('thu', 'calendar'));
        $group[] = $mform->createElement('checkbox', 'dayofweek[5]', '', get_string('fri', 'calendar'));
        $group[] = $mform->createElement('checkbox', 'dayofweek[6]', '', get_string('sat', 'calendar'));
        $group[] = $mform->createElement('checkbox', 'dayofweek[7]', '', get_string('sun', 'calendar'));
        $group[] = $mform->createElement('static', '', '', '<br />');
        $group[] = $mform->createElement('radio', 'caldaystype', '', get_string('monthdays', 'local_eliscore'), 2);
        $group[] = $mform->createElement('text', 'monthdays', '', array('size' => 6));

        $mform->addGroup($group, 'daytype', get_string('days', 'local_eliscore'), '', false);
        $mform->disabledIf('dayofweek[1]', 'caldaystype', 'neq', 1);
        $mform->disabledIf('dayofweek[2]', 'caldaystype', 'neq', 1);
        $mform->disabledIf('dayofweek[3]', 'caldaystype', 'neq', 1);
        $mform->disabledIf('dayofweek[4]', 'caldaystype', 'neq', 1);
        $mform->disabledIf('dayofweek[5]', 'caldaystype', 'neq', 1);
        $mform->disabledIf('dayofweek[6]', 'caldaystype', 'neq', 1);
        $mform->disabledIf('dayofweek[7]', 'caldaystype', 'neq', 1);
        $mform->disabledIf('monthdays', 'caldaystype', 'neq', 2);
        $mform->setType('monthdays', PARAM_TEXT);

        $group = array();
        $group[] = $mform->createElement('advcheckbox', 'allmonths', '', get_string('all'), 1);
        $group[] = $mform->createElement('static', '', '', '<br />');
        $group[] = $mform->createElement('checkbox', 'month[1]', '', strftime('%b', mktime(0, 0, 0, 1, 1)));
        $group[] = $mform->createElement('checkbox', 'month[2]', '', strftime('%b', mktime(0, 0, 0, 2, 1)));
        $group[] = $mform->createElement('checkbox', 'month[3]', '', strftime('%b', mktime(0, 0, 0, 3, 1)));
        $group[] = $mform->createElement('checkbox', 'month[4]', '', strftime('%b', mktime(0, 0, 0, 4, 1)));
        $group[] = $mform->createElement('checkbox', 'month[5]', '', strftime('%b', mktime(0, 0, 0, 5, 1)));
        $group[] = $mform->createElement('checkbox', 'month[6]', '', strftime('%b', mktime(0, 0, 0, 6, 1)));
        $group[] = $mform->createElement('static', '', '', '<br />');
        $group[] = $mform->createElement('checkbox', 'month[7]', '', strftime('%b', mktime(0, 0, 0, 7, 1)));
        $group[] = $mform->createElement('checkbox', 'month[8]', '', strftime('%b', mktime(0, 0, 0, 8, 1)));
        $group[] = $mform->createElement('checkbox', 'month[9]', '', strftime('%b', mktime(0, 0, 0, 9, 1)));
        $group[] = $mform->createElement('checkbox', 'month[10]', '', strftime('%b', mktime(0, 0, 0, 10, 1)));
        $group[] = $mform->createElement('checkbox', 'month[11]', '', strftime('%b', mktime(0, 0, 0, 11, 1)));
        $group[] = $mform->createElement('checkbox', 'month[12]', '', strftime('%b', mktime(0, 0, 0, 12, 1)));
        $mform->addGroup($group, 'month', get_string('months', 'local_eliscore'), '', false);
        for ($i = 1; $i <= 12; ++$i) {
            $mform->disabledIf("month[{$i}]", 'allmonths', 'checked');
        }

        $mform->addElement('html', '</fieldset>'); // Moodle/formslib bug!
        $mform->addElement('html', '</div>'); // advancedcalendar
        if (!empty($page->schedule_period)) {
            $mform->addElement('html', '</div>');
            $mform->addElement('html', '<div>');
            $mform->addElement('html', '<h3>');
            $mform->addElement('html', '<a href="#">'.get_string('periodscheduling', 'local_eliscore').'</a>');
            $mform->addElement('html', '</h3>');
            $mform->addElement('html', '<div class="periodsection">');
            // Period setting
            $periodlabel = get_string('form_period', 'local_eliscore').'&nbsp;&nbsp;'; // Must leave room for psuedo-required image.
            $periodelem = $mform->createElement('text', 'period', $periodlabel, array('id' => 'idperiod'));
            $mform->addElement($periodelem);
            $mform->setType('period', PARAM_TEXT);
            $mform->addHelpButton('period', 'form_period', 'local_eliscore');
            $mform->addElement('html', '</div>'); // Class: accordion.
            $mform->addElement('html', '<script type="text/javascript">
                    $(function() {
                        // Accordion
                        $("#accordion").accordion({ header: "h3",
                                icons: {
                                    header: "ui-icon-collapsed",
                                    activeHeader: "ui-icon-expanded"
                                },
                                active: (document.getElementById("idperiod").value != "") ? 1 : '.
                                        ((empty($workflowdata['period']) && $workflowdata['recurrencetype'] != 'period') ? '0' : '1').',
                                heightStyle: "content",
                                activate: function(event, ui) {
                                    var active = $("#accordion").accordion("option", "active");
                                    if (active != "1") {
                                        document.getElementById("idperiod").value = "";
                                    } else if (document.getElementById("idperiod").value == "") {
                                        document.getElementById("idperiod").value = "dhm";
                                    }
                                }
                        });
                    });
                </script>');
            $mform->addElement('html', '</div>'); // Class: periodsection.
        }
        $mform->addElement('html', '</div>'); // Class: scheduleform.

        $steps = $workflow->get_steps();
        $prevstep = null;
        foreach ($steps as $key => $step) {
            if ($key == $currentstep) {
                break;
            }
            $prevstep = $key;
        }
        if (($nextstep = next($steps))) {
            $nextstep = key(current($steps));
        }
        if ($nextstep === false) {
            $nextstep = workflow::STEP_FINISH;
        }
        workflowpage::add_navigation_buttons($mform, $prevstep, workflow::STEP_NEXT, ($nextstep == workflow::STEP_FINISH) ? get_string('save', 'repository') : null);
    }

    /**
     * Set error message for a form element
     *
     * @param     string    $element    Name of form element to set error for
     * @param     string    $message    Error message, if empty then removes the current error message
     * @since     1.0
     * @access    public
     * @return    void
     */
    function setElementError($element, $message = null) {
        $this->_form->setElementError($element, $message);
    }
}
