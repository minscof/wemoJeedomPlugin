<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

function wemo_install() {
    exec('sudo apt-get install python-pip libevent-dev python-all-dev ');
    exec('sudo apt-get install pywemo';
    exec('sudo apt-get remove python-pip libevent-dev python-all-dev ');
}

function wemo_update() {
    foreach (eqLogic::byType('wemo') as $wemo) {
        $wemo->save();
    }
}

function wemo_install() {
    exec('sudo apt-get install python-pip libevent-dev python-all-dev ');
    exec('sudo apt-get uninstall pywemo';
    exec('sudo apt-get remove python-pip libevent-dev python-all-dev ');
}


?>