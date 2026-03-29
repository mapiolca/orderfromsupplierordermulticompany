<?php

/*
 * Lien entre thirdpartie et entité
 *
 */

class TTELink extends TObjetStd
{

	function __construct()
	{

		parent::set_table(MAIN_DB_PREFIX . 'thirdparty_entity');
		parent::add_champs('fk_entity,fk_soc,entity', 'type=entier;index;');//fk_soc_leaser

		parent::_init_vars();
		parent::start();

	}


	/**
	 * @var int The object identifier
	 */
	public $id;

	/** @var string $table_element Table name in SQL */
	public $table_element = 'thirdparty_entity';

	/** @var string $element Name of the element (tip for better integration in Dolibarr: this value should be the reflection of the class name with ucfirst() function) */
	public $element = 'thirdparty_entity'; // need to be compatible to be compatible with dolGetElementUrl()  or linkedobjectblock.tpl.php call

	/** @var string $picto a picture file in [@...]/img/object_[...@].png */
	public $picto = 'orderfromsupplierordermulticompany@orderfromsupplierordermulticompany';


	public $error;

	/**
	 * Entite du dolibarr pour lequel appartient cet config
	 * @var int $entity
	 */
	public $entity;

	/**
	 * Entité du fournisseur cible
	 * @var int $fk_entity
	 */
	public $fk_entity;

	/**
	 * Id du Fournisseur cible
	 * @var int $fk_soc
	 */
	public $fk_soc;


	static function getList(&$ATMdb)
	{
		global $conf;

		$Tab = array();

		$Tab = $ATMdb->ExecuteAsArray("SELECT rowid,fk_soc,fk_entity FROM " . MAIN_DB_PREFIX . "thirdparty_entity WHERE entity IN (" . $conf->entity . ") ORDER BY rowid ASC");

		return $Tab;
	}


	/**
	 * Retourne l'ID du tiers correspondant à l'entité ciblée dans l'entité courante
	 * @param int $targetEntity
	 * @param int $currentEntity
	 * @return int 	<=0 if KO, >0 if OK
	 */
	public function getSocIdFromEntity($targetEntity, $currentEntity = false){
		global $db, $conf, $langs;

		if(!$currentEntity) {
			$currentEntity = $conf->entity;
		}

		$res = $db->query("SELECT fk_soc FROM " . MAIN_DB_PREFIX . "thirdparty_entity WHERE entity=" . intval($currentEntity) . " AND fk_entity=" . intval($targetEntity));
		if($res){
			if($db->num_rows($res)>0){
				$obj = $db->fetch_object($res);
				return $obj->fk_soc;
			}
			else{
				$this->error = $langs->trans('MissingEntityLinkBetweenSoc');
				return 0;
			}
		}
		else
		{
			$this->error = $db->error();
			return -1;
		}
	}
	/**
	 * TODO : trouver un nom qui veut dire quelque chose pour cette methode
	 * Retourne l'ID du tiers correspondant à la société fournie depuis  l'entité  courante mais pour l'entité liée
	 * @param int $socid
	 * @param int $currentEntity
	 * @return int 	<=0 if KO, >0 if OK
	 */
	public function getSocIdForEntityCustomerFromSupplierEntitySocId($socid, $currentEntity = false){
		$customerEntity = $this->getSocEntityFromSocId($socid, $currentEntity);
		if($customerEntity > 0){
			// Récupération du soc id du tiers client de l'expédition
			$customerId = $this->getSocIdFromEntity($currentEntity, $customerEntity);
			if($customerId) {
				return intval($customerId);
			}
		}
		elseif ($customerEntity < 0){
			return -1;
		}

		return 0;
	}



	/**
	 * Retourne l'entité correspondant  au tiers dans l'entité courante
	 * @param $fk_soc
	 * @param int $currentEntity
	 * @return int	<=0 if KO, >0 if OK
	 */
	public function getSocEntityFromSocId($fk_soc, $currentEntity = false){
		global $db, $conf, $langs;

		if(!$currentEntity) {
			$currentEntity = $conf->entity;
		}
		$res = $db->query("SELECT fk_entity FROM " . MAIN_DB_PREFIX . "thirdparty_entity WHERE entity=" . intval($currentEntity) . " AND fk_soc=" . intval($fk_soc) . ' AND fk_entity <> ' . intval($currentEntity));
		if($res){
			if($db->num_rows($res)>0){
				$obj = $db->fetch_object($res);
				return $obj->fk_entity;
			}
			else{
				$this->error = $langs->trans('MissingEntityLinkBetweenSoc');
				return 0;
			}
		}
		else
		{
			$this->error = $db->error();
			return -1;
		}
	}



