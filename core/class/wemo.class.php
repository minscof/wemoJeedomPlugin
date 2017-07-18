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
	$pest = new Pest('127.0.0.1:5000');
		try {
		    $request = $pest->get('/api/environment');
			$devices = json_decode($request,true);
			foreach($devices as $device) {
				$eqLogics = eqLogic::byTypeAndSearhConfiguration('wemo',$device['serialnumber']);
			if(count($eqLogics) == 0){
				log::add('wemo', 'info', 'nouvel equipement, création', 'config');
				$eqLogic = new eqLogic();
	            $eqLogic->setEqType_name('wemo');
	            $eqLogic->setIsEnable(1);
	            $eqLogic->setName($device['name']);
	            $eqLogic->setConfiguration('name',$device['name']);
	            $eqLogic->setConfiguration('host',$device['host']);
				$eqLogic->setConfiguration('model',$device['model']);
				$eqLogic->setConfiguration('serialnumber',$device['serialnumber']);
	            $eqLogic->setConfiguration('type',$device['type']);
				$eqLogic->setIsVisible(1);
	            $eqLogic->save();
	            $eqLogic = self::byId($eqLogic->getId());
	            $include_device = $eqLogic->getId();
				
				if($device['type']=="Switch" || $device['type']=="Insight" || $device['type']=="Lightswitch"){
					$wemoCmd = new wemoCmd();
			        $wemoCmd->setName(__('Etat', __FILE__));
			        $wemoCmd->setEqLogic_id($include_device);
			        $wemoCmd->setConfiguration('request', 'state');
			        $wemoCmd->setType('info');
			        $wemoCmd->setSubType('binary');
			        $wemoCmd->setDisplay('generic_type','ENERGY_STATE');
			        $wemoCmd->save();
					
					$wemoCmd = new wemoCmd();
			        $wemoCmd->setName(__('On', __FILE__));
			        $wemoCmd->setEqLogic_id($include_device);
			        $wemoCmd->setConfiguration('request', 'on');
			        $wemoCmd->setType('action');
			        $wemoCmd->setSubType('other');
			        $wemoCmd->setDisplay('generic_type','ENERGY_ON');
			        $wemoCmd->save();
					
					$wemoCmd = new wemoCmd();
			        $wemoCmd->setName(__('Off', __FILE__));
			        $wemoCmd->setEqLogic_id($include_device);
			        $wemoCmd->setConfiguration('request', 'off');
			        $wemoCmd->setType('action');
			        $wemoCmd->setSubType('other');
			        $wemoCmd->setDisplay('generic_type','OFF');
			        $wemoCmd->save();
					
					$wemoCmd = new wemoCmd();
			        $wemoCmd->setName(__('Clignote', __FILE__));
			        $wemoCmd->setEqLogic_id($include_device);
			        $wemoCmd->setConfiguration('request', 'blink');
			        $wemoCmd->setType('action');
			        $wemoCmd->setSubType('other');
			        $wemoCmd->save();	
					
				}elseif($device['type']=="Motion"){
					$wemoCmd = new wemoCmd();
			        $wemoCmd->setName(__('Etat', __FILE__));
			        $wemoCmd->setEqLogic_id($include_device);
			        $wemoCmd->setConfiguration('request', 'state');
			        $wemoCmd->setType('info');
			        $wemoCmd->setSubType('binary');
			        $wemoCmd->save();
				}
				
			}
			}
		} catch (Pest_NotFound $e) {
		    // 404
		    echo $e->getMessage();
  			echo "\n";
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
    

    /*     * **********************Getteur Setteur*************************** */
}
}
?>
