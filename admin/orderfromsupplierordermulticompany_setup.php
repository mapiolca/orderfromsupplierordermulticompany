<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2013 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		admin/orderfromsupplierordermulticompany.php
 * 	\ingroup	orderfromsupplierordermulticompany
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
require('../config.php');

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/orderfromsupplierordermulticompany.lib.php';
dol_include_once('abricot/includes/lib/admin.lib.php');
//require_once "../class/myclass.class.php";
// Translations
$langs->load("orderfromsupplierordermulticompany@orderfromsupplierordermulticompany");

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */

if($action == 'setconststatus') {

    $res = dolibarr_set_const($db, 'OFSOM_STATUS', GETPOST('OFSOM_STATUS'), 'chaine', 1, '', $conf->entity);

}

/*
 * View
 */
$page_name = "orderfromsupplierordermulticompanySetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = orderfromsupplierordermulticompanyAdminPrepareHead();
$notab = -1;
print dol_get_fiche_head(
    $head,
    'settings',
    $langs->trans("Module104200Name"),
	$notab,
    "orderfromsupplierordermulticompany@orderfromsupplierordermulticompany"
);

print dol_get_fiche_end($notab);

// Setup page goes here
	echo '<h3>'.$langs->trans("orderfromsupplierordermulticompanySetupPage").'</h3>';

	print '<div class="warning" >'.$langs->trans('ThisEntityMappingNeedToBeDoneOnEachEntityListed').'</div>';

	$ATMdb=new TPDOdb;
	if(isset($_REQUEST['action']) && $_REQUEST['action']=='save') {

	    if(!empty($_REQUEST['TLine'])) {
			foreach($_REQUEST['TLine'] as $id=>$TValues) {

			    $TValues['fk_entity'] = GETPOST('TLine_'.$TValues['rowid'].'_fk_entity', 'int');
			    $TValues['fk_soc'] = GETPOST('TLine_'.$TValues['rowid'].'_fk_soc', 'int');

				$o=new TTELink;
				if($id>0 ) $o->load($ATMdb, $id);
				else{

					if($TValues['fk_soc']>0 && $TValues['fk_entity']>0) {
						null;
					}
					else{
						continue; // non valide on passe au cycle suivant
					}

				}


				$o->set_values($TValues);

				$o->entity = $conf->entity;

				if(isset($TValues['delete'])) {
					$o->delete($ATMdb);
				}
				else {
					$o->save($ATMdb);
				}
			}
		}

	}


	$TLink = TTELink::getList($ATMdb);

	$form=new TFormCore($_SERVER['PHP_SELF'],'form1','POST');
	$form->Set_typeaff('edit');
	echo $form->hidden('action', 'save');

	?>
	<table class="liste">
		<tr class="liste_titre">
			<td><?php echo $langs->trans('Company'); ?></td>
			<td><?php echo $langs->trans('Entity'); ?></td>
			<td><?php echo $langs->trans('Delete'); ?> ?</td>
		</tr>
	<?php

	$html=new Form($db);
	$m=new ActionsMulticompany($db);

	foreach($TLink as $link) {

		?>
			<tr>
				<td><?php print $html->select_company($link->fk_soc,'TLine_'.$link->rowid.'_fk_soc','',1);  ?></td>
				<td><?php print $m->select_entities($link->fk_entity,'TLine_'.$link->rowid.'_fk_entity' ); ?></td>
				<td><input type="hidden" name="TLine[<?php echo $link->rowid ?>][rowid]" value="<?php echo $link->rowid ?>" /><input type="checkbox" value="1" name="TLine[<?php echo $link->rowid ?>][delete]"/></td>
			</tr>
		<?php

	}
		?><tr class="liste_titre">
				<td><?php print $html->select_company(-1,'TLine_0_fk_soc','',1);  ?></td>
				<td><?php print $m->select_entities(-1,'TLine_0_fk_entity' ); ?></td>
				<td><input type="hidden" name="TLine[0][rowid]" value="0" /> <?php $langs->trans('Nouvelle liaison'); ?></td>
			</tr>
	</table>
	<?php

	echo '<div class="tabsAction">'. $form->btsubmit("Enregistrer", "bt_submit") .'</div>';

	echo $form->end_form();


$form= new Form($db);
$TTriggers = array("ORDER_SUPPLIER_VALIDATE" => "Valider", "ORDER_SUPPLIER_SUBMIT" => "Commander");

print '<table class="liste">';

setup_print_title('ParamOFSOMStatus');
print '<form method="post" action="' . $_SERVER['PHP_SELF'] . '" enctype="multipart/form-data" >';
print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
print '<input type="hidden" name="action" value="setconststatus">';

print '<tr  class="oddeven"><td>' . $langs->trans("OFSOMStatusConf") . '</td>';
print '<td align="left">';

print $form->selectarray('OFSOM_STATUS', $TTriggers, $conf->global->OFSOM_STATUS, 0, '');

print '</td>';
print '<td colspan="3" align="right"><input type="submit" class="button" value="' . $langs->trans("Save") . '"></td>';
print '</tr>';

print '</form>';

setup_print_on_off('OFSOM_LINK_STATUSSUPPLIERORDER_ORDERCHILD', $langs->trans('OFSOMLinkStatusSupplierOrderOrderChild'));
setup_print_on_off('OFSOM_UPDATE_LINE_SOURCE', $langs->trans('OFSOMUpdateLineSource'));
setup_print_on_off('OFSOM_UPDATE_ORDER_SOURCE', $langs->trans('OFSOMUpdateOrderSource'));

//  Passe la commande fournisseur au statut « reçue » (entité A) lors de la clôture de l'expédition (Entité B)
setup_print_on_off('OFSOM_SET_SUPPLIER_ORDER_RECEIVED_ON_SUPPLIER_SHIPMENT_CLOSED');

// Création automatique de la facture fournisseur lors de la validation d'une facture client vers une entité multicompany
setup_print_on_off('OFSOM_AUTO_CREATE_SUPPLIER_INVOICE');

print '</table>';


if (empty($conf->global->OFSOM_STATUS))
{
    dolibarr_set_const($db, 'OFSOM_STATUS', 'ORDER_SUPPLIER_VALIDATE', 'chaine', 0, '', $conf->entity);
}

llxFooter();

$db->close();
