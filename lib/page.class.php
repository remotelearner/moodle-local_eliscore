<?php
/**
 * Base ELIS page class
 *
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/local/eliscore/lib/setup.php');

/**
 * Class casting function.
 *
 * @param string|object $destination
 * @param object $sourceobject
 * @return object destination object with values of sourceobject
 */
function cast_obj($destination, $sourceobject) {
    if (is_string($destination)) {
        $destination = new $destination();
    }
    $sourcereflection = new ReflectionObject($sourceobject);
    $destinationreflection = new ReflectionObject($destination);
    $sourceproperties = $sourcereflection->getProperties();
    foreach ($sourceproperties as $sourceproperty) {
        $sourceproperty->setAccessible(true);
        $name = $sourceproperty->getName();
        $value = $sourceproperty->getValue($sourceobject);
        if ($destinationreflection->hasProperty($name)) {
            $propdest = $destinationreflection->getProperty($name);
            $propdest->setAccessible(true);
            $propdest->setValue($destination, $value);
        } // TBD: don't set any additional properties - else { $destination->$name = $value; }
    }
    return $destination;
}

/**
 * Base ELIS page class.  Provides a framework for displaying a standard page
 * and performing actions.
 *
 * Subclasses must have a do_<foo>() or display_<foo>() method for each action
 * <foo> that it supports.  The default action (if none is specified) is called
 * "default", and so is handled by display_default() (or do_default(), though
 * you really shouldn't do that).
 */
abstract class elis_page extends moodle_page {
    /**
     * Page parameters (if null, use the HTTP parameters)
     */
    protected $params = null;

    /**
     * Constructor.
     *
     * Subclasses must override this and set the Moodle page parameters
     * (e.g. context, url, pagetype, title, etc.).
     *
     * @param array $params array of URL parameters.  If  $params is not
     * specified, the constructor for each subclass should load the parameters
     * from the current HTTP request.
     */
    public function __construct(array $params=null) {
        $this->params = $params;
        $this->set_context($this->_get_page_context());
        $this->set_url($this->_get_page_url(), $this->_get_page_params());
        $this->get_header_requirements();
        // ELIS-9092: If including jquery-ui must make sure not broken by re-loads of jquery(base).
        if (!empty($this->_requires)) {
            $elispage = cast_obj('eliscorepage', $this);
            $pgreqmanager = $elispage->get_pg_req_manager();
            if ($pgreqmanager->jquery_included('ui', $elispage)) {
                // $this->requires->js('/local/eliscore/js/solidify_jqueryui.js', true);
                $this->requires->js('/local/eliscore/js/solidify_jqueryui2.js', true);
            }
        }
        //set up a CSS hook for styling all ELIS pages
        $this->add_body_class('elis_page');
    }

    /**
     * Return the context that the page is related to.  Used by the constructor
     * for calling $this->set_context().
     */
    protected function _get_page_context() {
        return context_system::instance();
    }

    /**
     * Return the base URL for the page.  Used by the constructor for calling
     * $this->set_url().  Although the default behaviour is somewhat sane, this
     * method should be overridden by subclasses if the page may be created to
     * represent a page that is not the current page.
     */
    protected function _get_page_url() {
        global $ME;
        return $ME;
    }

    /**
     * Return the page parameters for the page.  Used by the constructor for
     * calling $this->set_url().
     *
     * @return array
     */
    protected function _get_page_params() {
        $params = isset($this->params) ? $this->params : $_GET;
        return $params;
    }

    /**
     * Return the page type.  Used by the constructor for calling
     * $this->set_pagetype().
     */
    protected function _get_page_type() {
        return 'elis';
    }

    /**
     * Return the page layout.  Used by the constructor for calling
     * $this->set_pagelayout().
     */
    protected function _get_page_layout() {
        return 'standard';
    }

    /**
     * Set page header requirements
     * Overload to set any page header requirements, like jquery, etc...
     */
    protected function get_header_requirements() {
    }

