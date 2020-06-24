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

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class wemo extends eqLogic
{
	/*     * *************************Attributs****************************** */
	/* Ajouter ici toutes vos variables propre à votre classe */

	/*     * ***********************Methode static*************************** */
	public static function health()
	{
		log::add('wemo', 'debug', 'Wemo heatlh - ');
		$return = array();
		$statusDaemon = false;
		$statusDaemon = (wemo::deamon_info()['state'] == 'ok' ? true : false);
		$libVer = config::byKey('DaemonVer', 'wemo');
		if ($libVer == '') {
			$libVer = '{{inconnue}}';
		}

		$return[] = array(
			'test' => __('Daemon', __FILE__),
			'result' => ($statusDaemon) ? $libVer : __('NOK', __FILE__),
			'advice' => ($statusDaemon) ? '' : __('Indique si la Daemon est opérationel avec sa version', __FILE__),
			'state' => $statusDaemon
		);
		log::add('wemo', 'debug', 'Wemo heatlh : ' . $return['result']);
		return $return;
	}

	public static function dependancy_info()
	{
		$return = array();
		$return['log'] = 'wemo_update';
		$return['progress_file'] = '/tmp/dependancy_wemo_in_progress';
		if (exec('sudo pip list | grep pywemo') <> "") {
			$return['state'] = 'ok';
		} else {
			$return['state'] = 'nok';
		}
		return $return;
	}
	public static function dependancy_install()
	{
		log::remove('wemo_update');
		if (exec('sudo pip list |grep pywemo') <> "") {
			$cmd = 'sudo /bin/bash ' . dirname(__FILE__) . '/../../resources/upgrade.sh';
		} else {
			$cmd = 'sudo /bin/bash ' . dirname(__FILE__) . '/../../resources/install.sh';
		}
		$cmd .= ' >> ' . log::getPathToLog('wemo_update') . ' 2>&1 &';
		exec($cmd);
	}
	public static function deamon_info()
	{
		$trace = debug_backtrace();
		$trace = print_r($trace,true);
		log::add('wemo', 'debug', 'Wemo daemon info : ' . $trace);
		$return = array();
		$return['log'] = 'wemo';
		$return['state'] = 'nok';
		$result = exec("ps -eo pid,command | grep 'wemo_server.py' | grep -v grep | awk '{print $1}'");
		$opts = array(
			'http' => array(
				'method' => "GET",
				'header' => "Accept-language: en\r\n" . "Cookie: foo=bar\r\n"
			)
		);
		$context = stream_context_create($opts);
		@$file = file_get_contents('http://' . config::byKey('wemoIp', 'wemo', 'localhost') . ':' . config::byKey('wemoPort', 'wemo', '5000') . '/ping', false, $context);
		log::add('wemo', 'debug', 'Wemo daemon info ping : ' . $file);
		if ($result <> 0) {
			$return['state'] = 'ok';
		}
		$return['launchable'] = 'ok';
		log::add('wemo', 'debug', 'Wemo daemon info : ' . $return['state']);
		return $return;
	}
	public static function deamon_start($_debug = false)
	{
		self::deamon_stop();
		$shell = realpath(dirname(__FILE__)) . '/../../resources/wemo_server.py';
		
		$string = file_get_contents($shell);
		preg_match("/__version__='([0-9.]+)/mis", $string, $matches);
		config::save('DaemonVer', 'Version ' . $matches[1],  'wemo');
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}

		$logLevel = log::convertLogLevel(log::getLogLevel('wemo'));
		$url  = network::getNetworkAccess('internal').'/core/api/jeeApi.php?type=wemo&apikey=' . jeedom::getApiKey('wemo') .'&value=';
		log::add('wemo', 'info', 'Lancement démon wemo : ' . $matches[1]);
		log::add('wemo', 'debug', 'Nom complet du démon wemo : ' . $shell);
		//$result = exec($shell . ' >> ' . log::getPathToLog('wemo') . ' 2>&1 &');
		// TODO il faut lancer le serveur sur la machine Ip définie, pas uniquement en local
		$cmd = 'nice -n 19 /usr/bin/python3 ' . $shell .' '."'". $url ."'". ' ' . config::byKey('wemoPort', 'wemo', '5000') . ' ' . $logLevel;
		log::add('wemo', 'debug', 'Cmd complète  : ' . $cmd);
		// le sudo semble poser pbm
		$result = exec('nohup ' . $cmd . ' >> ' . log::getPathToLog('wemo_daemon') . ' 2>&1 &');
        
		if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
			log::add('wemo', 'error', 'échec lancement du daemon :' . $result);
			return false;
		}

		$i = 0;
		while ($i < 30) {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				break;
			}
			sleep(1);
			$i++;
		}
		if ($i >= 30) {
			log::add('wemo', 'error', 'Impossible de lancer le démon Wemo, vérifiez le log wemo', 'unableStartDaemon');
			return false;
		}
		message::removeAll('wemo', 'unableStartDaemon');
		log::add('wemo', 'info', 'Démon wemo lancé version =' . $matches[1]);
		return true;
	}

	public static function deamon_stop()
	{
		log::add('wemo', 'info', 'Arrêt demandé du service wemo');
		if (!self::deamonRunning()) {
			return true;
		}
		$opts = array(
            'http' => array(
                'method' => "GET",
                'header' => "Accept-language: en\r\n" . "Cookie: foo=bar\r\n"
            )
		);
		$context = stream_context_create($opts);
        // pour éviter des logs intempestifs quand on cherche à arrêter un serveur déjà arrêté.. @
        @$file = file_get_contents('http://' . config::byKey('wemoIp', 'wemo', 'localhost') . ':' . config::byKey('wemoPort', 'wemo', '5000') . '/stop', false, $context);
        log::add('wemo', 'info', 'Arrêt du service wemo');
		$pid = exec("ps -eo pid,command | grep 'wemo_server.py' | grep -v grep | awk '{print $1}'");
		exec('kill ' . $pid);
		$check = self::deamonRunning();
		$retry = 0;
		while ($check) {
			$check = self::deamonRunning();
			$retry++;
			if ($retry > 10) {
				$check = false;
			} else {
				sleep(1);
			}
		}
		exec('kill -9 ' . $pid);
		$check = self::deamonRunning();
		$retry = 0;
		while ($check) {
			$check = self::deamonRunning();
			$retry++;
			if ($retry > 10) {
				$check = false;
			} else {
				sleep(1);
			}
		}

		return self::deamonRunning();
	}

	public static function deamonRunning()
	{

		$result = exec("ps -eo pid,command | grep 'wemo_server.py' | grep -v grep | awk '{print $1}'");
		if ($result == 0) {
			return false;
		}
		return true;
	}


	public static function cronHourly()
	{
		self::deamon_start();
	}

	public static function searchWemoDevices()
	{

		log::add('wemo', 'info', '******** Début de la recherche d\'équipements ********');
		if (self::deamon_info()['state'] == 'nok')
			return "Il faut démarrer le serveur pour pouvoir lancer une détection.";
		$opts = array(
			'http' => array(
				'method' => "GET",
				'header' => "Accept-language: en\r\n" . "Cookie: foo=bar\r\n"
			)
		);
		$context = stream_context_create($opts);
		if (!$file = file_get_contents('http://' . config::byKey('wemoIp', 'wemo', 'localhost') . ':' . config::byKey('wemoPort', 'wemo', '5000') . '/scan', false, $context)) {
			return "Problème de détection : pas de réponse du serveur - vérifier que votre serveur est bien démarré ou regarder ses logs";
		}
		$devices = json_decode($file);
		$count = 0;

		foreach ($devices as $device) {
			log::add('wemo', 'debug', '___________________________');
			log::add('wemo', 'debug', '|Equipement trouvé : ' . $device->serialNumber);
			log::add('wemo', 'debug', '|__________________________');
			self::saveEquipment($device->name, $device->host, $device->serialNumber, $device->model, $device->modelName, $device->status, $device->standby);
			$count++;
		}
		log::add('wemo', 'info', '******** Fin du scan wemo - nombre d\'équipements trouvés = ' . $count . ' ********');
	}

	public static function saveEquipment($name, $host, $serialNumber, $model, $modelName, $status, $standby)
	{
		log::add('wemo', 'debug', '  Début saveEquipment =' . $host);
		$name = init('name', $name);
		$host = init('host', $host);
		$serialNumber = init('serialNumber', $serialNumber);
		$model = init('model', $model);
		$type = init('modelName', $modelName);
		// $id = $model.'-'.$name.'-'.$host.'-'.$serialNumber.'-'.$modelName;
		$id = $serialNumber;
		//log::add('wemo', 'debug', '  Adresse logique de l\'équipement détecté : ' . $id);
		$elogic = self::byLogicalId($id, 'wemo');
		if (is_object($elogic)) {
			log::add('wemo', 'debug', '  Equipement déjà existant - mise à jour des informations de l\'équipement détecté : ' . $id);
			$save = false;
			if ($elogic->getConfiguration('name', '') != $name) {
				$elogic->setConfiguration('name', $name);
				$save = true;
			}
			if ($elogic->getConfiguration('host', '') != $host) {
				$elogic->setConfiguration('host', $host);
				$save = true;
			}
			if ($elogic->getConfiguration('serialNumber', '') != $serialNumber) {
				$elogic->setConfiguration('serialNumber', $serialNumber);
				$save = true;
			}
			if ($elogic->getConfiguration('model', '') != $model) {
				$elogic->setConfiguration('model', $model);
				$save = true;
			}
			if ($elogic->getConfiguration('modelName', '') != $modelName) {
				$elogic->setConfiguration('modelName', $modelName);
				$save = true;
			}
			$statusCmd = $elogic->getCmd(null, 'status');
			if ($statusCmd) {
				log::add('wemo', 'debug', '  Valeur du status de l\'équipement existant :' . $statusCmd->getValue() . ' - status=' . $status);
				if ($statusCmd->getValue() != $status) {
					log::add('wemo', 'debug', '  Mise à jour du status de l\'équipement existant :' . $status . ' au lieu de ' . $statusCmd->getValue());
					$statusCmd->setValue($status);
					$statusCmd->save();
				}
			}
			$standbyCmd = $elogic->getCmd(null, 'standby');
			if ($standbyCmd) {
				log::add('wemo', 'debug', '  Valeur du standby de l\'équipement existant :' . $standbyCmd->getValue() . ' - standby =' . $standby);
				if ($standbyCmd->getValue() != $standby) {
					log::add('wemo', 'debug', '  Mise à jour du standby de l\'équipement existant :' . $standby . ' au lieu de ' . $standbyCmd->getValue());
					$standbyCmd->setValue($standby);
					$standbyCmd->save();
				}
			}

			if ($save) {
				// log::add('wemo', 'debug', 'Avant mise à jour d\'un équipement existant :' . $elogic->getName());
				$elogic->save();
				log::add('wemo', 'debug', '  Mise à jour d\'un équipement existant terminée :' . $elogic->getName());
			} else {
				log::add('wemo', 'debug', '  Aucune mise à jour à apporter à cet équipement existant :' . $elogic->getName());
			}
		} else {
			log::add('wemo', 'debug', '  Nouvel Equipement : ' . $id);
			$equipment = new wemo();
			$equipment->setEqType_name('wemo');
			$equipment->setLogicalId($id);
			$equipment->setConfiguration('name', $name);
			$equipment->setConfiguration('host', $host);
			$equipment->setConfiguration('serialNumber', $serialNumber);
			$equipment->setConfiguration('model', $model);
			$equipment->setConfiguration('modelName', $modelName);
			$name = $model . ' - ' . $name;
			$newName = $name;
			log::add('wemo', 'debug', '  Choix a priori du nom de cet équipement :' . $name);
			$i = 1;
			while (self::byObjectNameEqLogicName(__('Aucun', __FILE__), $newName)) {
				$newName = $name . ' - ' . $i++;
			}
			$equipment->setName($newName);
			log::add('wemo', 'debug', '  Choix du nom de cet équipement :' . $newName);
			$equipment->setIsEnable(1);
			$equipment->setIsVisible(1);
			$equipment->save();
			log::add('wemo', 'debug', '  Ajout terminé d\'un nouvel équipement :' . $equipment->getName() . ' - LogicalId=' . $id);
		}
	}

	
	public static function event()
    {
        $value = init('value');
        log::add('wemo', 'debug', '-> Received : ' . $value);
        
        $event = json_decode($value, true);
        // $a = print_r($event,true);
        // log::add('wemo','debug','Dump event='.$a);
        $changed = false;
        if (! isset($event["logicalAddress"]) && ! isset($event["scan"])) {
            log::add('wemo', 'warning', '  Evénement reçu sans information nommée logicalAddress ni scan. Impossible de le traiter : ' . $value);
            return;
        }

        if (isset($event["scan"])) {
            $events = $event["scan"];
        } else {
            $events = '{"1":'.$event.'}';
        }
        
        foreach ($events as $event) {

            if (! $eqLogic = eqLogic::byLogicalId($event["logicalAddress"], 'wemo')) {
                log::add('wemo', 'warning', '  Evénement reçu pour un équipement : ' . $event["logicalAddress"] . ' inexistant : abandon de l\'événement. Vérifier vos équipements Wemo');
                continue;
            }
			
			$refresh = false;
            foreach ($event as $key => $value) {
                if ($key == 'logicalAddress')
                    continue;
                log::add('wemo', 'debug', '  Decoded received frame for: ' . $eqLogic->getName() . ' logicalid: ' . $eqLogic->getLogicalId() . ' - ' . $key . '=' . $value);
                $cmd = $eqLogic->getCmd('info', $key);
                if ($cmd && $cmd->getValue() != $value) {
					$refresh = true;
					$cmd->setValue($value);
					$cmd->save();
					$cmd->setCollectDate('');
					$cmd->event($value);
				} else {
					log::add('wemo', 'warning', 'Cmd not found for the received frame for: ' . $eqLogic->getName() . ' - ' . $key . '=' . $value);
				}
			}
			if ($refresh) $eqLogic->refreshWidget();
		}
	}


	/* fonction appelée après la fin de la séquence de sauvegarde */
	public function postSave()
	{
		log::add('wemo', 'debug', '  Postsave id equipment : ' . $this->getId());
		$cmd = $this->getCmd(null, 'refresh');
		if (! is_object($cmd)) {
			$cmd = new wemoCmd();
			$cmd->setLogicalId('refresh');
			$cmd->setIsVisible(1);
		}
		$cmd->setName(__('Refresh', __FILE__));
		$cmd->setEqLogic_id($this->getId());
		$cmd->setConfiguration('request', 'refresh');
		$cmd->setType('action');
		$cmd->setSubType('other');
		$cmd->save();

		if (in_array($this->getConfiguration('modelName'), array('Switch', 'Insight', 'Lightswitch'))) {
			$cmd = $this->getCmd(null, 'status');
        	if (! is_object($cmd)) {
				$cmd = new wemoCmd();
				$cmd->setLogicalId('status');
				$cmd->setIsVisible(1);
			}
			$cmd->setName(__('Etat', __FILE__));
			$cmd->setEqLogic_id($this->getId());
			$cmd->setConfiguration('request', 'status');
			$cmd->setType('info');
			$cmd->setSubType('binary');
			$cmd->setDisplay('generic_type', 'ENERGY_STATE');
			$cmd->save();

			$cmd = $this->getCmd(null, 'standby');
        	if (! is_object($cmd)) {
				$cmd = new wemoCmd();
				$cmd->setLogicalId('standby');
				$cmd->setIsVisible(1);
			}
			$cmd->setName(__('Standby', __FILE__));
			$cmd->setEqLogic_id($this->getId());
			$cmd->setConfiguration('request', 'standby');
			$cmd->setType('info');
			$cmd->setSubType('binary');
			$cmd->setDisplay('generic_type', 'ENERGY_STATE');
			$cmd->save();

			$cmd = $this->getCmd(null, 'on');
        	if (! is_object($cmd)) {
				$cmd = new wemoCmd();
				$cmd->setLogicalId('on');
				$cmd->setIsVisible(1);
			}
			$cmd->setName(__('On', __FILE__));
			$cmd->setEqLogic_id($this->getId());
			$cmd->setConfiguration('request', 'on');
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'ENERGY_ON');
			$cmd->save();

			$cmd = $this->getCmd(null, 'off');
        	if (! is_object($cmd)) {
				$cmd = new wemoCmd();
				$cmd->setLogicalId('off');
				$cmd->setIsVisible(1);
			}
			$cmd->setName(__('Off', __FILE__));
			$cmd->setEqLogic_id($this->getId());
			$cmd->setConfiguration('request', 'off');
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setDisplay('generic_type', 'OFF');
			$cmd->save();

			$cmd = $this->getCmd(null, 'toggle');
        	if (! is_object($cmd)) {
				$cmd = new wemoCmd();
				$cmd->setLogicalId('toggle');
				$cmd->setIsVisible(1);
			}
			$cmd->setName(__('Inverser', __FILE__));
			$cmd->setEqLogic_id($this->getId());
			$cmd->setConfiguration('request', 'toggle');
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->save();

			if ($this->getConfiguration('modelName') == "Insight") {
				$cmd = $this->getCmd(null, 'currentpower');
				if (! is_object($cmd)) {
					$cmd = new wemoCmd();
					$cmd->setLogicalId('currentpower');
					$cmd->setIsVisible(1);
				}
				$cmd->setName(__('Currentpower', __FILE__));
				$cmd->setEqLogic_id($this->getId());
				$cmd->setConfiguration('request', 'currentpower');
				$cmd->setType('info');
				$cmd->setSubType('numeric');
				$cmd->save();
			}
		} elseif (in_array($this->getConfiguration('modelName'), array('Motion'))) {
			$cmd = $this->getCmd(null, 'status');
        	if (! is_object($cmd)) {
				$cmd = new wemoCmd();
				$cmd->setLogicalId('status');
				$cmd->setIsVisible(1);
			}
			$cmd->setName(__('Etat', __FILE__));
			$cmd->setEqLogic_id($this->getId());
			$cmd->setConfiguration('request', 'status');
			$cmd->setType('info');
			$cmd->setSubType('binary');
			$cmd->save();
		}
	}


	/*     * *********************Methode d'instance************************* */
}

