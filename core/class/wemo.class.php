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
if(!class_exists('Pest')){ include dirname(__FILE__) . '/../../3rdparty/pest/Pest.php'; }

class wemo extends eqLogic {
    /*     * *************************Attributs****************************** */
	private static $_wemoUpdatetime = array();

    /*     * ***********************Methode static*************************** */
	public static function health()	{
	    $return = array();
	    $statusDeamon = false;
	    $statusDeamon = (wemo::deamon_info()['state']=='ok'?true:false);
	    $libVer = config::byKey('DeamonVer', 'wemo');
	    if ($libVer == '') {
	        $libVer = '{{inconnue}}';
	    }
	    
	    $return[] = array(
	        'test' => __('Deamon', __FILE__),
	        'result' => ($statusDeamon) ? $libVer : __('NOK', __FILE__),
	        'advice' => ($statusDeamon) ? '' : __('Indique si la Deamon est opérationel avec sa version', __FILE__),
	        'state' => $statusDeamon
	    );
	    return $return;
	}
	
	public static function dependancy_info() {
		$return = array();
		$return['log'] = 'wemo_update';
		$return['progress_file'] = '/tmp/dependancy_wemo_in_progress';
		if (exec('sudo pip list |grep ouimeaux')<>""){
			$return['state'] = 'ok';
		} else {
			$return['state'] = 'nok';
		}
		return $return;
	}
	public static function dependancy_install() {
		log::remove('wemo_update');
		if (exec('sudo pip list |grep ouimeaux')<>""){
			$cmd = 'sudo /bin/bash ' . dirname(__FILE__) . '/../../resources/upgrade.sh';
		}else{
			$cmd = 'sudo /bin/bash ' . dirname(__FILE__) . '/../../resources/install.sh';	
		}
		$cmd .= ' >> ' . log::getPathToLog('wemo_update') . ' 2>&1 &';
		exec($cmd);
	}
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'wemo';
		$return['state'] = 'nok';
		$result = exec("ps -eo pid,command | grep 'wemo_server.py' | grep -v grep | awk '{print $1}'");
		if ($result <> 0) {
            $return['state'] = 'ok';
        }
		$return['launchable'] = 'ok';
		return $return;
	}
	public static function deamon_start($_debug = false) {
		self::deamon_stop();
		$shell = realpath(dirname(__FILE__)).'/../../resources/wemo_server.py';
		$string = file_get_contents($shell);
		preg_match("/__version__='([0-9.]+)/mis", $string, $matches);
		config::save('DeamonVer', 'Version '.$matches[1],  'wemo');
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		log::remove('wemo');
		
        log::add('wemo', 'info', 'Lancement démon wemo : ' . $shell);
        $result = exec($shell . ' >> ' . log::getPathToLog('wemo') . ' 2>&1 &');
        if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
            log::add('wemo', 'error', $result);
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
			log::add('wemo', 'error', 'Impossible de lancer le démon Wemo, vérifiez le log wemo', 'unableStartDeamon');
			return false;
		}
        message::removeAll('wemo', 'unableStartDeamon');
        log::add('wemo', 'info', 'Démon wemo lancé '.matches[1]);
		return true;
	}

	public static function deamon_stop() {
		if (!self::deamonRunning()) {
            return true;
        }
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
    
    public static function cronHourly() {
    	self::deamon_start();
    }
    
    public static function searchWemoDevices() {

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
        if (! $file = file_get_contents('http://' . config::byKey('wemoIp', 'wemo', 'localhost') . ':' . config::byKey('wemoPort', 'wemo', '5000') . '/scan', false, $context)) {
            return "Problème de détection : pas de réponse du serveur - vérifier que votre serveur est bien démarré ou regarder ses logs";
        }
        $devices = json_decode($file);
        $count = 0;
		
		foreach($devices as $device) {
			log::add('wemo', 'debug', '___________________________');
            log::add('wemo', 'debug', '|Equipement trouvé : ' . $device->serialnumber);
            log::add('wemo', 'debug', '|__________________________');
            self::saveEquipment($device->name, $device->host, $device->serialnumber, $device->model, $device->model_name, $device->state);
        }
	}
	
	public static function saveEquipment($name, $host, $serialNumber, $model, $model_name, $status)
    {
        log::add('wemo', 'debug', '  Début saveEquipment =' . $host);
        $name = init('name', $name);
        $host = init('host', $host);
        $serialNumber = init('serialNumber', $serialNumber);
        $model = init('model', $model);
        $type = init('model_name', $model_name);
        // $id = $model.'-'.$name.'-'.$host.'-'.$serialNumber.'-'.$model_name;
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
            if ($elogic->getConfiguration('model_name', '') != $model_name) {
                $elogic->setConfiguration('model_name', $model_name);
                $save = true;
            }
            $statusCmd = $elogic->getCmd(null, 'status');
            log::add('wemo', 'debug', '  Valeur du status de l\'équipement existant :' . $statusCmd->getValue() . ' - status=' . $status);
            if ($statusCmd->getValue() != $value = self::convertStatus($status)) {
                log::add('wemo', 'debug', '  Mise à jour du status de l\'équipement existant :' . self::convertStatus($status) . ' au lieu de ' . $statusCmd->getValue());
                $statusCmd->setValue(self::convertStatus($status));
                $statusCmd->save();
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
            $equipment->setConfiguration('model_name', $model_name);
            $name = $model . ' - ' . $name;
            $newName = $name;
            log::add('wemo', 'debug', '  Choix a priori du nom de cet équipement :' . $name);
            $i = 1;
            while (self::byObjectNameEqLogicName(__('Aucun', __FILE__), $newName)) {
                $newName = $name . ' - ' . $i ++;
            }
            $equipment->setName($newName);
            log::add('wemo', 'debug', '  Choix du nom de cet équipement :' . $newName);
            $equipment->setIsEnable(true);
            $equipment->setIsVisible(true);
            $equipment->save();
            log::add('wemo', 'debug', '  Ajout terminé d\'un nouvel équipement :' . $equipment->getName() . ' - LogicalId=' . $id);
        }
    }

    public static function deamonRunning() {
        
        $result = exec("ps -eo pid,command | grep 'wemo_server.py' | grep -v grep | awk '{print $1}'");
        if ($result == 0) {
            return false;
        }
        return true;
    }

    

    /*     * *********************Methode d'instance************************* */
    
}

class wemoCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    public function preSave() {
        if ($this->getConfiguration('request') == '') {
            //throw new Exception(__('La requete ne peut etre vide',__FILE__));
        }
	}
	


	/* fonction appelée après la fin de la séquence de sauvegarde */
	public function postSave()
	{
		if (in_array($this->getConfiguration('model_name'), array('Switch','Insight','Lightswitch'))) {
			$wemoCmd = new wemoCmd();
			$wemoCmd->setName(__('Etat', __FILE__));
			$wemoCmd->setEqLogic_id($this->getId());
			$wemoCmd->setConfiguration('request', 'state');
			$wemoCmd->setType('info');
			$wemoCmd->setSubType('binary');
			$wemoCmd->setDisplay('generic_type','ENERGY_STATE');
			$wemoCmd->save();
			
			$wemoCmd = new wemoCmd();
			$wemoCmd->setName(__('On', __FILE__));
			$wemoCmd->setEqLogic_id($this->getId());
			$wemoCmd->setConfiguration('request', 'on');
			$wemoCmd->setType('action');
			$wemoCmd->setSubType('other');
			$wemoCmd->setDisplay('generic_type','ENERGY_ON');
			$wemoCmd->save();
			
			$wemoCmd = new wemoCmd();
			$wemoCmd->setName(__('Off', __FILE__));
			$wemoCmd->setEqLogic_id($this->getId());
			$wemoCmd->setConfiguration('request', 'off');
			$wemoCmd->setType('action');
			$wemoCmd->setSubType('other');
			$wemoCmd->setDisplay('generic_type','OFF');
			$wemoCmd->save();
			
			$wemoCmd = new wemoCmd();
			$wemoCmd->setName(__('Clignote', __FILE__));
			$wemoCmd->setEqLogic_id($this->getId());
			$wemoCmd->setConfiguration('request', 'blink');
			$wemoCmd->setType('action');
			$wemoCmd->setSubType('other');
			$wemoCmd->save();	
			
		}elseif(in_array($this->getConfiguration('model_name'), array('Motion'))){
			$wemoCmd = new wemoCmd();
			$wemoCmd->setName(__('Etat', __FILE__));
			$wemoCmd->setEqLogic_id($this->getId());
			$wemoCmd->setConfiguration('request', 'state');
			$wemoCmd->setType('info');
			$wemoCmd->setSubType('binary');
			$wemoCmd->save();
		}
	}



    public function execute($_options = null) {
    	$wemo=$this->getEqLogic();
		if ($this->type == 'action') {
    		$pest = new Pest('127.0.0.1:5000');
    		try {
    			$url='/api/device/'.rawurlencode($wemo->getConfiguration('name')).'?state='.$this->getConfiguration('request');
    			//log::add('wemo', 'info', 'url='.$url);
    		    $request = $pest->post($url);
    		    log::add('wemo', 'info', 'Action '.$url.' ok');
    			return TRUE;
    		} catch (Pest_NotFound $e) {
    		    // 404
    		    log::add('wemo', 'warn', 'device='.$wemo->getConfiguration('name').' not found !');
    		    message::add('wemo', 'device not found');
    		    echo $e->getMessage();
      			echo "\n";
      			return false;
    		}
		}else{
			//return true;	
			return $this->getValue();
		}
                
        return $response;
	}

    /*     * **********************Getteur Setteur*************************** */
}

?>
