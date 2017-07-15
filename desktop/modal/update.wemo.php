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
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
?>
<div id='div_updateWemoAlert' style="display: none;"></div>
<pre id='pre_wemoupdate' style='overflow: auto; height: 90%;with:90%;'></pre>


<script>
	$.ajax({
		type: 'POST',
		url: 'plugins/wemo/core/ajax/wemo.ajax.php',
		data: {
			action: 'updateWemo'
		},
		dataType: 'json',
		global: false,
		error: function (request, status, error) {
			handleAjaxError(request, status, error, $('#div_updateWemoAlert'));
		},
		success: function () {
			getWemoLog(1);
		}
	});
	function getWemoLog(_autoUpdate) {
		$.ajax({
			type: 'POST',
			url: 'core/ajax/log.ajax.php',
			data: {
				action: 'get',
				logfile: 'wemo_update',
			},
			dataType: 'json',
			global: false,
			error: function (request, status, error) {
				setTimeout(function () {
					getJeedomLog(_autoUpdate, _log)
				}, 1000);
			},
			success: function (data) {
				if (data.state != 'ok') {
					$('#div_alert').showAlert({message: data.result, level: 'danger'});
					return;
				}
				var log = '';
				var regex = /<br\s*[\/]?>/gi;
				for (var i in data.result.reverse()) {
					log += data.result[i][2].replace(regex, "\n");
				}
				$('#pre_wemoupdate').text(log);
				$('#pre_wemoupdate').scrollTop($('#pre_wemoupdate').height() + 200000);
				if (!$('#pre_wemoupdate').is(':visible')) {
					_autoUpdate = 0;
				}
				if (init(_autoUpdate, 0) == 1) {
					setTimeout(function () {
						getWemoLog(_autoUpdate)
					}, 1000);
				}
			}
		});
	}
</script>