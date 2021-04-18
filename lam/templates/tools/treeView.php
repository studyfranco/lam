<?php
namespace LAM\TOOLS\TREEVIEW;

/*

  This code is part of LDAP Account Manager (http://www.ldap-account-manager.org/)
  Copyright (C) 2021  Roland Gruber

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

use htmlDiv;
use htmlForm;
use htmlJavaScript;
use htmlOutputText;
use htmlResponsiveRow;

/**
* LDAP tree view.
*
* @author Roland Gruber
* @package tools
*/

/** security functions */
include_once(__DIR__ . "/../../lib/security.inc");
/** access to configuration options */
include_once(__DIR__ . "/../../lib/config.inc");

// start session
startSecureSession();
enforceUserIsLoggedIn();
validateSecurityToken();

checkIfToolIsActive('TreeViewTool');

setlanguage();

include __DIR__ . '/../../lib/adminHeader.inc';
echo '<link rel="stylesheet" href="../../style/jstree/style.css" />';
echo '<script src="../lib/extra/jstree/jstree.js"></script>';
echo '<div class="smallPaddingContent">';

$toolSettings = $_SESSION['config']->getToolSettings();

if (empty($toolSettings[TreeViewTool::TREE_SUFFIX_CONFIG][0])) {
	StatusMessage('ERROR', _('Please configure the tree suffix in your LAM server profile settings.'));
}
else {
	showTree();
}

echo '</div>';
include __DIR__ . '/../../lib/adminFooter.inc';

function showTree() {
	$toolSettings = $_SESSION['config']->getToolSettings();
	$rootDn = $toolSettings[TreeViewTool::TREE_SUFFIX_CONFIG];
	$row = new htmlResponsiveRow();
	$row->setCSSClasses(array('maxrow'));
	$row->add(new htmlDiv('ldap_tree', new htmlOutputText(''), array('tree-view--tree')), 12, 5);
	$row->add(new htmlDiv('ldap_actionarea', new htmlOutputText(''), array('tree-view--actionarea')), 12, 7);
	$newMenu = '';
	if (checkIfWriteAccessIsAllowed()) {
		$newMenu = '"createNode": {
								"label": "' . _('Create a child entry') . '",
								"icon": "../../graphics/add.png",
								"action": function(obj) {
									window.lam.treeview.createNode("' . getSecurityTokenName() . '",
										"' . getSecurityTokenValue() . '",
										node,
										tree)
								}
							},';
	}
	$deleteMenu = '';
	if (checkIfWriteAccessIsAllowed()) {
		$deleteMenu = '"deleteNode": {
								"label": "' . _('Delete') . '",
								"icon": "../../graphics/del.png",
								"action": function(obj) {
									window.lam.treeview.deleteNode("' . getSecurityTokenName() . '",
										"' . getSecurityTokenValue() . '",
										node,
										tree,
										"' . _('Delete') . '",
										"' . _('Cancel') . '",
										"' . _('Delete this entry') . '",
										"' . _('Ok') . '",
										"' . _('Error') . '")
								}
							},';
	}
	$exportMenu = '';
	if ($_SESSION['config']->isToolActive('ImportExport')) {
		$exportMenu = '"exportNode": {
								"label": "' . _('Export') . '",
								"icon": "../../graphics/export.png",
								"action": function(obj) {
									window.location.href = "../tools/importexport.php?tab=export&dn=" + node.id;
								}
							},';
	}
	$treeScript = new htmlJavaScript('
		jQuery(document).ready(function() {
			var maxHeight = jQuery(document).height() - jQuery("#ldap_tree").offset().top - 50;
			jQuery("#ldap_tree").css("max-height", maxHeight);
			jQuery("#ldap_actionarea").css("max-height", maxHeight);
			jQuery(\'#ldap_tree\').jstree({
				"plugins": [
					"contextmenu",
					"changed"
				],
				"contextmenu": {
					"items": function(node) {
						var tree = jQuery.jstree.reference("#ldap_tree");
						var menuItems = {' .
								$newMenu .
								$deleteMenu .
								'"refreshNode": {
								"label": "' . _('Refresh') . '",
								"icon": "../../graphics/refresh.png",
								"action": function(obj) {
									tree.refresh_node(node);
									window.lam.treeview.getNodeContent("' . getSecurityTokenName() . '", "' . getSecurityTokenValue() . '", node.id);
								}
							},
							' .
							$exportMenu .
						'};
						return menuItems;
					}
				},
				"core": {
					"worker": false,
					"strings": {
						"Loading ...": "' . _('Loading') . '"
					},
					"data": function(node, callback) {
						window.lam.treeview.getNodes("' . getSecurityTokenName() . '", "' . getSecurityTokenValue() . '", node, callback);
					}
				}
			})
			.on("changed.jstree", function (e, data) {
				if (data && data.action && (data.action == "select_node")) {
					var node = data.node;
					window.lam.treeview.getNodeContent("' . getSecurityTokenName() . '", "' . getSecurityTokenValue() . '", node.id);
				}
			});
		});
	');
	$row->add($treeScript, 12);

	$deleteDialogContent = new htmlResponsiveRow();
	$deleteDialogContent->add(new htmlOutputText(_('Do you really want to delete this entry?')), 12);
	$deleteDialogContent->addVerticalSpacer('0.5rem');
	$deleteDialogEntryText = new htmlOutputText('');
	$deleteDialogEntryText->setCSSClasses(array('treeview-delete-entry'));
	$deleteDialogContent->add($deleteDialogEntryText, 12);
	$deleteDialogDiv = new htmlDiv('treeview_delete_dlg', $deleteDialogContent, array('hidden'));
	$row->add($deleteDialogDiv, 12);

	$errorDialogContent = new htmlResponsiveRow();
	$errorDialogEntryTitle = new htmlOutputText('');
	$errorDialogEntryTitle->setCSSClasses(array('treeview-error-title'));
	$errorDialogContent->add($errorDialogEntryTitle, 12);
	$errorDialogEntryText = new htmlOutputText('');
	$errorDialogEntryText->setCSSClasses(array('treeview-error-text'));
	$errorDialogContent->add($errorDialogEntryText, 12);
	$errorDialogDiv = new htmlDiv('treeview_error_dlg', $errorDialogContent, array('hidden'));
	$row->add($errorDialogDiv, 12);

	$row->add(new htmlJavaScript('jQuery(document).ready(function() {
					jQuery(\'form[name="actionarea"]\').validationEngine({promptPosition: "topLeft", addFailureCssClassToField: "lam-input-error", autoHidePrompt: true, autoHideDelay: 5000});
				});'), 12);

	$tabIndex = 1;
	$form = new htmlForm('actionarea', 'treeView.php', $row);
	parseHtml(null, $form, array(), true, $tabIndex, 'none');
}