    /**
     * Create a new page object of the same class with the given parameters.
     *
     * @param array $params array of URL parameters.
     * @param boolean $replace_params whether the page URL parameters should be
     * replaced by $params (true) or whether the page URL parameters should be
     * $params appended to the original page parameters (false).
     */
    public function get_new_page(array $params=null, $replace_params=false) {
        $pageclass = get_class($this);
        if (!$replace_params) {
            if ($params === null) {
                $params = $this->params;
            } else if ($this->url->params() !== null) {
                $params += $this->url->params();
            }
        }
        return new $pageclass($params);
    }

    /**
     * Get required page parameters.
     *
     * Please note the $type parameter is now required and the value can not be array.
     *
     * @param string $parname the name of the page parameter we want
     * @param string $type expected type of parameter
     * @return mixed
     */
    public function required_param($name, $type=PARAM_CLEAN) {
        if ($this->params !== null) {
            if (isset($this->params[$name])) {
                return clean_param($this->params[$name], $type);
            } else {
                print_error('missingparam', '', '', $name);
            }
        } else {
            return required_param($name, $type);
        }
    }

    /**
     * Get required page parameters as an array
     *
     *  Note: arrays of arrays are not supported, only alphanumeric keys with _ and - are supported
     *
     * @param string $parname the name of the page parameter we want
     * @param string $type expected type of parameter
     * @return array
     */
    public function required_param_array($name, $type=PARAM_CLEAN) {
        if ($this->params !== null) {
            if (isset($this->params[$name])) {
                $result = array();

                foreach ($this->params[$name] as $key => $value) {
                    if (!preg_match('/^[a-z0-9_-]+$/i', $key)) {
                        debugging('Invalid key name in required_param_array() detected: '.$key.', parameter: '.$parname);
                        continue;
                    }
                    $result[$key] = clean_param($value, $type);
                }

                return $result;
            } else {
                print_error('missingparam', '', '', $name);
            }
        } else {
            return required_param_array($name, $type);
        }
    }

    /**
     * Get optional page parameters.
     */
    public function optional_param($name, $default, $type) {
        if ($this->params !== null) {
            if (isset($this->params[$name])) {
                return clean_param($this->params[$name], $type);
            } else {
                return $default;
            }
        } else {
            return optional_param($name, $default, $type);
        }
    }

    /**
     * Get optional array page parameters.
     */
    public function optional_param_array($name, $default, $type) {
        if ($this->params !== null) {
            if (isset($this->params[$name])) {
                $result = array();

                foreach ($this->params[$name] as $key => $value) {
                    if (!preg_match('/^[a-z0-9_-]+$/i', $key)) {
                        debugging('Invalid key name in page::optional_param_array() detected: '. $key .', parameter: '. $name);
                        continue;
                    }
                    // Support nested array params!
                    $result[$key] = is_array($value)
                                    ? clean_param_array($value, $type)
                                    : clean_param($value, $type);
                }
                return $result;
            } else {
                return $default;
            }
        } else {
            // NOTE: cannot just call optional_param_array()
            // because it doesn't support nested array params!
            if (func_num_args() != 3 or empty($name) or empty($type)) {
                throw new coding_exception('page::optional_param_array() requires $name, $default and $type to be specified (parameter: '. $name .')');
            }

            if (isset($_POST[$name])) {       // POST has precedence
                $param = $_POST[$name];
            } else if (isset($_GET[$name])) {
                $param = $_GET[$name];
            } else {
                return $default;
            }
            if (!is_array($param)) {
                debugging('page::optional_param_array() expects array parameters only: '.$parname);
                return $default;
            }

            $result = array();
            foreach ($param as $key => $value) {
                if (!preg_match('/^[a-z0-9_-]+$/i', $key)) {
                    debugging('Invalid key name in page::optional_param_array() detected: '. $key .', parameter: '. $name);
                    continue;
                }
                // Support nested array params!
                $result[$key] = is_array($value)
                                ? clean_param_array($value, $type)
                                : clean_param($value, $type);
            }
            return $result;
        }
    }