	/**
	 * Crée une facture fournisseur dans l'entité cible à partir d'une facture client
	 * @param int $idInvoiceSource ID de la facture client source
	 * @param int $toEntity ID de l'entité cible
	 * @return int >0 if OK, <0 if KO
	 */
	public function cloneInvoice($idInvoiceSource, $toEntity)
	{
		global $db, $conf, $user, $langs;

		dol_include_once('/compta/facture/class/facture.class.php');
		dol_include_once('/fourn/class/fournisseur.facture.class.php');

		$facture = new Facture($db);
		$facture->fetch($idInvoiceSource);
		$facture->fetch_lines();

		// Récupération du tiers correspondant à l'entité source dans l'entité cible (le fournisseur côté entité cible)
		$fk_soc = $this->getSocIdFromEntity($conf->entity, $toEntity);
		if ($fk_soc <= 0) {
			return -1;
		}

		// Vérifier si une facture fournisseur existe déjà pour cette facture client
		$existingInvoiceId = $this->getSupplierInvoiceIdFromInvoice($facture, $toEntity);
		if ($existingInvoiceId > 0) {
			// La facture existe déjà dans le système en face. On la supprime pour la recréer
			$fi = new FactureFournisseur($db);

			$previous_entity = $conf->entity;
			$conf->entity = $toEntity;
			if ($fi->fetch($existingInvoiceId) > 0) {
				$delRes = $fi->delete($user);
				if ($delRes < 0) {
					$this->error = $fi->error;
					$conf->entity = $previous_entity;
					return -3;
				}
			} else {
				$conf->entity = $previous_entity;
				return -2;
			}
			$conf->entity = $previous_entity;
		}

		$fi = new FactureFournisseur($db);
		$fi->date = $facture->date;
		$fi->ref_supplier = $facture->ref;
		$fi->socid = $fk_soc;
		$fi->fk_project = $facture->fk_project;
		$fi->note_public = $facture->note_public;
		$fi->note_private = $facture->note_private;
		$fi->cond_reglement_id = $facture->cond_reglement_id;
		$fi->mode_reglement_id = $facture->mode_reglement_id;
		$fi->lines = array();

		foreach ($facture->lines as $line) {
			$lineInvoice = new SupplierInvoiceLine($db);

			$TPropertiesToClone = array('desc', 'qty', 'tva_tx', 'vat_src_code', 'localtax1_tx', 'localtax2_tx', 'fk_product', 'remise_percent', 'info_bits', 'date_start', 'date_end', 'product_type', 'rang', 'special_code', 'fk_parent_line', 'label', 'array_options', 'fk_unit');

			foreach ($TPropertiesToClone as $property) {
				if (isset($line->{$property})) {
					$lineInvoice->{$property} = $line->{$property};
				}
			}

			// Le prix unitaire de la facture client devient le prix unitaire de la facture fournisseur
			$lineInvoice->pu_ht = $line->subprice;
			$lineInvoice->subprice = $line->subprice;

			$fi->lines[] = $lineInvoice;
		}

		$invoiceCreatedRes = $fi->create($user);

		if ($invoiceCreatedRes < 0) {
			$this->error = $fi->error;
			return -4;
		}

		// Transfert de la facture fournisseur dans l'entité cible
		$res = $db->query("UPDATE " . MAIN_DB_PREFIX . "facture_fourn SET entity=" . intval($toEntity) . " WHERE rowid=" . intval($fi->id));
		if (!$res) {
			return -5;
		}

		// Lien entre la facture client et la facture fournisseur dans la table element_element
		$sql = "INSERT INTO " . MAIN_DB_PREFIX . "element_element (";
		$sql .= "fk_source";
		$sql .= ", sourcetype";
		$sql .= ", fk_target";
		$sql .= ", targettype";
		$sql .= ") VALUES (";
		$sql .= intval($idInvoiceSource);
		$sql .= ", 'facture'";
		$sql .= ", " . intval($fi->id);
		$sql .= ", 'invoice_supplier'";
		$sql .= ")";
		$res = $db->query($sql);
		if (!$res) {
			return -6;
		}

		return $invoiceCreatedRes;
	}

	/**
	 * Permet de récupérer l'ID de la facture fournisseur côté entité cible à partir de la facture client
	 * @param Facture $invoice facture client source
	 * @param int $targetEntity entité cible
	 * @return int <=0 if KO, >0 if OK
	 */
	public function getSupplierInvoiceIdFromInvoice($invoice, $targetEntity)
	{
		$targetSocid = $this->getSocIdFromEntity($targetEntity, $invoice->entity);

		if ($targetSocid <= 0) {
			return -1;
		}

		$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "facture_fourn"
			. " WHERE fk_soc = " . intval($targetSocid) . " AND entity=" . intval($targetEntity) . " AND ref_supplier='" . $invoice->db->escape($invoice->ref) . "' ";

		$res = $invoice->db->query($sql);

		if (!$res) {
			$this->error = $invoice->db->error();
			return -1;
		}

		$obj = $invoice->db->fetch_object($res);

		if ($obj && $obj->rowid > 0) {
			return $obj->rowid;
		}

		return 0;
	}

