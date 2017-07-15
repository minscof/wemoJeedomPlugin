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
	            {{Veuillez lancer la commande suivante :}} wget http://127.0.0.1/jeedom/plugins/wemo/ressources/install.sh -v -O install.sh; ./install.sh
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