<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

$eqLogics=eqLogic::byType('wemo');
sendVarToJS('eqType', 'wemo');

$deamonRunning = false;

$deamonRunning = wemo::deamonRunning();
if (!$deamonRunning) {
    echo '<div class="alert alert-danger">Le démon wemo ne tourne pas</div>';
}

?>
<div class="row row-overflow">
    <div class="col-lg-2">
        <div class="bs-sidebar">
            <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
                <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
                <?php
                foreach ($eqLogics as $eqLogic) {
                    echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '"><a>' . $eqLogic->getHumanName(true, true) . '</a></li>';
                }
                ?>
            </ul>
        </div>
    </div>
     <div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
   <legend><i class="fa fa-cog"></i>  {{Gestion}}</legend>
   <div class="eqLogicThumbnailContainer">
	 <div class="cursor eqLogicAction" data-action="gotoPluginConf" style="background-color : #ffffff; height : 140px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;">
      <center>
        <i class="fa fa-wrench" style="font-size : 5em;color:#767676;"></i>
      </center>
      <span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#767676"><center>{{Configuration}}</center></span>
    </div>

	<div class="cursor expertModeVisible" id="bt_syncEqLogic" style="background-color : #ffffff; height : 140px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
      <center>
        <i class="fa fa-refresh" style="font-size : 5em;color:#767676;"></i>
      </center>
      <span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#767676"><center>{{Synchroniser}}</center></span>
    </div>
  </div>
        <legend><i class="techno-cable1"></i> {{Mes Wemos}}
        </legend>
        <?php
        if (count($eqLogics) == 0) {
            echo "<br/><br/><br/><center><span style='color:#767676;font-size:1.2em;font-weight: bold;'>{{Vous n'avez pas encore de Wemo, cliquez sur Synchroniser pour commencer}}</span></center>";
        } else {
            ?>
            <div class="eqLogicThumbnailContainer">
                <?php
                foreach ($eqLogics as $eqLogic) {
                    $opacity = '';
                    if ($eqLogic->getIsEnable() != 1) {
                        $opacity = ' -webkit-filter: grayscale(100%); -moz-filter: grayscale(100);
                                -o-filter: grayscale(100%);  -ms-filter: grayscale(100%);  filter: grayscale(100%); opacity: 0.35;';
                    }
                    echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;' . $opacity . '" >';
                    echo "<center>";
                    $file = "plugins/hdmiCec/core/template/images/" . $eqLogic->getConfiguration('model') . ".png";
                    if (file_exists($file)) {
                        $path = $file;
                    } else {
                        $path = 'plugins/wemo/doc/images/wemo_icon.png';
                    }
                    echo '<img src="' . $path . '" height="105"  />';
                    echo "</center>";
                    echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $eqLogic->getHumanName(true, true) . '</center></span>';
                    echo '</div>';
                }
                ?>
            </div>
        <?php } ?>
    </div>
    <div class="col-lg-10 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
        <form class="form-horizontal">
            <fieldset>
                <legend><i class="fa fa-arrow-circle-left eqLogicAction cursor" data-action="returnToThumbnailDisplay"></i> {{Général}}<i class='fa fa-cogs eqLogicAction pull-right cursor expertModeVisible' data-action='configure'></i></legend>
                <div class="form-group">
                    <label class="col-lg-2 control-label">{{Nom de l'équipement}}</label>
							<div class="col-lg-3">
								<input type="text" class="eqLogicAttr form-control"
									data-l1key="id" style="display: none;" /> <input type="text"
									class="eqLogicAttr form-control" data-l1key="name"
									placeholder="{{Nom de l'équipement}}" />
							</div>

							<label class="col-lg-3 control-label">{{Objet parent}}</label>
							<div class="col-lg-3">
								<select id="sel_object" class="eqLogicAttr form-control"
									data-l1key="object_id">
                            <option value="">{{Aucun}}</option>
                            <?php
                            foreach (jeeObject::all() as $object) {
                                echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-lg-2 control-label">{{Catégorie}}</label>
                    <div class="col-lg-9">
                        <?php
                        foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                            echo '<label class="checkbox-inline">';
                            echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                            echo '</label>';
                        }
                        ?>

                    </div>
                </div>
                <div class="form-group">
							<label class="col-sm-2 control-label"></label>
							<div class="col-sm-9">
								<label class="checkbox-inline"><input type="checkbox"
									class="eqLogicAttr" data-l1key="isEnable" checked />{{Activer}}</label>
								<label class="checkbox-inline"><input type="checkbox"
									class="eqLogicAttr" data-l1key="isVisible" checked />{{Visible}}</label>
							</div>
                        </div>
                        
                        <legend>
							<i class="fa fa-wrench"></i> {{Configuration}}
						</legend>
                <div class="form-group">
                    <label class="col-lg-2 control-label">{{Adresse IP}}</label>
                    <div class="col-lg-3">
                        <input type="text" id="host" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="host" placeholder="{{Adresse IP}}" disabled/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-lg-2 control-label">{{Modèle}}</label>
                    <div class="col-lg-3">
                        <input type="text" id="model" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="model" placeholder="{{Modèle}}" disabled/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-lg-2 control-label">{{N° de série}}</label>
                    <div class="col-lg-3">
                        <input type="text" id="serialNumber" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="serialNumber" placeholder="{{N° de série}}" disabled/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-lg-2 control-label">{{Type}}</label>
                    <div class="col-lg-3">
                        <input type="text" id="modelName" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="modelName" placeholder="{{modelName}}" disabled/>
                    </div>
                </div>
                
            </fieldset> 
        </form>

        <legend>{{Tableau des commandes}}</legend>
        <a class="btn btn-success btn-sm cmdAction" data-action="add"><i class="fa fa-plus-circle"></i>{{ Ajouter une commande}}</a><br/><br/>
        <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th style="width: 190px;">{{Nom}}</th>
                    <th style="width: 130px;">{{Type}}</th>
                    <th style="width: 150px;">{{Paramètres}}</th>
                    <th style="width: 100px;"></th>
                </tr>
            </thead>
            <tbody>

            </tbody>
        </table>

        <form class="form-horizontal">
            <fieldset>
                <div class="form-actions">
                    <a class="btn btn-danger eqLogicAction" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
                    <a class="btn btn-success eqLogicAction" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
                </div>
            </fieldset>
        </form>

    </div>
</div>

<?php include_file('desktop', 'wemo', 'js', 'wemo'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>