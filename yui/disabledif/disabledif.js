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

YUI.add('moodle-local_eliscore-disabledif', function(Y) {

    /**
     * The module name
     * @property MODULEBASENAME
     * @type {String}
     * @default "eliscore-disabledif"
     */
    var MODULEBASENAME = 'core-disabled-if';

    /**
     * This method calls the base class constructor
     * @method MODULEBASE
     */
    var MODULEBASE = function() {
        MODULEBASE.superclass.constructor.apply(this, arguments);
    }

    /**
     * @class M.local_eliscore.disabledif
     */
    Y.extend(MODULEBASE, Y.Base, {
        /**
         * A dom element id whose state enables or disables list of dom elements.
         * @property controlelemid
         * @type {string}
         * @default ''
         */
        controlelemid: '',

        /**
         * Operator (currently 'eq' or 'neq' supported).
         * @property testop
         * @type {string}
         * @default ''
         */
        testop: '',

        /**
         * Test controlelemid property, i.e. 'checked', 'value', ....
         * @property testprop
         * @type {string}
         * @default ''
         */
        testprop: '',

        /**
         * Test value.
         * @property testvalue
         * @type {string}
         * @default ''
         */
        testvalue: '',

        /**
         * Array of dom element ids controlled/disabled-if.
         * @property controlled
         * @type {array}
         * @default empty
         */
        controlled: [],

        /**
         * Initialize the disabled-if module
         * @param array args function arguments
         */
        initializer : function(args) {
            this.controlelemid = args[0];
            this.testop = args[1];
            this.testprop = args[2];
            this.testvalue = args[3];
            this.controlled = Y.JSON.parse(args[4]);
            Y.on('click', this.controlelem_changed, '#'+this.controlelemid, this);
            this.controlelem_changed(); // Initialize!
        },

        /**
         * Handle controlelem changes.
         * @return bool true on success, false on error.
         */
        controlelem_changed : function() {
            controlelem = Y.one('#'+this.controlelemid);
            if (!controlelem) {
                return false;
            }
            var controlval = controlelem.get(this.testprop) || controlelem.getAttribute(this.testprop);
            // console.debug(controlval);
            var disabled = (this.testop == 'neq') ? (controlval != this.testvalue) : (controlval == this.testvalue);
            // console.debug(disabled);
            var controlled;
            for (var i in this.controlled) {
                controlled = Y.one('#'+this.controlled[i]);
                if (!controlled) {
                    continue;
                }
                if (disabled) {
                    controlled.set('disabled', 'disabled');
                    // controlled.set('value', '');
                } else {
                    controlled.set('disabled', '');
                }
            }
            return true;
        }
    },
    {
        NAME : MODULEBASENAME,
        ATTRS : {
            controlelemid: '',
            testop: '',
            testprop: '',
            testvalue: '',
            controlled: []
        }
    });

    // Ensure that M.local_eliscore exists and is initialized correctly
    M.local_eliscore = M.local_eliscore || {};

    /**
     * Entry point for disabled-if YUI module
     * @param string control element id.
     * @param string test op: 'eq' (or 'neq')
     * @param string test property, i.e. 'value', 'checked', ...
     * @param string test value (to match 'eq' or 'neq' ...)
     * @param string json encoded array of (strings) controlled elem ids.
     * @return object the disabledif object
     */
    M.local_eliscore.init_disabledif = function(controlelemid, testop, testprop, testvalue, controlled) {
        args = [controlelemid, testop, testprop, testvalue, controlled];
        return new MODULEBASE(args);
    }

}, '@VERSION@', { requires : ['base', 'event', 'json', 'node'] });
