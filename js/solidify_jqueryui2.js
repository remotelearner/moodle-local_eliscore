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

var lecjqueryui;
var lecscripts = document.getElementsByTagName('script');
for (var i = 0; i < lecscripts.length; i++) {
    if (lecscripts[i].src && lecscripts[i].src.search('jquery-ui.js') != -1) {
        // console.log('local_eliscore::solidify_jqueryui2.js: found jquery-ui in head');
        window.lecjqueryui = lecscripts[i];
        break;
    }
}

var reinitjqueryui = function(e) {
    if (!jQuery.ui && window.lecjqueryui.src) {
        // console.log('local_eliscore::solidify_jqueryui2.js::reinitjqueryui: reloading jqueryui & script.');
        YUI().use("io-base", function(Y) {
            // Download the jqueryui
            var cfg = {
                method: 'GET',
                sync: true,
                timeout: 30000, // TBD: 30secs ?
            };
            var resobj = Y.io(window.lecjqueryui.src, cfg);
            // console.debug(resobj);
            if (resobj && resobj.status == 200 && resobj.responseText) {
                // console.log('local_eliscore::solidify_jqueryui2.js::reinitjqueryui: re-evaluating jqueryui ...');
                eval(resobj.responseText);
                return true;
            }
        });
    }
};

if (window.lecjqueryui) {
    if (window.addEventListener) {
        window.addEventListener('error', window.reinitjqueryui, true);
    } else if (window.attachEvent) { // Support IE > 9.
        window.attachEvent('onerror', window.reinitjqueryui);
    }
}
