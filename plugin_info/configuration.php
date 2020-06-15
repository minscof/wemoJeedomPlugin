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
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
?>
<form class="form-horizontal">
    <fieldset>
	<div class="form-group">
			<label class="col-lg-4 control-label">{{Adresse IP du serveur
				wemo}}</label>
			<div class="col-lg-2">
				<input class="configKey form-control" data-l1key="wemoIp"
					value="127.0.0.1" placeholder="{{adr Ip}}" />
			</div>
		</div>
		<div class="form-group">
			<label class="col-lg-4 control-label">{{Port du serveur}}</label>
			<div class="col-lg-2">
				<input class="configKey form-control" data-l1key="wemoPort"
					value="5000" placeholder="{{n° port}}" />
			</div>
		</div>
		<div class="form-group">
			<label class="col-lg-4 control-label">{{Utilisateur}}</label>
			<div class="col-lg-2">
				<input class="configKey form-control" data-l1key="wemoUser"
					value="root" placeholder="{{utilisateur}}" />
			</div>
		</div>
		<div class="form-group">
			<label class="col-lg-4 control-label">{{Mot de passe}}</label>
			<div class="col-lg-2">
				<input class="configKey form-control" data-l1key="wemoPassword"
					value="password" placeholder="{{mot de passe}}" />
			</div>
		</div>
        <?php if (exec('sudo cat /etc/sudoers')<>"") {?>

	    <div class="form-group">
	        <label class="col-lg-4 control-label">{{Installer/Mettre à jour les dépendances}}</label>
	        <div class="col-lg-3">
	            <a class="btn btn-danger" id="bt_installDeps"><i class="fa fa-check"></i> {{Lancer}}</a>
	        </div>
	    </div>
	    <?php }else{?>
	    <div class="form-group">
	        <label class="col-lg-4 control-label">{{Installation automatique impossible}}</label>
	        <div class="col-lg-8">
	            {{Veuillez lancer la commande suivante :}} wget http://127.0.0.1/jeedom/plugins/wemo/resources/install.sh -v -O install.sh; ./install.sh
	        </div>
	    </div>
	    <?php }?>
	    <script>
	        $('#bt_installDeps').on('click',function(){
	            bootbox.confirm('{{Etes-vous sûr de vouloir installer/mettre à jour les dépendances ? }}', function (result) {
	              if (result) {
					  $('#md_modal').dialog({title: "{{Installation / Mise à jour}}"});
	                  $('#md_modal').load('index.php?v=d&plugin=wemo&modal=update.wemo').dialog('open');
	            }
	        });
	        });
	        </script>
	 </fieldset>
</form>