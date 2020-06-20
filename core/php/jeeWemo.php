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
if (php_sapi_name() != 'cli' || isset($_SERVER['REQUEST_METHOD']) || !isset($_SERVER['argc'])) {
    header("Status: 404 Not Found");
    header('HTTP/1.0 404 Not Found');
    $_SERVER['REDIRECT_STATUS'] = 404;
    echo "<h1>404 Not Found</h1>";
    echo "The page that you have requested could not be found.";
    exit();
}
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (isset($argv)) {
    foreach ($argv as $arg) {
        $argList = explode('=', $arg);
        if (isset($argList[0]) && isset($argList[1])) {
            $_GET[$argList[0]] = $argList[1];
        }
    }
}
$message = '';
foreach ($_GET as $key => $value) {
    $message .= $key . '=>' . $value . ' ';
}
log::add('wemo', 'event', 'Evenement  à supprimer : ' . $message);
//log::add('wemo', 'event', 'tableau 2: ' . json_encode($values_arr));
$wemo_all = eqLogic::byTypeAndSearhConfiguration('wemo', $_GET['serialNumber']);
if (count($wemo_all) == 0) {
    log::add('wemo', 'warning', 'Event reçu mais impossible de trouver le device ' . $message, 'config');
    return;
}
foreach ($wemo_all as $wemo) {
    foreach ($wemo->getCmd('info') as $cmd) {
        $cmd->event($_GET['status']);
        $cmd->setValue($_GET['status']);
        $cmd->save();
    }
}