    /**
     * Return the page title.  Used by the constructor for calling
     * $this->set_title().
     */
    public function get_page_title($action=null) {
        if ($action === null) {
            $action = $this->optional_param('action', '', PARAM_ACTION);
        }
        if (method_exists($this, "get_page_title_{$action}")) {
            return call_user_func(array($this, "get_page_title_{$action}"));
        } else {
            return $this->get_page_title_default();
        }
    }

    public function get_page_title_default() {
        return get_string('elis', 'local_eliscore');
    }

    /**
     * Build the navigation bar object
     */
    public function build_navbar($action=null) {
        if ($action === null) {
            $action = $this->optional_param('action', '', PARAM_ACTION);
        }
        if (method_exists($this, "build_navbar_{$action}")) {
            return call_user_func(array($this, "build_navbar_{$action}"));
        } else {
            return $this->build_navbar_default();
        }
    }

    public function build_navbar_default() {
        // Do nothing (default to empty navbar)
    }

    /**
     * Return the page heading.  Used by the constructor for calling
     * $this->set_heading().
     */
    protected function get_page_heading($action=null) {
        return $this->get_page_title();
    }


    /**
     * Main page entry point.  Dispatches based on the action parameter.
     */
    public function run() {
        global $OUTPUT;
        $action = $this->optional_param('action', 'default', PARAM_ACTION);
        if ($this->can_do($action)) {
            $this->_init_display();
            if (method_exists($this, "do_{$action}")) {
                return call_user_func(array($this, "do_{$action}"));
            } else if (method_exists($this, 'display_' . $action)) {
                $this->display($action);
            } else {
                print_error('unknown_action', 'local_eliscore', '', $action);
            }
        } else {
            print_error('nopermissions', '', '', $action);
        }
    }

    /**
     * Initialize the page variables needed for display.
     */
    protected function _init_display() {
        $this->set_pagelayout($this->_get_page_layout());
        $this->set_pagetype($this->_get_page_type());
        $this->set_title($this->get_page_title());
        $this->set_heading($this->get_page_heading());
        $this->build_navbar();
    }

    /**
     * Print the page header.
     */
    public function print_header($_) {
        global $OUTPUT;
        echo $OUTPUT->header();
    }

    /**
     * Print the page footer.
     */
    public function print_footer() {
        global $OUTPUT;
        echo $OUTPUT->footer();
    }

    /**
     * Display the page.
     */
    public function display($action=null) {
        if ($action === null) {
            $action = $this->optional_param('action', 'default', PARAM_ACTION);
        }
        $this->print_header(null);
        call_user_func(array($this, 'display_' . $action));
        $this->print_footer();
    }

    /**
     * Determines whether or not the user can perform the specified action.  By
     * default, it calls the can_do_<action> functions.
     */
    public function can_do($action=null) {
        if ($action === null) {
            $action = $this->optional_param('action', '', PARAM_ACTION);
        }
        if (method_exists($this, 'can_do_' . $action)) {
            return call_user_func(array($this, 'can_do_' . $action));
        } else if (method_exists($this, 'can_do_default')) {
            return $this->can_do_default();
        } else {
            return false;
        }
    }

    /**
     * Specifies a unique shortname for the entity represented by
     * a page of this type, transforming the supplied value if necessary
     *
     * @param   string       $parent_path  Path of all parent elements, or the empty string
     *                                     if none
     * @param   string       $name         Initial name provided for the element
     *
     * @return  string|NULL                A valid name to identify the item with, or NULL if
     *                                     not applicable
     */
    static function get_entity_name($parent_path, $name) {
        //implement in child class if necessary
        return NULL;
    }

    /**
     * Get page requirements manager of page.
     * @return object elis_pg_reqs_manager for page.
     */
    public function get_pg_req_manager() {
        return cast_obj('elis_pg_reqs_manager', $this->_requires);
    }
}

/**
 * ELIS core page. Dummy class to provide a class to create instance of
 * see elis_pg_reqs_manager class, below.
 */
class eliscorepage extends elis_page {
}

/**
 * Subclass of Moodle page_manager_requirements class.
 */
class elis_pg_reqs_manager extends page_requirements_manager {
    /** @const jquery base library included bit-mapped */
    const JQUERY_BASE_INCLUDED = 1;
    /** @const jquery ui library included */
    const JQUERY_UI_INCLUDED = 2;
    /** @const jquery ui-css included */
    const JQUERY_UICSS_INCLUDED = 4;

