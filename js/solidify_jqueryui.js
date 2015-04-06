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

var blockpostuijquery = function(e) {
    var el = e.target;
    if (typeof(el.src) != 'undefined' && el.src.search(/script.*jquery-[1-9].*[.]js/i) != -1) {
        console.log('local_eliscore::solidify_jqueryui.js: Aborting script: '+el.src);
        return e.preventDefault(); // Block script.
    }
};

// Following will only work in >= FF5.x (HTML5).
if (window.addEventListener) {
    window.addEventListener('beforescriptexecute', window.blockpostuijquery, true);
} else if (window.attachEvent) { // Support IE > 9.
    window.attachEvent('onbeforescriptexecute', window.blockpostuijquery);
}
