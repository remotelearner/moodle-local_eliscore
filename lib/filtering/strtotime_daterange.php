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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/eliscore/lib/filtering/lib.php');

/**
 * A string to time date range filter.  Displays two text fields.  Allowing the user to specify a specific date range in the past
 * Supported date formats http://www.php.net/manual/en/datetime.formats.php
 */
class generalized_filter_strtotime_daterange extends generalized_filter_type {
    /**
     * From field suffix.
     */
    const FROMFIELDSUFFIX = '_fromfield';

    /**
     * To field suffix.
     */
    const TOFIELDSUFFIX = '_tofield';

    /** @var string From label. */
    protected $fromlabel = '';

    /** @var string To label. */
    protected $tolabel = '';

    /** @var mixed|array from_disable control. */
    protected $from_disable = false;

    /** @var mixed|array to_disable control. */
    protected $to_disable = false;

    /** @var string the date format. */
    protected $date_format = '';

    /** @var int the timezone to use. */
    protected $_timezone = 99;

    /**
     * This constructor calls the parent constructor.
     * @param string $uniqueid A unique identifier for the filter.
     * @param string $alias Alias for the table being filtered on.
     * @param string $name The name of the filter instance.
     * @param string $label The label of the filter instance.
     * @param bool $advanced Advanced form element flag.
     * @param string $field field name (unused).
     * @param array $options An array of options.
     */
    public function __construct($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        $help = !empty($options['help']) ? $options['help'] : array('text', $label, 'local_eliscore');
        parent::__construct($uniqueid, $alias, $name, $label, $advanced, $help);
        $this->fromlabel = isset($options['fromlabel']) ? $options['fromlabel'] : get_string('from', 'moodle');
        $this->tolabel = isset($options['tolabel']) ? $options['tolabel'] : get_string('to', 'moodle');
        $this->from_disable = isset($options['from_disable']) ? $options['from_disable'] : false;
        $this->to_disable = isset($options['to_disable']) ? $options['to_disable'] : false;
        $this->date_format = isset($options['dateformat']) ? $options['dateformat'] : '';
        if (isset($options['timezone'])) {
            $this->_timezone = $options['timezone'];
        }
        $this->intervals = array(
            'days' => get_string('days'),
            'weeks' => get_string('weeks'),
            'months' => strtolower(get_string('months'))
        );
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup.
     */
    public function setupForm(&$mform) {
        $objs = array();
        // Add 'From' field label and text element.
        $objs[] =& $mform->createElement('static', $this->_uniqueid.'_fromfield_label', null, '<table><tr><td>'.$this->fromlabel.'</td><td>');
        $from = $mform->createElement('text', $this->_uniqueid.self::FROMFIELDSUFFIX, null);
        $from->setSize(3);
        $objs[] =& $from;
        $objs[] =& $mform->createElement('static', $this->_uniqueid.'_fromfield_label', null, '</td><td>');
        $objs[] =& $mform->createElement('select', $this->_uniqueid.self::FROMFIELDSUFFIX.'_interval', null, $this->intervals);
        $objs[] =& $mform->createElement('static', $this->_uniqueid.'_fromfield_label', null, '</td><td>');
        $ago = get_string('ago', 'local_eliscore');
        $objs[] =& $mform->createElement('static', $this->_uniqueid.'_break', null, $ago);
        $objs[] =& $mform->createElement('static', $this->_uniqueid.'_fromfield_label', null, '</td></tr>');
        $objs[] =& $mform->createElement('static', $this->_uniqueid.'_tofield_label', null, '<tr><td>'.$this->tolabel.'</td><td>');

        // Add 'To' field label and text element.
        $to = $mform->createElement('text', $this->_uniqueid.self::TOFIELDSUFFIX, null);
        $to->setSize(3);
        $objs[] =& $to;
        $objs[] =& $mform->createElement('static', $this->_uniqueid.'_tofield_label', null, '</td><td>');
        $objs[] =& $mform->createElement('select', $this->_uniqueid.self::TOFIELDSUFFIX.'_interval', null, $this->intervals);
        $objs[] =& $mform->createElement('static', $this->_uniqueid.'_tofield_label', null, '</td><td>');
        $objs[] =& $mform->createElement('static', $this->_uniqueid.'_break', null, $ago);
        $objs[] =& $mform->createElement('static', $this->_uniqueid.'_tofield_label', null, '</td></tr></table>');
        $grp =& $mform->addElement('group', $this->_uniqueid.'_grp', $this->_label, $objs, '', false);

        if (!empty($this->from_disable)) {
            $mform->disabledIf($this->_uniqueid.self::FROMFIELDSUFFIX, $this->from_disable['elem'], $this->from_disable['op'], $this->from_disable['value']);
            $mform->disabledIf($this->_uniqueid.self::FROMFIELDSUFFIX.'_interval', $this->from_disable['elem'], $this->from_disable['op'], $this->from_disable['value']);
        }
        if (!empty($this->to_disable)) {
            $mform->disabledIf($this->_uniqueid.self::TOFIELDSUFFIX, $this->to_disable['elem'], $this->to_disable['op'], $this->to_disable['value']);
            $mform->disabledIf($this->_uniqueid.self::TOFIELDSUFFIX.'_interval', $this->to_disable['elem'], $this->to_disable['op'], $this->to_disable['value']);
        }

        $mform->addGroupRule($this->_uniqueid.'_grp', array(
            $this->_uniqueid.self::FROMFIELDSUFFIX => array(array(get_string('invalidnum', 'error'), 'numeric', null, 'client', false, false)),
            $this->_uniqueid.self::TOFIELDSUFFIX => array(array(get_string('invalidnum', 'error'), 'numeric', null, 'client', false, false))
        ));

        // Add help icon.
        $mform->addHelpButton($this->_uniqueid.'_grp', $this->_filterhelp[0], $this->_filterhelp[1]);
    }

    /**
     * Retrieves data from the form data.
     * @param object $formdata Data submited with the form.
     * @return array|bool array Filter data or false when filter not set.
     */
    public function check_data($formdata) {
        $fromfield = $this->_uniqueid.self::FROMFIELDSUFFIX;
        $tofield = $this->_uniqueid.self::TOFIELDSUFFIX;
        $frominterval = $this->_uniqueid.self::FROMFIELDSUFFIX.'_interval';
        $tointerval = $this->_uniqueid.self::TOFIELDSUFFIX.'_interval';

        $result = array(
            'from'         => 0,
            'frominterval' => (!empty($formdata->$fromfield) && !empty($formdata->$frominterval)) ? $formdata->$frominterval : 'days',
            'to'           => 0,
            'tointerval'   => (!empty($formdata->$tofield) && !empty($formdata->$tointerval)) ? $formdata->$tointerval : 'days'
        );
        if (!isset($formdata->$fromfield) && !isset($formdata->$tofield)) {
            return $result;
        }

        $time = time();
        // Get the user's date and time for their timezone.
        $currentuserdatetime = usergetdate($time);
        // Create a timestamp from the date and midnight time.
        $currentusertimestamp = make_timestamp($currentuserdatetime['year'], $currentuserdatetime['mon'], $currentuserdatetime['mday'],
                0, 0, 0, $this->_timezone);
        // Convert the user's string date and time into a timestamp relative to their current time.
        if (isset($formdata->$fromfield) && is_numeric($formdata->$fromfield)) {
            $strfrom = !empty($formdata->$fromfield) ? $formdata->$fromfield : 0;
            $strfrom .= $result['frominterval'].' ago';
            $fromtimestamp = strtotime($strfrom, $currentusertimestamp);
            $result['from'] = $fromtimestamp;
        }
        if (isset($formdata->$tofield) && is_numeric($formdata->$tofield)) {
            if (!empty($formdata->$tofield)) {
                $strto = $formdata->$tofield;
                $strto .= $result['tointerval'].' ago';
                $totimestamp = strtotime($strto, $currentusertimestamp);
            } else {
                $tointerval = 'now';
                $totimestamp = $time;
            }
            $result['to'] = $totimestamp;
        }

        // Let's make sure the "from" time is before the "to" time.
        if (!empty($result['from']) && !empty($result['to']) && $result['from'] > $result['to']) {
            $result['from'] = $totimestamp;
            $result['frominterval'] = $tointerval;
            $result['to'] = $fromtimestamp;
            $result['tointerval'] = $frominterval;
        }
        return $result;
    }

    /**
     * Get report parameters
     * @param array $data Report parameters
     * @return array Report parameters
     */
    public function get_report_parameters($data) {
        $result = array(
            'from'         => 0,
            'frominterval' => $data['frominterval'],
            'to'           => 0,
            'tointerval'   => $data['tointerval']
        );
        if (isset($data['from'])){
            $result['from'] = $data['from'];
        }
        if (isset($data['to'])){
            $result['to'] = $data['to'];
        }
        return $result;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array  $data filter settings
     * @return array|null the filtering condition with optional parameter array
     *               or null if the filter is disabled
     *               Eg.   return array($sql, array('param1' => $param1 ...));
     */
    public function get_sql_filter($data) {
        static $counter = 0;
        $sql = array();
        $params = array();

        $fullfieldname = $this->get_full_fieldname();
        if (empty($fullfieldname)) {
            return null;
        }

        if (!empty($data['from'])) {
            $paramfrom = 'ex_datefrom'.$counter;
            $sql[] = "{$fullfieldname} >= :{$paramfrom}";
            $params[$paramfrom] = $data['from'];
        }

        if (!empty($data['to'])) {
            $paramto = 'ex_dateto'.$counter;
            $sql[] = "{$fullfieldname} <= :{$paramto}";
            $params[$paramto] = $data['to'];
        }

        if (!empty($sql)) {
            $sql = implode(' AND ', $sql);
        } else {
            $sql = 'TRUE';
        }

        if (!empty($params)) {
            $counter++;
        }

        return array($sql, $params);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label($data) {
        $labelstr = '';
        if (!empty($data['from'])) {
            $from = $data['from'];
            if (is_int($from)) {
                $from = userdate($from, $this->date_format);
            }
            $labelstr .= $this->fromlabel.' '.$from.' ';
        }
        if (!empty($data['to'])) {
            $to = $data['to'];
            if (is_int($to)) {
                $to = userdate($to, $this->date_format);
            }
            $labelstr .= ' '.$this->tolabel.' '.$to;
        }
        if (!empty($labelstr)) {
            $labelstr = get_string('strtodatefilter', 'local_eliscore').': '.$labelstr;
        }
        return $labelstr;
    }
}