	/**
	 * @param int $idOrderSource
	 * @param int $toEntity
	 */
	public function cloneOrder($idOrderSource, $toEntity)
	{
		global $db, $conf, $user, $mc, $langs;

		$cf = new CommandeFournisseur($db);
		$cf->fetch($idOrderSource);

		$fk_soc = $this->getSocIdFromEntity($conf->entity,$toEntity);
		if($fk_soc>0) {

			dol_include_once('/commande/class/commande.class.php');

			$existingOrderId =  $this->getOrderIdFromSupplierOrder($cf, $toEntity);
			if ($existingOrderId > 0) {
				// la facture commande déjà dans le système en face. On la supprime
				$o = new Commande($db);

				$previous_entity = $conf->entity;
				$conf->entity = $toEntity;
				if ($o->fetch($existingOrderId) > 0) {
					$delRes = $o->delete($user);
					if($delRes<0){
						$this->error = $o->error;
						return -3;
					}
				}
				else{
					return -2;
				}

				$conf->entity = $previous_entity;
			}

			$o = new Commande($db);
			$o->date = date('Y-m-d H:i:s');
			$o->ref_client = $cf->ref;
			$o->socid = $fk_soc;

			$o->fk_project = $cf->fk_project; //TODO check if it's shared project
			$o->lines = array();

			foreach ($cf->lines as $line) {
				$lineOrder = new OrderLine($db);
				$lineOrder->origin = 'supplierorderdet';
				$TPropertiesToClone = array('desc', 'subprice', 'qty', 'tva_tx', 'vat_src_code', 'localtax1_tx', 'localtax2_tx', 'fk_product', 'remise_percent', 'info_bits', 'fk_remise_except', 'date_start', 'date_end', 'product_type', 'rang', 'special_code', 'fk_parent_line', 'fk_fournprice', 'pa_ht', 'label', 'array_options', 'fk_unit', 'id');

				foreach ($TPropertiesToClone as $property) {
					$lineOrder->{$property} = $line->{$property};
				}

				if ($line->fk_product) {
					$producttmp = new ProductFournisseur($db);
					$ret = $producttmp->fetch($line->fk_product);

					if ($ret > 0) {
						$lineOrder->pa_ht = $producttmp->cost_price; // cout de revient

						if ($conf->global->MARGIN_TYPE == '1') // best fournprice
						{
							$ret = $producttmp->find_min_price_product_fournisseur($line->fk_product, $line->qty);
							if ($ret > 0) $lineOrder->pa_ht = $producttmp->fourn_unitprice;
						} else if ($conf->global->MARGIN_TYPE == 'pmp' && !empty($conf->stock->enabled)) // pmp
						{
							$lineOrder->pa_ht = $producttmp->pmp;
						}
					}

				}

				// Avoir une liason entre les lignes de la commande fournisseur de l'entité A et les lignes de la commande créée coté entité B,
				// ça arrange bien, car il n'est pas possible de connaitre ID de la ligne en cours de création. (je sais de rien... bisous)
				$lineOrder->array_options['options_supplier_order_det_source'] = $line->id;

				$o->lines[] = $lineOrder;
			}

			if (!empty($conf->global->OFSOM_DONT_FORCE_BUY_PRICE_WITH_SELL_PRICE)) {
				$oldval = $conf->global->ForceBuyingPriceIfNull;
				$conf->global->ForceBuyingPriceIfNull = 0;
			}

			$orderCreatedRes = $o->create($user);

			if ($orderCreatedRes < 0) {
				$this->error = $o->error;
				return -4;
			} else {
				if ((float)DOL_VERSION>=14.0) {
					//Cannot use $o->copy_linked_contact because it copy fk_c_type_contact from object order_supplier but we need order
					//So We recode the method here
					$contacts = $cf->liste_contact(-1, 'external');
					if (!empty($contacts)) {
						$o->delete_linked_contact('external');
						foreach ($contacts as $contact)
						{
							$sqltypeContact='SELECT rowid FROM '.MAIN_DB_PREFIX.'c_type_contact WHERE element=\''.$o->element.'\'';
							$sqltypeContact.=' AND source=\''.$o->db->escape($contact['source']).'\'';
							$sqltypeContact.=' AND code=\''.$o->db->escape($contact['code']).'\'';
							$resqlCopyContact=$o->db->query($sqltypeContact);
							if (!$resqlCopyContact) {
								setEventMessage($o->db->lasterror,'errors');
							} else {
								$obj=$o->db->fetch_object($resqlCopyContact);
								if ($o->add_contact($contact['id'], $obj->rowid, $contact['source']) < 0)
								{
									setEventMessage($o->db->lasterror,'errors');
								}
							}
						}

					}
				}

				if (!empty($conf->nomenclature->enabled)) {
					$orderID = $o->id;
					$o = new Commande($db);
					$res = $o->fetch($orderID); // Rechargement pour récupérer les bons IDs des lignes

					dol_include_once('/nomenclature/class/nomenclature.class.php');
					$PDOdb = new TPDOdb;

					foreach ($cf->lines as $k => &$line) {
						$n = new TNomenclature;
						$n->loadByObjectId($PDOdb, $line->id, $cf->element);
						if ($n->iExist) {
							$n->reinit();
							$n->fk_object = $o->lines[$k]->id;
							$n->object_type = $o->element;
							$n->save($PDOdb);
						}

					}
				}

				// Le changement d'entité doit se faire après le changement d'entité, sinon, le fetch échoue
				$res = $db->query("UPDATE " . MAIN_DB_PREFIX . "commande
						 SET entity=" . $toEntity . "
						 WHERE rowid=" . $o->id); // on transporte la commande dans l'autre entité

				if(!$res){
					return -5;
				}

				//Lien entre la commande fournisseur et la commande client dans la table element_element
				$sql = "INSERT INTO " . MAIN_DB_PREFIX . "element_element (";
				$sql .= "fk_source";
				$sql .= ", sourcetype";
				$sql .= ", fk_target";
				$sql .= ", targettype";
				$sql .= ") VALUES (";
				$sql .= $idOrderSource;
				$sql .= ", 'commandefourn'";
				$sql .= ", " . $o->id;
				$sql .= ", 'commande'";
				$sql .= ")";
				$res = $db->query($sql);
				if(!$res){
					return -6;
				}
			}

			if (!empty($conf->global->OFSOM_DONT_FORCE_BUY_PRICE_WITH_SELL_PRICE)) $conf->global->ForceBuyingPriceIfNull = $oldval;

			return $orderCreatedRes;
		}
		else
		{
			return -1;
		}
	}

	/**
	 * Permet de recupérer l'Id de la commande client coté entité fournisseur à partir de la commande fournisseur coté entité cliente
	 * @param CommandeFournisseur $supplierOrder (coté entité cliente)
	 * @param int $targetEntity entité cible (entité fournisseur)
	 * @param int $targetSocid societé cible (coté entité fournisseur)
	 * @return int 	<=0 if KO, >0 if OK
	 */
	public function getOrderIdFromSupplierOrder($supplierOrder, $targetEntity, $targetSocid = false){

		if(!$targetSocid){
			$targetSocid = $this->getSocIdFromEntity($targetEntity, $supplierOrder->entity);
		}

		if($targetSocid<=0){
			return -1;
		}


		$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "commande "
			 . " WHERE fk_soc = ".intval($targetSocid)." AND entity=" . intval($targetEntity) . " AND ref_client='" . $supplierOrder->db->escape($supplierOrder->ref) . "' ";

		$res = $supplierOrder->db->query($sql);

		if(!$res){
			$this->error = $supplierOrder->db->error();
			return -1;
		}

		$obj = $supplierOrder->db->fetch_object($res);

		if ($obj && $obj->rowid > 0) {
			return $obj->rowid;
		}

		return 0;
	}


	/**
	 * Permet de récupérer l'ID de la commande client créée sur l'entité fournisseur à partir de la commande fournisseur de l'entité cliente
	 *
	 * @param Commande $order (coté entité fournisseur)
	 * @param int $targetEntity (entité cliente)
	 * @param int $targetSocid (coté entité cliente)
	 * @return int supplier order 	<=0 if KO, >0 if OK
	 */
	public function getSupplierOrderIdFromOrder($order, $targetEntity = false, $targetSocid = false){

		if(!$targetEntity){
			// récupère l'entité cliente à l'origine de la commande à partir du socid de la commande de l'entité fournisseur
			$targetEntity = $this->getSocEntityFromSocId($order->socid, $order->entity);
		}


		if(!$targetSocid){
			// récupère id correspondant à la société fournisseur sur l'entité cliente à l'origine de la commande à partir des id d'entité client et fournisseur
			$targetSocid = $this->getSocIdFromEntity($order->entity, $targetEntity);
		}

		if($targetSocid<=0){
			return -1;
		}

		$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "commande_fournisseur "
			. " WHERE fk_soc = ".intval($targetSocid)." AND entity=" . intval($targetEntity) . " AND ref='" . $order->db->escape($order->ref_client) . "' ";

		$res = $order->db->query($sql);

		if(!$res){
			$this->error = $order->db->error();
			return -2;
		}

		$obj = $order->db->fetch_object($res);
		if ($obj && $obj->rowid > 0) {
			return $obj->rowid;
		}

		return 0;
	}

}