    /** @var int included jquery in page */
    protected $included_jquery = -1;

    /**
     * Return jQuery related markup for page start.
     * @return string
     */
    public function get_jquery_headcode() {
        return parent::get_jquery_headcode();
    }

    /**
     * Method to determine jquery libraries already included in page header.
     * @param string $jqueryplugin the jquery component to test.
     * @param object $page the moodle_page object, defaults to $PAGE.
     * @return bool true if jquery component already installed, false otherwise.
     */
    public function jquery_included($jqueryplugin, $page = null) {
        $pluginmap = array('jquery' => self::JQUERY_BASE_INCLUDED, 'ui' => self::JQUERY_UI_INCLUDED, 'ui-css' => self::JQUERY_UICSS_INCLUDED);
        if ($this->included_jquery < 0) {
            global $PAGE;
            if (is_null($page)) {
                $page = $PAGE;
            }
            $jquery = 0;
            $elispage = cast_obj('eliscorepage', $page);
            $pgreqmanager = $elispage->get_pg_req_manager();
            $pageheadcode = $pgreqmanager->get_jquery_headcode();
            if (preg_match("/script.*jquery-[1-9].*[.]js/i", $pageheadcode) !== false) {
                $jquery = $jquery | self::JQUERY_BASE_INCLUDED;
            }
            if (preg_match("/script.*jquery-ui[.]js/i", $pageheadcode) !== false) {
                $jquery = $jquery | self::JQUERY_UI_INCLUDED;
            }
            if (preg_match("/jquery-ui[.]css/", $pageheadcode) !== false) {
                $jquery = $jquery | self::JQUERY_UICSS_INCLUDED;
            }
            $this->included_jquery = $jquery;
        }
        if (empty($jqueryplugin)) {
            $jqueryplugin = 'jquery';
        }
        return isset($pluginmap[$jqueryplugin]) && ($this->included_jquery & $pluginmap[$jqueryplugin]) != 0;
    }

    /**
     * Method to require jquery plugins.
     * @param array $jqueryplugins jquery plugins string array required for page, 'jquery', 'ui', 'ui-css'.
     * @param object $page the moodle_page object, defaults to $PAGE.
     * @param bool $output true to directly output jquery html, false (default) returns html only.
     * @return string required jquery html or empty string for none.
     */
    public function jquery_plugins($jqueryplugins, $page = null, $output = false) {
        global $PAGE;
        if (is_null($page)) {
            $page = $PAGE;
        }
        $jqueryhtml = '';
        $failedplugins = false;
        foreach ($jqueryplugins as $jqueryplugin) {
            ob_start(); // TBD - if header already done, method jquery_plugin() currently outputs debugging message and returns false - no exception!
            try {
                $success = $page->requires->jquery_plugin($jqueryplugin);
            } catch (Exception $e) {
                $success = false;
            }
            ob_end_clean();
            if (!$success && !$this->jquery_included($jqueryplugin, $page)) {
                $failedplugins = true;
                $this->jquery_plugin($jqueryplugin);
            }
        }
        if ($failedplugins) {
            $jqueryhtml = $this->get_jquery_headcode();
            if ($output) {
                echo $jqueryhtml;
            }
        }
        return $jqueryhtml;
    }

    /**
     * Method to add YUI lib css to page or return required style '@import' directive.
     * @param object $page the page object to require css file.
     * @param string $cssfile the required css file with path relative to lib/yuilib/X.Y.Z/
     * @return string the style directive for the specified css file, or empty if added to page requirements or not found.
     */
    public function yui_css_style($page, $cssfile) {
        global $CFG;
        $csspath = "/lib/yuilib/{$CFG->yui3version}/{$cssfile}";
        if (file_exists($CFG->dirroot.$csspath)) {
            try {
                $page->requires->css($csspath);
            } catch (Exception $e) {
                return "@import url(\"{$this->yui3loader->base}{$cssfile}\");\n";
            }
        }
        return '';
    }
}
