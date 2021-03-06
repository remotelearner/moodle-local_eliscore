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
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

require_once($CFG->dirroot.'/local/eliscore/lib/filtering/lib.php');
require_once($CFG->dirroot.'/local/eliscore/lib/form/autocompletelib.php');

/**
 * Autocomplete Filter
 * @author James McQuillan <james.mcquillan@remote-learner.net>
 */
abstract class generalized_filter_autocomplete_base extends generalized_filter_type {
    /**
     * options for the list values
     */
    public $_options;
    public $_field;
    public $_fields;
    public $_table;
    public $_parent_report;
    public $_label_template;
    public $_selection_enabled = true;
    public $_restriction_sql = '';
    public $_ui = 'popup';
    public $_default_id = '';
    public $_default_label = '';
    public $_popup_title = '';
    public $_required = false;
    public $_useid = false;
    protected $_formdelim = '_';
    public $results_fields = array();
    public $perm_req_for_config = 'local/elisprogram:config';

    /**
     * Constructor
     * @param  string   $name      the name of the filter instance
     * @param  string   $label     the label of the filter instance
     * @param  boolean  $advanced  advanced form element flag
     * @param  string   $field     user table filed name
     * @param  array    $options   select options
     */
    public function __construct($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {

        parent::generalized_filter_type(
                $uniqueid,
                $alias,
                $name,
                $label,
                $advanced,
                (!empty($options['help']) ? $options['help'] : array('simpleselect', $label, 'filters'))
        );

        $this->_field = $field;

        if (!isset($options['report'])) {
            print_error('autocomplete_noreport', 'local_eliscore');
        }
        $this->_parent_report = $options['report'];
        $this->parent_report_instance = php_report::get_default_instance($options['report']);

        if (isset($options['ui']) && in_array($options['ui'],array('inline','popup'),true)) {
            $this->_ui = $options['ui'];
        }

        if (!empty($options['restriction_sql']) && is_string($options['restriction_sql'])) {
            $this->_restriction_sql = $options['restriction_sql'];
        }

        if (!empty($options['popup_title'])) {
            $this->_popup_title = $options['popup_title'];
        }

        if (isset($options['label_template'])) {
            $this->_label_template = $options['label_template'];
        } else {
            $this->_label_template = (!empty($this->_fields[0])) ? $this->_fields[0] : '';
        }

        if (isset($options['selection_enabled']) && is_bool($options['selection_enabled'])) {
            $this->_selection_enabled = $options['selection_enabled'];
        }

        if (!empty($options['defaults']['id'])) {
            $this->_default_id = $options['defaults']['id'];
        }

        if (!empty($options['defaults']['label'])) {
            $this->_default_label = $options['defaults']['label'];
        }

        if (!empty($options['required'])) {
            $this->_required = $options['required'];
        }

        $this->_useid = ($field == 'id');
        $this->load_options($options);
    }

    /**
     * Get the default value.
     *
     * @return mixed Default values or null if none.
     */
    public function get_default() {
        if ($this->_useid) {
            return(empty($this->_default_id) ? null : $this->_default_id);
        } else {
            return(empty($this->_default_label) ? null : $this->_default_label);
        }
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param  object  $mform  A MoodleForm object to setup
     */
    public function setupForm(&$mform) {
        global $CFG;

        $report = $this->_parent_report;
        $filter = $this->_uniqueid;

        $filter_js = array(
            'var ac_show_panel = function( url ) {
                var x = window.open(url, \'newWindow\', \'height=700,width=650,resizable=yes,scrollbars=yes,screenX=50,screenY=50\');
            }
            window.ac_show_panel = ac_show_panel;'
        );

        $filt_action_url_base = $CFG->wwwroot.'/local/eliscore/lib/form/autocomplete.php?report='.$report.'&filter='.$filter;

        if ($this->_ui === 'inline') {

            $filterelements = array();
            $idname = $this->_uniqueid.$this->_formdelim.'id';
            $filterelements[] = $mform->createElement('hidden', $idname, $this->get_default(), array('id' => 'id_'.$idname));

            if ($this->_selection_enabled === true) {

                $search_url = $filt_action_url_base.'&mode=search&q=';
                $config_url = $filt_action_url_base.'&mode=config';

                $main_input_ph = ($this->_selection_enabled === true) ? get_string('filt_autoc_typetosearch','local_eliscore') : '';

                $textentryname = $this->_uniqueid.$this->_formdelim.'textentry';
                $filterelements[] = $mform->createElement('text', $textentryname, $this->_label, array('placeholder' => $main_input_ph));
                if ($this->config_allowed() === true) {
                    $configlink = '<a onclick="ac_show_panel(\''.$config_url.'\');" href="#">'.'<img src='.$CFG->wwwroot.'/local/elisprogram/pix/configuration.png>'.'</a>';
                    $configlinkname = $this->_uniqueid.$this->_formdelim.'configlink';
                    $filterelements[] = $mform->createElement('static', $configlinkname, '', $configlink);
                }
                $filterelements[] = $mform->createElement('html',
                        '<div id="search_results_outer" class="filt_ac_res filt_ac_res_inline">
                            <div id="search_status" class="filt_ac_status filt_ac_status_inline"></div>
                            <div id="search_results"></div>
                        </div>');

                $mform->addElement('group', $this->_uniqueid.'_grp', $this->_label, $filterelements, '', false);
                $mform->setType($this->_uniqueid.$this->_formdelim.'textentry', PARAM_TEXT);

                if ($this->_required) {
                    // This adds red * & that a required form field exists + validation
                    $mform->addGroupRule($this->_uniqueid.'_grp', get_string('required'), 'required', null, 1, 'client');
                    if ($this->_useid) {
                        //$mform->addRule($this->_uniqueid.'_grp_'.$this->_uniqueid.$this->_formdelim.'id', get_string('required'), 'required', null, 'client');
                    }
                }

                $mform->addElement('html',"<link rel='stylesheet' type='text/css' href='{$CFG->wwwroot}/local/eliscore/lib/form/autocomplete.css' />");
                $mform->addElement('html',"<script src='{$CFG->wwwroot}/local/eliscore/js/jquery-1.7.1.min.js'></script>");
                $mform->addElement('html',"<script src='{$CFG->wwwroot}/local/eliscore/lib/form/autocomplete.js'></script>");

                $filter_js[] =
                        "var search_textbox = 'id_{$this->_uniqueid}_textentry';
                        var search_results = 'search_results';
                        var search_status = 'search_status';
                        var search_results_outer = 'search_results_outer';

                        var autocomplete = new autocomplete_ui(search_textbox,search_results,search_status,'{$search_url}','search_results_outer');
                        autocomplete.str_searching = '".get_string('filt_autoc_searching','local_eliscore')."';
                        autocomplete.str_typetosearch = '".get_string('filt_autoc_typetosearch','local_eliscore')."';

                        $('#'+search_textbox).focus().click(function(e){e.stopPropagation();}).keyup(function(e) {
                            if (e.keyCode != 13) {
                                $('#'+search_results_outer).show().click(function(e) { e.stopPropagation() });
                                $('body').click(function(e){ $('#'+search_results_outer).hide(); });
                                var pos = $(this).position();
                                var height = $(this).outerHeight();
                                $('#'+search_results_outer).css('left',pos.left+2).css('top',(pos.top+height+2));
                            }
                        });";

                $defaultval = '';
                $fval = php_report_filtering_get_active_filter_values($this->_parent_report, $this->_uniqueid.'_textentry');
                if (!empty($fval)) {
                    $defaultval = $fval[0]['value'];
                } else if (!empty($this->_default_label)) {
                    $defaultval = $this->_default_label;
                }
                if (!empty($defaultval)) {
                    $mform->setDefault($this->_uniqueid.$this->_formdelim.'textentry', $defaultval);
                }
            } else {
                $textentryname = $this->_uniqueid.$this->_formdelim.'textentry';
                $filterelements[] = $mform->createElement('text', $textentryname, $this->_label, array('disabled' => 'disabled'));
                $mform->addElement('group', $this->_uniqueid.'_grp', $this->_label, $filterelements, '', false);
                $mform->setType($this->_uniqueid.$this->_formdelim.'textentry', PARAM_TEXT);
                if (!empty($this->_default_label)) {
                    $mform->setDefault($this->_uniqueid.$this->_formdelim.'textentry', $this->_default_label);
                }
            }

        } else {

            $popup_link = '<span id="id_'.$this->_uniqueid.'_label"></span> ';
            if ($this->_selection_enabled === true) {
                $popup_link .= '<a onclick="ac_show_panel(\''.$filt_action_url_base.'\');" href="#">'.get_string('filt_autoc_select','local_eliscore').'</a>';
            }

            $mform->addElement('static', 'selector', $this->_label,$popup_link);
            $mform->addElement('html','<div style="display:none;">');
            $mform->addElement('text', $this->_uniqueid.'_labelsave', '');
            $mform->setType($this->_uniqueid.'_labelsave', PARAM_TEXT);
            $mform->addElement('text', $this->_uniqueid, '');
            $mform->setType($this->_uniqueid, PARAM_TEXT);
            if (!empty($this->_default_label)) {
                $mform->setDefault($this->_uniqueid.'_labelsave',$this->_default_label);
            }
            if (!empty($this->_default_id)) {
                $mform->setDefault($this->_uniqueid, $this->_default_id);
            }
            $mform->addElement('html','</div>');
            if (!empty($this->_filterhelp) && is_array($this->_filterhelp) && isset($this->_filterhelp[2])) {
                //$mform->addHelpButton('selector', $this->_filterhelp[0], $this->_filterhelp[2]);
            }

            $filter_js[] =
                'labelsave = document.getElementById(\'id_'.$this->_uniqueid.'_labelsave\');
                labeldisp = document.getElementById(\'id_'.$this->_uniqueid.'_label\');
                if (labelsave != null && labeldisp != null) {
                    labeldisp.innerHTML = labelsave.value;
                }';
        }

        $mform->addElement('html','<script>'.implode("\n\n",$filter_js).'</script>');
    }

    /**
     * Retrieves data from the form data.
     *
     * @param object $formdata Data submited with the form
     * @return array|false Filter data or false when filter not set
     */
    public function check_data($formdata) {
        if ($this->_useid === true) {
            $field = $this->_uniqueid.$this->_formdelim.'id';
        } else {
            $field = $this->_uniqueid.$this->_formdelim.'textentry';
        }

        if (isset($formdata->$field) && $formdata->$field !== '') {
            return array('value'=>(string)$formdata->$field); // TBD (string)
        }

        return false;
    }

    public function get_report_parameters($data) {
        return array('value' => $data['value'],
                     'numeric' => $this->_numeric);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param   array   $data  Filter settings
     * @return  string         Active filter label
     */
    public function get_label($data) {
        $value = $data['value'];

        $a = new object();
        $a->label    = $this->_label;
        $a->value    = '"'.s($value).'"';
        $a->operator = get_string('contains','filters');

        return get_string('selectlabel', 'filters', $a);
    }

    /**
     * Returns the condition to be used with SQL where
     * @param   array   $data  Filter settings
     * @return  string         The filtering condition or null if the filter is disabled
     */
    public function get_sql_filter($data) {
        static $counter = 0;
        $param_name = 'filt_autoc'. $counter++;

        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) { // for manual filter use
            return null;
        }

        if (!$this->_useid) {
            $sql = $full_fieldname.' LIKE :'.$param_name;
            $params = array($param_name => '%'.$data['value'].'%');
        } else {
            $sql = "{$full_fieldname} = :{$param_name}";
            $params = array($param_name => $data['value']);
        }

        return array($sql, $params);
    }

    /**
     * Takes a set of submitted values and retuns this filter's default values
     * for them in the same structure (used to reset the filtering form),
     *
     * @param array $filterdata The submitted filtering data for this filter.
     */
    public function get_default_values($filterdata) {
        // Our data map of field shortnames to values.
        $defaultvalues = array();
        $textentryname = $this->_uniqueid.$this->_formdelim.'textentry';
        $idname = $this->_uniqueid.$this->_formdelim.'id';

        // Set all fields to an empty value.
        foreach ($filterdata as $key => $value) {
            if ($key === $textentryname && !empty($this->_default_label)) {
                $defaultvalues[$key] = $this->_default_label;
            } else if ($key === $idname && !empty($this->_default_id)) {
                $defaultvalues[$key] = $this->_default_id;
            } else {
                $defaultvalues[$key] = '';
            }
        }

        // Return our data mapping.
        return $defaultvalues;
    }

    /* BEGIN autocomplete-specific functions */

    /**
     * Loads the $options array into class properties
     * @param  array  $options  The options array passed into the class
     */
    abstract public function load_options($options);

    /**
     * Gets the labels for each column of the results table.
     * @return  array  An array of strings with values in the same order as $this->get_results_fields();
     */
    abstract public function get_results_headers();

    /**
     * Gets the fields for each column of the results table.
     * @return  array  An array of strings corresponding to members of a SQL result row with values
     *                  in the same order as $this->get_results_headers();
     */
    abstract public function get_results_fields();

    /**
     * Gets the search results for the autocomplete UI
     * Note that this is the SQL used to select a value, not the SQL used in the report SQL
     * @param   string  $q  The query string
     * @return  string      The SQL query
     */
    abstract public function get_search_results($q);

    /**
     * If you want to implement a config form for you autocomplete filter, you can do so by implementing this function
     * This should return an instance of the config form you'd like to use.
     */
    public function get_config_form() {
        die();
    }

    /**
     * If you need to transform the data coming from the config form before it's stored, implement this function
     * @param  stdClass  $configdata  The data from the form
     * @return stdClass               The modified data to go into storage
     */
    public function process_config_data($configdata) {
        return $configdata;
    }

    /**
     * Checks whether the current user can configure the filter.
     * @return boolean Whether the user can configure the filter.
     */
    public function config_allowed() {
        return has_capability($this->perm_req_for_config, context_system::instance()) ? true : false;
    }

    /**
     * Parses the label template and extracts the fields referenced.
     * @staticvar  array  $fields
     * @return     array  An array of strings corresponding to the fields referenced in the label template
     */
    public function get_label_fields() {
        static $fields = array();
        if (empty($fields)) {
            $matches = array();
            preg_match_all('#\[\[(.*)\]\]#iU',$this->_label_template,$matches);
            $fields = $matches[1];
        }
        return $fields;
    }

    /**
     * Parses the set label template and replaces placeholders with values from $results
     * @param   stdClass  $result  An SQL result row.
     * @return  string             The resulting label
     */
    public function get_results_label($result) {
        $label = $this->_label_template;
        $label_fields = $this->get_label_fields();
        foreach ($label_fields as $field) {
            if (strpos($this->_label_template,'[['.$field.']]') !== false) {
                if (isset($result->$field)) {
                    $label = str_replace('[['.$field.']]',$result->$field,$label);
                }
            }
        }
        return $label;
    }

}