class wemoCmd extends cmd
{
	/*     * *************************Attributs****************************** */


	/*     * ***********************Methode static*************************** */


	/*     * *********************Methode d'instance************************* */


	function _isJson($string) {
		json_decode($string);
		return (json_last_error() == JSON_ERROR_NONE);
	}

	public function execute($_options = array())
	{

		if (empty($_options)) {
			$param = "none";
		} else {
			$param = print_r($_options, true);
		}

		log::add('wemo', 'debug', 'Commande reçue à exécuter : ' . $this->getConfiguration('request') . ' de type ' . $this->type . ' paramètres =' . $param);
		if (empty($this->getConfiguration('request'))) {
			log::add('wemo', 'warning', 'Echec exécution d\'une commande vide');
			return;
		}
		$eqLogic = $this->getEqLogic();
		$action = $this->getConfiguration('request');
		$logicalAddress = $eqLogic->getConfiguration('logicalAddress');
		switch ($action) {
			case "channel1":
				$action = 'transmit';
				$_options['title'] = $logicalAddress;
				$_options['message'] = '44:' . Channel1;
				break;
		}
		if (!empty($_options)) {
			$param = print_r($_options, true);
			log::add('wemo', 'debug', '-> Enrichissement dynamique des paramètres : ' . $this->getConfiguration('request') . ' de type ' . $this->type . ' paramètres =' . $param);
		}
		// log::add('wemo','debug','Commande reçue : ' . $action);
		$opts = array(
			'http' => array(
				'method' => "GET",
				'header' => "Accept-language: en\r\n" . "Cookie: foo=bar\r\n"
			)
		);
		$context = stream_context_create($opts);
		// pour éviter des logs intempestifs quand on cherche à arrêter un serveur déjà arrêté.. @
		if (isset($_options['title'])) {
			@$file = file_get_contents('http://' . config::byKey('wemoIp', 'wemo', 'localhost') . ':' . config::byKey('wemoPort', 'wemo', '5000') . '/' . rawurlencode($action) . '?address=' . rawurlencode($_options['title'] . '&parameter=' . rawurlencode($_options['message'])), false, $context);
		} else {
			@$file = file_get_contents('http://' . config::byKey('wemoIp', 'wemo', 'localhost') . ':' . config::byKey('wemoPort', 'wemo', '5000') . '/' . rawurlencode($action) . '?address=' . rawurlencode($eqLogic->getLogicalId()), false, $context);
		}
		
		if ($file === false) {
			$error = error_get_last();
			log::add('wemo', 'warning', 'Echec exécution de la commande  ' . $this->getConfiguration('request') . ' error=' . $error);
		} else {
			log::add('wemo', 'debug', 'Exécution de la commande  ' . $this->getConfiguration('request') . ' terminée ' . $file);
			//todo analyse result
			$refresh = false;
			if ( $this->_isJson($file)) {
			//if ( in_array($this->getConfiguration('request'),array('refresh','toggle'))) {
				$result = json_decode($file);
				foreach ($result as $key => $value) {
					$cmd = $eqLogic->getCmd('info', $key);
					if ($cmd && $cmd->getValue() != $value) {
						$refresh = true;
						$cmd->setValue($value);
						$cmd->save();
						$cmd->setCollectDate('');
						$cmd->event($value);
					} else {
						log::add('wemo','debug',"key = $key no info command found, value = $value, skip key");
					}
				}
			}
			if ($refresh) $eqLogic->refreshWidget();
		}
		return true;
	}

	/*     * **********************Getteur Setteur*************************** */
}