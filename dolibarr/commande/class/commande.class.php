<?php
/* Copyright (C) 2003-2006 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2010 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2006      Andre Cianfarani     <acianfa@free.fr>
 * Copyright (C) 2010      Juanjo Menent        <jmenent@2byte.es>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 */

/**
 *  \file       htdocs/commande/class/commande.class.php
 *  \ingroup    commande
 *  \brief      Fichier des classes de commandes
 *  \version    $Id: commande.class.php,v 1.62.2.5 2011/06/17 18:21:02 eldy Exp $
 */
require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");
require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");


/**
 *  \class      Commande
 *  \brief      Class to manage customers orders
 */
class Commande extends CommonObject
{
	var $db;
	var $error;
	var $element='commande';
	var $table_element='commande';
	var $table_element_line = 'commandedet';
	var $class_element_line = 'OrderLine';
	var $fk_element = 'fk_commande';
	var $ismultientitymanaged = 1;	// 0=No test on entity, 1=Test with field entity, 2=Test with link by societe

	var $id ;

	var $socid;		// Id client
	var $client;		// Objet societe client (a charger par fetch_client)

	var $ref;
	var $ref_client;
	var $contactid;
	var $fk_project;
	var $statut;		// -1=Canceled, 0=Draft, 1=Validated, 2=Accepted/On process, 3=Closed (Sent/Recevied, billed or not)

	var $facturee;		// Facturee ou non
	var $brouillon;
	var $cond_reglement_id;
	var $cond_reglement_code;
	var $mode_reglement_id;
	var $mode_reglement_code;
	var $fk_delivery_address;
	var $adresse;
	var $date;				// Date commande
	var $date_commande;		// Date commande (deprecated)
	var $date_livraison;	// Date livraison souhaitee
	var $fk_remise_except;
	var $remise_percent;
	var $total_ht;			// Total net of tax
	var $total_ttc;			// Total with tax
	var $total_tva;			// Total VAT
	var $total_localtax1;   // Total Local tax 1
	var $total_localtax2;   // Total Local tax 2
	var $remise_absolue;
	var $modelpdf;
	var $info_bits;
	var $rang;
	var $special_code;
	var $source;			// Origin of order

	var $origin;
	var $origin_id;

	var $user_author_id;

	var $lines = array();

	// Pour board
	var $nbtodo;
	var $nbtodolate;


	/**
	 *        \brief      Constructeur
	 *        \param      DB      Handler d'acces base
	 */
	function Commande($DB, $socid="", $commandeid=0)
	{
		global $langs;
		$langs->load('orders');
		$this->db = $DB;
		$this->socid = $socid;
		$this->id = $commandeid;

		$this->remise = 0;
		$this->remise_percent = 0;

		$this->products = array();
	}

	/**
	 *      \brief      Renvoie la reference de commande suivante non utilisee en fonction du module
	 *                  de numerotation actif defini dans COMMANDE_ADDON
	 *      \param	    soc  		            objet societe
	 *      \return     string                  reference libre pour la commande
	 */
	function getNextNumRef($soc)
	{
		global $db, $langs, $conf;
		$langs->load("order");

		$dir = DOL_DOCUMENT_ROOT . "/includes/modules/commande";

		if (! empty($conf->global->COMMANDE_ADDON))
		{
			$file = $conf->global->COMMANDE_ADDON.".php";

			// Chargement de la classe de numerotation
			$classname = $conf->global->COMMANDE_ADDON;

			$result=include_once($dir.'/'.$file);
			if ($result)
			{
				$obj = new $classname();
				$numref = "";
				$numref = $obj->getNextValue($soc,$this);

				if ( $numref != "")
				{
					return $numref;
				}
				else
				{
					dol_print_error($db,"Commande::getNextNumRef ".$obj->error);
					return "";
				}
			}
			else
			{
				print $langs->trans("Error")." ".$langs->trans("Error_COMMANDE_ADDON_NotDefined");
				return "";
			}
		}
		else
		{
			print $langs->trans("Error")." ".$langs->trans("Error_COMMANDE_ADDON_NotDefined");
			return "";
		}
	}


	/**
	 *  \brief   Validate order
	 *  \param   user     	User making status change
	 *  \return  int		<=0 if OK, >0 if KO
	 */
	function valid($user)
	{
		global $conf,$langs;

		$error=0;

		// Protection
		if ($this->statut == 1)
		{
			dol_syslog("Commande::valid no draft status", LOG_WARNING);
			return 0;
		}

		if (! $user->rights->commande->valider)
		{
			$this->error='Permission denied';
			dol_syslog("Commande::valid ".$this->error, LOG_ERR);
			return -1;
		}

		$now=dol_now();

		$this->db->begin();

		// Definition du nom de module de numerotation de commande
		$soc = new Societe($this->db);
		$soc->fetch($this->socid);

		// Class of company linked to order
		$result=$soc->set_as_client();

		// Define new ref
		if (! $error && (preg_match('/^[\(]?PROV/i', $this->ref)))
		{
			$num = $this->getNextNumRef($soc);
		}
		else
		{
			$num = $this->ref;
		}

		// Validate
		$sql = "UPDATE ".MAIN_DB_PREFIX."commande";
		$sql.= " SET ref = '".$num."'";
		$sql.= ", fk_statut = 1";
		$sql.= ", date_valid=".$this->db->idate($now);
		$sql.= ", fk_user_valid = ".$user->id;
		$sql.= " WHERE rowid = ".$this->id;

		dol_syslog("Commande::valid() sql=".$sql);
		$resql=$this->db->query($sql);
		if (! $resql)
		{
			dol_syslog("Commande::valid() Echec update - 10 - sql=".$sql, LOG_ERR);
			dol_print_error($this->db);
			$error++;
		}

		if (! $error)
		{
			// If stock is incremented on validate order, we must increment it
			if ($result >= 0 && $conf->stock->enabled && $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER == 1)
			{
				require_once(DOL_DOCUMENT_ROOT."/product/stock/class/mouvementstock.class.php");

				// Loop on each line
				for ($i = 0 ; $i < sizeof($this->lines) ; $i++)
				{
					if ($this->lines[$i]->fk_product > 0 && $this->lines[$i]->product_type == 0)
					{
						$mouvP = new MouvementStock($this->db);
						// We decrement stock of product (and sub-products)
						$entrepot_id = "1"; // TODO ajouter possibilite de choisir l'entrepot
						$result=$mouvP->livraison($user, $this->lines[$i]->fk_product, $entrepot_id, $this->lines[$i]->qty, $this->lines[$i]->subprice);
						if ($result < 0) { $error++; }
					}
				}
			}
		}

		if (! $error)
		{
			// Rename directory if dir was a temporary ref
			if (preg_match('/^[\(]?PROV/i', $this->ref))
			{
				// On renomme repertoire ($this->ref = ancienne ref, $numfa = nouvelle ref)
				// afin de ne pas perdre les fichiers attaches
				$comref = dol_sanitizeFileName($this->ref);
				$snum = dol_sanitizeFileName($num);
				$dirsource = $conf->commande->dir_output.'/'.$comref;
				$dirdest = $conf->commande->dir_output.'/'.$snum;
				if (file_exists($dirsource))
				{
					dol_syslog("Commande::valid() rename dir ".$dirsource." into ".$dirdest);

					if (@rename($dirsource, $dirdest))
					{
						dol_syslog("Rename ok");
						// Suppression ancien fichier PDF dans nouveau rep
						dol_delete_file($conf->commande->dir_output.'/'.$snum.'/'.$comref.'.*');
					}
				}
			}
		}

		// Set new ref
		if (! $error)
		{
			$this->ref = $num;
		}

		if (! $error)
		{
			// Appel des triggers
			include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
			$interface=new Interfaces($this->db);
			$result=$interface->run_triggers('ORDER_VALIDATE',$this,$user,$langs,$conf);
			if ($result < 0) { $error++; $this->errors=$interface->errors; }
			// Fin appel triggers
		}

		if (! $error)
		{
			$this->db->commit();
			return 1;
		}
		else
		{
			$this->db->rollback();
			$this->error=$this->db->lasterror();
			return -1;
		}
	}

	/**
	 *		\brief		Set draft status
	 *		\param		user		Object user that modify
	 *		\return		int			<0 if KO, >0 if OK
	 */
	function set_draft($user)
	{
		global $conf,$langs;

		$error=0;

		// Protection
		if ($this->statut <= 0)
		{
			return 0;
		}

		if (! $user->rights->commande->valider)
		{
			$this->error='Permission denied';
			return -1;
		}

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."commande";
		$sql.= " SET fk_statut = 0";
		$sql.= " WHERE rowid = ".$this->id;

		dol_syslog("Commande::set_draft sql=".$sql, LOG_DEBUG);
		if ($this->db->query($sql))
		{
			// If stock is decremented on validate order, we must reincrement it
			if ($conf->stock->enabled && $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER == 1)
			{
				require_once(DOL_DOCUMENT_ROOT."/product/stock/class/mouvementstock.class.php");

				for ($i = 0 ; $i < sizeof($this->lines) ; $i++)
				{
					if ($this->lines[$i]->fk_product > 0 && $this->lines[$i]->product_type == 0)
					{
						$mouvP = new MouvementStock($this->db);
						// We increment stock of product (and sub-products)
						$entrepot_id = "1"; //Todo: ajouter possibilite de choisir l'entrepot
						$result=$mouvP->reception($user, $this->lines[$i]->fk_product, $entrepot_id, $this->lines[$i]->qty, $this->lines[$i]->subprice);
						if ($result < 0) { $error++; }
					}
				}

				if (!$error)
				{
					$this->statut=0;
					$this->db->commit();
					return $result;
				}
				else
				{
					$this->error=$mouvP->error;
					$this->db->rollback();
					return $result;
				}
			}

			$this->statut=0;
			$this->db->commit();
			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			$this->db->rollback();
			dol_syslog($this->error, LOG_ERR);
			return -1;
		}
	}


	/**
	 *      \brief      Tag the order as opened
	 *				   	Function used when order is reopend after being closed.
	 *      \param      user        Object user that change status
	 *      \return     int         <0 if KO, 0 if nothing is done, >0 if OK
	 */
	function set_reopen($user)
	{
		global $conf,$langs;
		$error=0;

		if ($this->statut == 3)
		{
			$this->db->begin();

			$sql = 'UPDATE '.MAIN_DB_PREFIX.'commande';
			$sql.= ' SET fk_statut=2, facture=0';
			$sql.= ' WHERE rowid = '.$this->id;

			dol_syslog("Commande::set_reopen sql=".$sql);
			$resql = $this->db->query($sql);
			if ($resql)
			{
				$this->use_webcal=($conf->global->PHPWEBCALENDAR_BILLSTATUS=='always'?1:0);

				// Appel des triggers
				include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
				$interface=new Interfaces($this->db);
				$result=$interface->run_triggers('BILL_REOPEN',$this,$user,$langs,$conf);
				if ($result < 0) { $error++; $this->errors=$interface->errors; }
				// Fin appel triggers
			}
			else
			{
				$error++;
				$this->error=$this->db->error();
				dol_print_error($this->db);
			}

			if (! $error)
			{
				$this->db->commit();
				return 1;
			}
			else
			{
				$this->db->rollback();
				return -1;
			}
		}
		else
		{
			return 0;
		}
	}

	/**
	 *    	\brief      Cloture la commande
	 * 		\param      user        Objet utilisateur qui cloture
	 *		\return		int			<0 if KO, >0 if OK
	 */
	function cloture($user)
	{
		global $conf;

		if ($user->rights->commande->valider)
		{
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'commande';
			$sql.= ' SET fk_statut = 3,';
			$sql.= ' fk_user_cloture = '.$user->id.',';
			$sql.= ' date_cloture = '.$this->db->idate(mktime());
			$sql.= ' WHERE rowid = '.$this->id.' AND fk_statut > 0';

			if ($this->db->query($sql))
			{
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog($this->error, LOG_ERR);
				return -1;
			}
		}
	}

	/**
	 * 	\brief		Cancel an order
	 *	\return		int			<0 if KO, >0 if OK
	 * 	\remarks	If stock is decremented on order validation, we must reincrement it
	 */
	function cancel($user)
	{
		global $conf;

		$error=0;

		if ($user->rights->commande->valider)
		{
			$this->db->begin();

			$sql = "UPDATE ".MAIN_DB_PREFIX."commande";
			$sql.= " SET fk_statut = -1";
			$sql.= " WHERE rowid = ".$this->id;
			$sql.= " AND fk_statut = 1";

			dol_syslog("Commande::cancel sql=".$sql, LOG_DEBUG);
			if ($this->db->query($sql))
			{
				// If stock is decremented on validate order, we must reincrement it
				if ($conf->stock->enabled && $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER == 1)
				{
					require_once(DOL_DOCUMENT_ROOT."/product/stock/class/mouvementstock.class.php");

					if ($this->lines[$i]->fk_product > 0 && $this->lines[$i]->product_type == 0)
					{
						$mouvP = new MouvementStock($this->db);
						// We increment stock of product (and sub-products)
						$entrepot_id = "1"; //Todo: ajouter possibilite de choisir l'entrepot
						$result=$mouvP->reception($user, $this->lines[$i]->fk_product, $entrepot_id, $this->lines[$i]->qty, $this->lines[$i]->subprice);
						if ($result < 0) { $error++; }
					}
				}

				if (! $error)
				{
					$this->statut=-1;
					$this->db->commit();
					return 1;
				}
				else
				{
					$this->error=$mouvP->error;
					$this->db->rollback();
					return -1;
				}
			}
			else
			{
				$this->error=$this->db->error();
				$this->db->rollback();
				dol_syslog($this->error, LOG_ERR);
				return -1;
			}
		}
	}

	/**
	 *  	\brief		Create order
	 *		\param		user 	Objet user that make creation
	 * 		\return 	int		<0 if KO, >0 if OK
	 *		\remarks	this->ref can be set or empty. If empty, we will use "(PROV)"
	 */
	function create($user)
	{
		global $conf,$langs,$mysoc;
		$error=0;

		// Clean parameters
		$this->brouillon = 1;		// On positionne en mode brouillon la commande

		dol_syslog("Commande::create user=".$user->id);

		// Check parameters
		$soc = new Societe($this->db);
		$result=$soc->fetch($this->socid);
		if ($result < 0)
		{
			$this->error="Failed to fetch company";
			dol_syslog("Commande::create ".$this->error, LOG_ERR);
			return -2;
		}
		if (! empty($conf->global->COMMANDE_REQUIRE_SOURCE) && $this->source < 0)
		{
			$this->error=$langs->trans("ErrorFieldRequired",$langs->trans("Source"));
			dol_syslog("Commande::create ".$this->error, LOG_ERR);
			return -1;
		}
		if (! $remise) $remise=0;
		if (! $this->fk_project) $this->fk_project = 0;


		$this->db->begin();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."commande (";
		$sql.= " ref, fk_soc, date_creation, fk_user_author, fk_projet, date_commande, source, note, note_public, ref_client";
		$sql.= ", model_pdf, fk_cond_reglement, fk_mode_reglement, date_livraison, fk_adresse_livraison";
		$sql.= ", remise_absolue, remise_percent";
		$sql.= ", entity";
		$sql.= ")";
		$sql.= " VALUES ('(PROV)',".$this->socid.", ".$this->db->idate(gmmktime()).", ".$user->id.", ".$this->fk_project;
		$sql.= ", ".$this->db->idate($this->date_commande);
		$sql.= ", ".($this->source>=0 && $this->source != '' ?$this->source:'null');
		$sql.= ", '".addslashes($this->note)."'";
		$sql.= ", '".addslashes($this->note_public)."'";
		$sql.= ", '".addslashes($this->ref_client)."', '".$this->modelpdf."'";
		$sql.= ", ".($this->cond_reglement_id>0?"'".$this->cond_reglement_id."'":"null");
		$sql.= ", ".($this->mode_reglement_id>0?"'".$this->mode_reglement_id."'":"null");
		$sql.= ", ".($this->date_livraison?"'".$this->db->idate($this->date_livraison)."'":"null");
		$sql.= ", ".($this->fk_delivery_address>0?$this->fk_delivery_address:'NULL');
		$sql.= ", ".($this->remise_absolue>0?$this->remise_absolue:'NULL');
		$sql.= ", '".$this->remise_percent."'";
		$sql.= ", ".$conf->entity;
		$sql.= ")";

		dol_syslog("Commande::create sql=".$sql);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'commande');

			if ($this->id)
			{
				/*
				 *  Insertion du detail des produits dans la base
				 */
				for ($i = 0 ; $i < sizeof($this->lines) ; $i++)
				{
					$result = $this->addline(
					$this->id,
					$this->lines[$i]->desc,
					$this->lines[$i]->subprice,
					$this->lines[$i]->qty,
					$this->lines[$i]->tva_tx,
					$this->lines[$i]->localtax1_tx,
					$this->lines[$i]->localtax2_tx,
					$this->lines[$i]->fk_product,
					$this->lines[$i]->remise_percent,
					$this->lines[$i]->info_bits,
					$this->lines[$i]->fk_remise_except,
					'HT',
					0,
					$this->lines[$i]->date_start,
					$this->lines[$i]->date_end,
					$this->lines[$i]->product_type,
					$this->lines[$i]->rang,
					$this->lines[$i]->special_code
					);
					if ($result < 0)
					{
						$this->error=$this->db->lasterror();
						dol_print_error($this->db);
						$this->db->rollback();
						return -1;
					}
				}

				// Mise a jour ref
				$sql = 'UPDATE '.MAIN_DB_PREFIX."commande SET ref='(PROV".$this->id.")' WHERE rowid=".$this->id;
				if ($this->db->query($sql))
				{
					if ($this->id)
					{
					    $this->ref="(PROV".$this->id.")";

						// Add linked object
						if ($this->origin && $this->origin_id)
						{
							$ret = $this->add_object_linked();
							if (! $ret)	dol_print_error($this->db);
						}

						// TODO mutualiser
						if ($this->origin == 'propal' && $this->origin_id)
						{
							// On recupere les differents contact interne et externe
							$prop = new Propal($this->db, $this->socid, $this->origin_id);

							// On recupere le commercial suivi propale
							$this->userid = $prop->getIdcontact('internal', 'SALESREPFOLL');

							if ($this->userid)
							{
								//On passe le commercial suivi propale en commercial suivi commande
								$this->add_contact($this->userid[0], 'SALESREPFOLL', 'internal');
							}

							// On recupere le contact client suivi propale
							$this->contactid = $prop->getIdcontact('external', 'CUSTOMER');

							if ($this->contactid)
							{
								//On passe le contact client suivi propale en contact client suivi commande
								$this->add_contact($this->contactid[0], 'CUSTOMER', 'external');
							}
						}
					}

					// Appel des triggers
					include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
					$interface=new Interfaces($this->db);
					$result=$interface->run_triggers('ORDER_CREATE',$this,$user,$langs,$conf);
					if ($result < 0) { $error++; $this->errors=$interface->errors; }
					// Fin appel triggers

					$this->db->commit();
					return $this->id;
				}
				else
				{
					$this->db->rollback();
					return -1;
				}
			}
		}
		else
		{
			dol_print_error($this->db);
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *		\brief      Load an object from its id and create a new one in database
	 *		\param      fromid     		Id of object to clone
	 *		\param		invertdetail	Reverse sign of amounts for lines
	 * 	 	\return		int				New id of clone
	 */
	function createFromClone($fromid,$invertdetail=0)
	{
		global $conf,$user,$langs;

		$error=0;

		$object=new Commande($this->db);

		// Instantiate hooks of thirdparty module
		if (is_array($conf->hooks_modules) && !empty($conf->hooks_modules))
		{
			$object->callHooks('objectcard');
		}

		$this->db->begin();

		// Load source object
		$object->fetch($fromid);
		$objFrom = $object;

		$object->id=0;
		$object->statut=0;

		// Clear fields
		$object->user_author_id     = $user->id;
		$object->user_valid         = '';
		$object->date_creation      = '';
		$object->date_validation    = '';
		$object->ref_client         = '';

		// Create clone
		$result=$object->create($user);

		// Other options
		if ($result < 0)
		{
			$this->error=$object->error;
			$error++;
		}

		if (! $error)
		{
			// Hook of thirdparty module
			if (! empty($object->hooks))
			{
				foreach($object->hooks as $module)
				{
					$result = $module->createfrom($objFrom,$result,$object->element);
					if ($result < 0) $error++;
				}
			}

			// Appel des triggers
			include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
			$interface=new Interfaces($this->db);
			$result=$interface->run_triggers('ORDER_CLONE',$object,$user,$langs,$conf);
			if ($result < 0) { $error++; $this->errors=$interface->errors; }
			// Fin appel triggers
		}

		// End
		if (! $error)
		{
			$this->db->commit();
			return $object->id;
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *     Add an order line into database (linked to product/service or not)
	 *     @param      commandeid      	Id of line
	 * 	   @param      desc            	Description of line
	 * 	   @param      pu_ht            Unit price (without tax)
	 * 	   @param      qty             	Quantite
	 * 	   @param      txtva           	Taux de tva force, sinon -1
	 * 	   @param      txlocaltax1		Local tax 1 rate
	 *     @param      txlocaltax2		Local tax 2 rate
	 *	   @param      fk_product      	Id du produit/service predefini
	 * 	   @param      remise_percent  	Pourcentage de remise de la ligne
	 * 	   @param      info_bits		Bits de type de lignes
	 *	   @param      fk_remise_except	Id remise
	 *	   @param      price_base_type	HT or TTC
	 * 	   @param      pu_ttc           Prix unitaire TTC
	 * 	   @param      date_start       Start date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
	 * 	   @param      date_end         End date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
	 * 	   @param      type				Type of line (0=product, 1=service)
	 *     @param      rang             Position of line
	 *     @return     int             	>0 if OK, <0 if KO
	 *     @see        add_product
	 * 	   Les parametres sont deja cense etre juste et avec valeurs finales a l'appel
	 *	   de cette methode. Aussi, pour le taux tva, il doit deja avoir ete defini
	 *	   par l'appelant par la methode get_default_tva(societe_vendeuse,societe_acheteuse,produit)
	 *	   et le desc doit deja avoir la bonne valeur (a l'appelant de gerer le multilangue)
	 */
	function addline($commandeid, $desc, $pu_ht, $qty, $txtva, $txlocaltax1=0, $txlocaltax2=0, $fk_product=0, $remise_percent=0, $info_bits=0, $fk_remise_except=0, $price_base_type='HT', $pu_ttc=0, $date_start='', $date_end='', $type=0, $rang=-1, $special_code=0)
	{
		dol_syslog("Commande::addline commandeid=$commandeid, desc=$desc, pu_ht=$pu_ht, qty=$qty, txtva=$txtva, fk_product=$fk_product, remise_percent=$remise_percent, info_bits=$info_bits, fk_remise_except=$fk_remise_except, price_base_type=$price_base_type, pu_ttc=$pu_ttc, date_start=$date_start, date_end=$date_end, type=$type", LOG_DEBUG);

		include_once(DOL_DOCUMENT_ROOT.'/lib/price.lib.php');

		// Clean parameters
		if (empty($remise_percent)) $remise_percent=0;
        if (empty($qty)) $qty=0;
        if (empty($info_bits)) $info_bits=0;
        if (empty($rang)) $rang=0;
        if (empty($txtva)) $txtva=0;
        if (empty($txlocaltax1)) $txlocaltax1=0;
        if (empty($txlocaltax2)) $txlocaltax2=0;

		$remise_percent=price2num($remise_percent);
		$qty=price2num($qty);
		$pu_ht=price2num($pu_ht);
		$pu_ttc=price2num($pu_ttc);
		$txtva = price2num($txtva);
		$txlocaltax1 = price2num($txlocaltax1);
		$txlocaltax2 = price2num($txlocaltax2);
		if ($price_base_type=='HT')
		{
			$pu=$pu_ht;
		}
		else
		{
			$pu=$pu_ttc;
		}
		$desc=trim($desc);

		// Check parameters
		if ($type < 0) return -1;

		if ($this->statut == 0)
		{
			$this->db->begin();

			// Calcul du total TTC et de la TVA pour la ligne a partir de
			// qty, pu, remise_percent et txtva
			// TRES IMPORTANT: C'est au moment de l'insertion ligne qu'on doit stocker
			// la part ht, tva et ttc, et ce au niveau de la ligne qui a son propre taux tva.
			$tabprice = calcul_price_total($qty, $pu, $remise_percent, $txtva, $txlocaltax1 ,$txlocaltax2, 0, $price_base_type, $info_bits);
			$total_ht  = $tabprice[0];
			$total_tva = $tabprice[1];
			$total_ttc = $tabprice[2];
			$total_localtax1 = $tabprice[9];
			$total_localtax2 = $tabprice[10];

			// Rang to use
			$rangtouse = $rang;
			if ($rangtouse == -1)
			{
				$rangmax = $this->line_max();
				$rangtouse = $rangmax + 1;
			}

			// TODO A virer
			// Anciens indicateurs: $price, $remise (a ne plus utiliser)
			$price = $pu;
			$remise = 0;
			if ($remise_percent > 0)
			{
				$remise = round(($pu * $remise_percent / 100), 2);
				$price = $pu - $remise;
			}

			// Insert line
			$this->line=new OrderLine($this->db);

			$this->line->fk_commande=$commandeid;
			$this->line->desc=$desc;
			$this->line->qty=$qty;
			$this->line->tva_tx=$txtva;
			$this->line->localtax1_tx=$txlocaltax1;
			$this->line->localtax2_tx=$txlocaltax2;
			$this->line->fk_product=$fk_product;
			$this->line->fk_remise_except=$fk_remise_except;
			$this->line->remise_percent=$remise_percent;
			$this->line->subprice=$pu_ht;
			$this->line->rang=$rangtouse;
			$this->line->info_bits=$info_bits;
			$this->line->total_ht=$total_ht;
			$this->line->total_tva=$total_tva;
			$this->line->total_localtax1=$total_localtax1;
			$this->line->total_localtax2=$total_localtax2;
			$this->line->total_ttc=$total_ttc;
			$this->line->product_type=$type;
			$this->line->special_code=$special_code;

			$this->line->date_start=$date_start;
			$this->line->date_end=$date_end;

			// TODO Ne plus utiliser
			$this->line->price=$price;
			$this->line->remise=$remise;

			$result=$this->line->insert();
			if ($result > 0)
			{
				// Mise a jour informations denormalisees au niveau de la commande meme
				$this->id=$commandeid;	// TODO A virer
				$result=$this->update_price($commandeid);
				if ($result > 0)
				{
					$this->db->commit();
					return 1;
				}
				else
				{
					$this->db->rollback();
					return -1;
				}
			}
			else
			{
				$this->error=$this->line->error;
				dol_syslog("Commande::addline error=".$this->error, LOG_ERR);
				$this->db->rollback();
				return -2;
			}
		}
	}


	/**
	 * 		Add line into array
	 *		$this->client doit etre charge
	 *		@param				idproduct			Id du produit a ajouter
	 *		@param				qty					Quantite
	 * 		@param    	date_start             	Start date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
	 * 		@param    	date_end             	End date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
	 *		@remise_percent		remise_percent		Remise relative effectuee sur le produit
	 * 		@return    			void
	 *		TODO	Remplacer les appels a cette fonction par generation objet Ligne
	 *				insere dans tableau $this->products
	 */
	function add_product($idproduct, $qty, $remise_percent=0, $date_start='', $date_end='')
	{
		global $conf, $mysoc;

		if (! $qty) $qty = 1;

		if ($idproduct > 0)
		{
			$prod=new Product($this->db);
			$prod->fetch($idproduct);

			$tva_tx = get_default_tva($mysoc,$this->client,$prod->id);
			$localtax1_tx=get_localtax($tva_tx,1,$this->client);
			$localtax2_tx=get_localtax($tva_tx,2,$this->client);
			// multiprix
			if($conf->global->PRODUIT_MULTIPRICES && $this->client->price_level)
			$price = $prod->multiprices[$this->client->price_level];
			else
			$price = $prod->price;

			$line=new OrderLine($this->db);

			$line->fk_product=$idproduct;
			$line->desc=$prod->description;
			$line->qty=$qty;
			$line->subprice=$price;
			$line->remise_percent=$remise_percent;
			$line->tva_tx=$tva_tx;
			$line->localtax1_tx=$localtax1_tx;
			$line->localtax2_tx=$localtax2_tx;
			$line->ref=$prod->ref;
			$line->libelle=$prod->libelle;
			$line->product_desc=$prod->description;

			// Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
			// Save the start and end date of the line in the object
			if ($date_start) { $line->date_start = $date_start; }
			if ($date_end)   { $line->date_end = $date_end; }

			$this->lines[] = $line;

			/** POUR AJOUTER AUTOMATIQUEMENT LES SOUSPRODUITS a LA COMMANDE
			 if (! empty($conf->global->PRODUIT_SOUSPRODUITS))
			 {
			 $prod = new Product($this->db, $idproduct);
			 $prod -> get_sousproduits_arbo ();
			 $prods_arbo = $prod->get_each_prod();
			 if(sizeof($prods_arbo) > 0)
			 {
			 foreach($prods_arbo as $key => $value)
			 {
			 // print "id : ".$value[1].' :qty: '.$value[0].'<br>';
			 if(! in_array($value[1],$this->products))
			 $this->add_product($value[1], $value[0]);

			 }
			 }

			 }
			 **/
		}
	}


	/**
	 *	\brief      Get object and lines from database
	 *	\param      id       	Id of object to load
	 * 	\param		ref			Ref of object
	 *	\return     int         >0 if OK, <0 if KO
	 */
	function fetch($id,$ref='')
	{
		global $conf;

		// Check parameters
		if (empty($id) && empty($ref)) return -1;

		$sql = 'SELECT c.rowid, c.date_creation, c.ref, c.fk_soc, c.fk_user_author, c.fk_statut';
		$sql.= ', c.amount_ht, c.total_ht, c.total_ttc, c.tva as total_tva, c.localtax1 as total_localtax1, c.localtax2 as total_localtax2, c.fk_cond_reglement, c.fk_mode_reglement';
		$sql.= ', c.date_commande';
		$sql.= ', c.date_livraison';
		$sql.= ', c.fk_projet, c.remise_percent, c.remise, c.remise_absolue, c.source, c.facture as facturee';
		$sql.= ', c.note, c.note_public, c.ref_client, c.model_pdf, c.fk_adresse_livraison';
		$sql.= ', p.code as mode_reglement_code, p.libelle as mode_reglement_libelle';
		$sql.= ', cr.code as cond_reglement_code, cr.libelle as cond_reglement_libelle, cr.libelle_facture as cond_reglement_libelle_doc';
		$sql.= ', el.fk_source';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'commande as c';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_payment_term as cr ON (c.fk_cond_reglement = cr.rowid)';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_paiement as p ON (c.fk_mode_reglement = p.id)';
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_element as el ON el.fk_target = c.rowid AND el.targettype = '".$this->element."'";
		$sql.= " WHERE c.entity = ".$conf->entity;
		if ($ref) $sql.= " AND c.ref='".$ref."'";
		else $sql.= " AND c.rowid=".$id;

		dol_syslog("Commande::fetch sql=".$sql, LOG_DEBUG);
		$result = $this->db->query($sql) ;
		if ($result)
		{
			$obj = $this->db->fetch_object($result);
			if ($obj)
			{
				$this->id                     = $obj->rowid;
				$this->ref                    = $obj->ref;
				$this->ref_client             = $obj->ref_client;
				$this->socid                  = $obj->fk_soc;
				$this->statut                 = $obj->fk_statut;
				$this->user_author_id         = $obj->fk_user_author;
				$this->total_ht               = $obj->total_ht;
				$this->total_tva              = $obj->total_tva;
				$this->total_localtax1		  = $obj->total_localtax1;
				$this->total_localtax2		  = $obj->total_localtax2;
				$this->total_ttc              = $obj->total_ttc;
				$this->date                   = $this->db->jdate($obj->date_commande);
				$this->date_commande          = $this->db->jdate($obj->date_commande);
				$this->remise                 = $obj->remise;
				$this->remise_percent         = $obj->remise_percent;
				$this->remise_absolue         = $obj->remise_absolue;
				$this->source                 = $obj->source;
				$this->facturee               = $obj->facturee;
				$this->note                   = $obj->note;
				$this->note_public            = $obj->note_public;
				$this->fk_project             = $obj->fk_projet;
				$this->modelpdf               = $obj->model_pdf;
				$this->mode_reglement_id      = $obj->fk_mode_reglement;
				$this->mode_reglement_code    = $obj->mode_reglement_code;
				$this->mode_reglement         = $obj->mode_reglement_libelle;
				$this->cond_reglement_id      = $obj->fk_cond_reglement;
				$this->cond_reglement_code    = $obj->cond_reglement_code;
				$this->cond_reglement         = $obj->cond_reglement_libelle;
				$this->cond_reglement_doc     = $obj->cond_reglement_libelle_doc;
				$this->date_livraison         = $this->db->jdate($obj->date_livraison);
				$this->fk_delivery_address    = $obj->fk_adresse_livraison;
				$this->propale_id             = $obj->fk_source;

				$this->lines                 = array();

				if ($this->statut == 0) $this->brouillon = 1;

				$this->db->free();

				if ($this->propale_id)
				{
					$sqlp = "SELECT ref";
					$sqlp.= " FROM ".MAIN_DB_PREFIX."propal";
					$sqlp.= " WHERE rowid = ".$this->propale_id;

					$resqlprop = $this->db->query($sqlp);

					if ($resqlprop)
					{
						$objp = $this->db->fetch_object($resqlprop);
						$this->propale_ref = $objp->ref;
						$this->db->free($resqlprop);
					}
				}

				/*
				 * Lignes
				 */
				$result=$this->fetch_lines();
				if ($result < 0)
				{
					return -3;
				}
				return 1;
			}
			else
			{
				dol_syslog('Commande::Fetch Error rowid='.$rowid.' numrows=0 sql='.$sql);
				$this->error='Order with id '.$rowid.' not found sql='.$sql;
				return -2;
			}
		}
		else
		{
			dol_syslog('Commande::Fetch Error rowid='.$rowid.' Erreur dans fetch de la commande');
			$this->error=$this->db->error();
			return -1;
		}
	}


	/**
	 *    \brief     Ajout d'une ligne remise fixe dans la commande, en base
	 *    \param     idremise			Id de la remise fixe
	 *    \return    int          		>0 si ok, <0 si ko
	 */
	function insert_discount($idremise)
	{
		global $langs;

		include_once(DOL_DOCUMENT_ROOT.'/lib/price.lib.php');
		include_once(DOL_DOCUMENT_ROOT.'/core/class/discount.class.php');

		$this->db->begin();

		$remise=new DiscountAbsolute($this->db);
		$result=$remise->fetch($idremise);

		if ($result > 0)
		{
			if ($remise->fk_facture)	// Protection against multiple submission
			{
				$this->error=$langs->trans("ErrorDiscountAlreadyUsed");
				$this->db->rollback();
				return -5;
			}

			$line = new OrderLine($this->db);

			$line->fk_commande=$this->id;
			$line->fk_remise_except=$remise->id;
			$line->desc=$remise->description;   	// Description ligne
			$line->tva_tx=$remise->tva_tx;
			$line->subprice=-$remise->amount_ht;
			$line->price=-$remise->amount_ht;
			$line->fk_product=0;					// Id produit predefini
			$line->qty=1;
			$line->remise=0;
			$line->remise_percent=0;
			$line->rang=-1;
			$line->info_bits=2;

			$line->total_ht  = -$remise->amount_ht;
			$line->total_tva = -$remise->amount_tva;
			$line->total_ttc = -$remise->amount_ttc;

			$result=$line->insert();
			if ($result > 0)
			{
				$result=$this->update_price();
				if ($result > 0)
				{
					$this->db->commit();
					return 1;
				}
				else
				{
					$this->db->rollback();
					return -1;
				}
			}
			else
			{
				$this->error=$line->error;
				$this->db->rollback();
				return -2;
			}
		}
		else
		{
			$this->db->rollback();
			return -2;
		}
	}


	/**
	 *      \brief      Reinitialize array lignes
	 *		\param		only_product	Return only physical products
	 *		\return		int				<0 if KO, >0 if OK
	 */
	function fetch_lines($only_product=0)
	{
		$this->lines=array();

		$sql = 'SELECT l.rowid, l.fk_product, l.product_type, l.fk_commande, l.description, l.price, l.qty, l.tva_tx,';
		$sql.= ' l.localtax1_tx, l.localtax2_tx, l.fk_remise_except, l.remise_percent, l.subprice, l.marge_tx, l.marque_tx, l.rang, l.info_bits, l.special_code,';
		$sql.= ' l.total_ht, l.total_ttc, l.total_tva, l.total_localtax1, l.total_localtax2, l.date_start, l.date_end,';
		$sql.= ' p.ref as product_ref, p.description as product_desc, p.fk_product_type, p.label';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'commandedet as l';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON (p.rowid = l.fk_product)';
		$sql.= ' WHERE l.fk_commande = '.$this->id;
		if ($only_product) $sql .= ' AND p.fk_product_type = 0';
		$sql .= ' ORDER BY l.rang';

		dol_syslog("Commande::fetch_lines sql=".$sql,LOG_DEBUG);
		$result = $this->db->query($sql);
		if ($result)
		{
			$num = $this->db->num_rows($result);

			$i = 0;
			while ($i < $num)
			{
				$objp = $this->db->fetch_object($result);

				$line = new OrderLine($this->db);

				$line->rowid            = $objp->rowid;				// \deprecated
				$line->id               = $objp->rowid;
				$line->fk_commande      = $objp->fk_commande;
				$line->commande_id      = $objp->fk_commande;			// \deprecated
				$line->desc             = $objp->description;  		// Description ligne
				$line->product_type     = $objp->product_type;
				$line->qty              = $objp->qty;
				$line->tva_tx           = $objp->tva_tx;
				$line->localtax1_tx     = $objp->localtax1_tx;
				$line->localtax2_tx     = $objp->localtax2_tx;
				$line->total_ht         = $objp->total_ht;
				$line->total_ttc        = $objp->total_ttc;
				$line->total_tva        = $objp->total_tva;
				$line->total_localtax1  = $objp->total_localtax1;
				$line->total_localtax2  = $objp->total_localtax2;
				$line->subprice         = $objp->subprice;
				$line->fk_remise_except = $objp->fk_remise_except;
				$line->remise_percent   = $objp->remise_percent;
				$line->price            = $objp->price;
				$line->fk_product       = $objp->fk_product;
				$line->marge_tx         = $objp->marge_tx;
				$line->marque_tx        = $objp->marque_tx;
				$line->rang             = $objp->rang;
				$line->info_bits        = $objp->info_bits;
				$line->special_code		= $objp->special_code;

				$line->ref              = $objp->product_ref;
				$line->libelle          = $objp->label;
				$line->product_label    = $objp->label;
				$line->product_desc     = $objp->product_desc; 		// Description produit
				$line->fk_product_type  = $objp->fk_product_type;	// Produit ou service

				$line->date_start       = $this->db->jdate($objp->date_start);
				$line->date_end         = $this->db->jdate($objp->date_end);

				$this->lines[$i] = $line;

				$i++;
			}
			$this->db->free($result);

			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog('Commande::fetch_lines: Error '.$this->error, LOG_ERR);
			return -3;
		}
	}


	/**
	 *      Return number of line with type product.
	 *		@return		int		<0 if KO, Nbr of product lines if OK
	 */
	function getNbOfProductsLines()
	{
		$nb=0;
		foreach($this->lines as $line)
		{
			if ($line->fk_product_type == 0) $nb++;
		}
		return $nb;
	}

	/**
	 *      \brief      Load array this->expeditions of nb of products sent by line in order
	 *      \param      filtre_statut       Filter on status
	 *      \return     int                	<0 if KO, Nb of lines found if OK
	 *      \TODO deprecated, move to Sending class
	 */
	function loadExpeditions($filtre_statut=-1)
	{
		$num=0;
		$this->expeditions = array();

		$sql = 'SELECT cd.rowid, cd.fk_product,';
		$sql.= ' sum(ed.qty) as qty';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'expeditiondet as ed,';
		if ($filtre_statut >= 0) $sql.= ' '.MAIN_DB_PREFIX.'expedition as e,';
		$sql.= ' '.MAIN_DB_PREFIX.'commandedet as cd';
		$sql.= ' WHERE';
		if ($filtre_statut >= 0) $sql.= ' ed.fk_expedition = e.rowid AND';
		$sql.= ' ed.fk_origin_line = cd.rowid';
		$sql.= ' AND cd.fk_commande =' .$this->id;
		if ($filtre_statut >= 0) $sql.=' AND e.fk_statut = '.$filtre_statut;
		$sql.= ' GROUP BY cd.rowid, cd.fk_product';
		//print $sql;

		dol_syslog("Commande::loadExpeditions sql=".$sql,LOG_DEBUG);
		$result = $this->db->query($sql);
		if ($result)
		{
			$num = $this->db->num_rows($result);
			$i = 0;
			while ($i < $num)
			{
				$obj = $this->db->fetch_object($result);
				$this->expeditions[$obj->rowid] = $obj->qty;
				$i++;
			}
			$this->db->free();
			return $num;
		}
		else
		{
			$this->error=$this->db->lasterror();
			dol_syslog("Commande::loadExpeditions ".$this->error,LOG_ERR);
			return -1;
		}

	}

	/**
	 * Renvoie un tableau avec nombre de lignes d'expeditions
	 * \TODO deprecated, move to Sending class
	 */
	function nb_expedition()
	{
        $sql = 'SELECT count(*)';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'expedition as e';
        $sql.= ', '.MAIN_DB_PREFIX.'element_element as el';
        $sql.= ' WHERE el.fk_source = '.$this->id;
        $sql.= " AND el.fk_target = e.rowid";
        $sql.= " AND el.targettype = 'shipping'";

        $resql = $this->db->query($sql);
        if ($resql)
        {
            $row = $this->db->fetch_row($resql);
            return $row[0];
        }
        else dol_print_error($this->db);
	}

	/**
	 *      \brief      Renvoie un tableau avec les livraisons par ligne
	 *      \param      filtre_statut       Filtre sur statut
	 *      \return     int                 0 si OK, <0 si KO
	 *      \TODO  deprecated, move to Delivery class
	 */
	function livraison_array($filtre_statut=-1)
	{
		$delivery = new Livraison($this->db);

		$deliveryArray = $delivery->livraison_array($filtre_statut);

		return $deliveryArray;
	}

	/**
	 *      \brief      Renvoie un tableau avec les stocks restant par produit
	 *      \param      filtre_statut       Filtre sur statut
	 *      \return     int                 0 si OK, <0 si KO
	 *		\todo		FONCTION NON FINIE A FINIR
	 */
	function stock_array($filtre_statut=-1)
	{
		$this->stocks = array();

		// Tableau des id de produit de la commande


		// Recherche total en stock pour chaque produit
		if (sizeof($array_of_product))
		{
			$sql = "SELECT fk_product, sum(ps.reel) as total";
			$sql.= " FROM ".MAIN_DB_PREFIX."product_stock as ps";
			$sql.= " WHERE ps.fk_product in (".join(',',$array_of_product).")";
			$sql.= ' GROUP BY fk_product ';
			$result = $this->db->query($sql);
			if ($result)
			{
				$num = $this->db->num_rows($result);
				$i = 0;
				while ($i < $num)
				{
					$obj = $this->db->fetch_object($result);
					$this->stocks[$obj->fk_product] = $obj->total;
					$i++;
				}
				$this->db->free();
			}
		}
		return 0;
	}

	/**
	 *  \brief      Supprime une ligne de la commande
	 *  \param      idligne     Id de la ligne a supprimer
	 *  \return     int         >0 si ok, 0 si rien a supprimer, <0 si ko
	 */
	function deleteline($lineid)
	{
		global $user;

		if ($this->statut == 0)
		{
			$this->db->begin();

			$sql = "SELECT fk_product, qty";
			$sql.= " FROM ".MAIN_DB_PREFIX."commandedet";
			$sql.= " WHERE rowid = ".$lineid;

			$result = $this->db->query($sql);
			if ($result)
			{
				$obj = $this->db->fetch_object($result);

				if ($obj)
				{
					$product = new Product($this->db);
					$product->id = $obj->fk_product;

					// Supprime ligne
					$line = new OrderLine($this->db);

					$line->id = $lineid;
					$line->fk_commande = $this->id; // On en a besoin dans les triggers

					$result=$line->delete($user);

					if ($result > 0)
					{
						$result=$this->update_price();

						if ($result > 0)
						{
							$this->db->commit();
							return 1;
						}
						else
						{
							$this->db->rollback();
							$this->error=$this->db->lasterror();
							return -1;
						}
					}
					else
					{
						$this->db->rollback();
						$this->error=$this->db->lasterror();
						return -1;
					}
				}
				else
				{
					$this->db->rollback();
					return 0;
				}
			}
			else
			{
				$this->db->rollback();
				$this->error=$this->db->lasterror();
				return -1;
			}
		}
		else
		{
			return -1;
		}
	}

	/**
	 * 	\brief     	Applique une remise relative
	 * 	\param     	user		User qui positionne la remise
	 * 	\param     	remise
	 *	\return		int 		<0 si ko, >0 si ok
	 */
	function set_remise($user, $remise)
	{
		$remise=trim($remise)?trim($remise):0;

		if ($user->rights->commande->creer)
		{
			$remise=price2num($remise);

			$sql = 'UPDATE '.MAIN_DB_PREFIX.'commande';
			$sql.= ' SET remise_percent = '.$remise;
			$sql.= ' WHERE rowid = '.$this->id.' AND fk_statut = 0 ;';

			if ($this->db->query($sql))
	  {
	  	$this->remise_percent = $remise;
	  	$this->update_price();
	  	return 1;
	  }
	  else
	  {
	  	$this->error=$this->db->error();
	  	return -1;
	  }
		}
	}


	/**
	 * 		\brief     	Applique une remise absolue
	 * 		\param     	user 		User qui positionne la remise
	 * 		\param     	remise
	 *		\return		int 		<0 si ko, >0 si ok
	 */
	function set_remise_absolue($user, $remise)
	{
		$remise=trim($remise)?trim($remise):0;

		if ($user->rights->commande->creer)
		{
			$remise=price2num($remise);

			$sql = 'UPDATE '.MAIN_DB_PREFIX.'commande';
			$sql.= ' SET remise_absolue = '.$remise;
			$sql.= ' WHERE rowid = '.$this->id.' AND fk_statut = 0 ;';

			dol_syslog("Commande::set_remise_absolue sql=$sql");

			if ($this->db->query($sql))
	  {
	  	$this->remise_absolue = $remise;
	  	$this->update_price();
	  	return 1;
	  }
	  else
	  {
	  	$this->error=$this->db->error();
	  	return -1;
	  }
		}
	}


	/**
	 *      Set the order date
	 *      @param      user        		Object user
	 *      @param      date_livraison      Date delivery
	 *      @return     int         		<0 if KO, >0 if OK
	 */
	function set_date($user, $date)
	{
		if ($user->rights->commande->creer)
		{
			$sql = "UPDATE ".MAIN_DB_PREFIX."commande";
			$sql.= " SET date_commande = ".($date ? $this->db->idate($date) : 'null');
			$sql.= " WHERE rowid = ".$this->id." AND fk_statut = 0";

			dol_syslog("Commande::set_date sql=$sql",LOG_DEBUG);
			$resql=$this->db->query($sql);
			if ($resql)
			{
				$this->date = $date;
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Commande::set_date ".$this->error,LOG_ERR);
				return -1;
			}
		}
		else
		{
			return -2;
		}
	}

	/**
	 *      \brief      Set the planned delivery date
	 *      \param      user        		Objet utilisateur qui modifie
	 *      \param      date_livraison      Date de livraison
	 *      \return     int         		<0 si ko, >0 si ok
	 */
	function set_date_livraison($user, $date_livraison)
	{
		if ($user->rights->commande->creer)
		{
			$sql = "UPDATE ".MAIN_DB_PREFIX."commande";
			$sql.= " SET date_livraison = ".($date_livraison ? "'".$this->db->idate($date_livraison)."'" : 'null');
			$sql.= " WHERE rowid = ".$this->id;

			dol_syslog("Commande::set_date_livraison sql=".$sql,LOG_DEBUG);
			$resql=$this->db->query($sql);
			if ($resql)
			{
				$this->date_livraison = $date_livraison;
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Commande::set_date_livraison ".$this->error,LOG_ERR);
				return -1;
			}
		}
		else
		{
			return -2;
		}
	}

	/**
	 *      \brief      Definit une adresse de livraison
	 *      \param      user        		Objet utilisateur qui modifie
	 *      \param      adresse_livraison      Adresse de livraison
	 *      \return     int         		<0 si ko, >0 si ok
	 */
	function set_adresse_livraison($user, $fk_address)
	{
		if ($user->rights->commande->creer)
		{
			$sql = "UPDATE ".MAIN_DB_PREFIX."commande SET fk_adresse_livraison = '".$fk_address."'";
			$sql.= " WHERE rowid = ".$this->id." AND fk_statut = 0";

			if ($this->db->query($sql) )
			{
				$this->fk_delivery_address = $fk_address;
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Commande::set_adresse_livraison Erreur SQL");
				return -1;
			}
		}
	}

	/**
	 *    \brief      Return list of orders (eventuelly filtered on a user) into an array
	 *    \param      brouillon       0=non brouillon, 1=brouillon
	 *    \param      user            Objet user de filtre
	 *    \return     int             -1 if KO, array with result if OK
	 */
	function liste_array($brouillon=0, $user='')
	{
		global $conf;

		$ga = array();

		$sql = "SELECT s.nom, s.rowid, c.rowid, c.ref";
		$sql.= " FROM ".MAIN_DB_PREFIX."societe as s, ".MAIN_DB_PREFIX."commande as c";
		$sql.= " WHERE c.entity = ".$conf->entity;
		$sql.= " AND c.fk_soc = s.rowid";
		if ($brouillon) $sql.= " AND c.fk_statut = 0";
        if ($user) $sql.= " AND c.fk_user_author <> ".$user->id;
		$sql .= " ORDER BY c.date_commande DESC";

		$result=$this->db->query($sql);
		if ($result)
		{
			$numc = $this->db->num_rows($result);
			if ($numc)
			{
				$i = 0;
				while ($i < $numc)
				{
					$obj = $this->db->fetch_object($result);

					$ga[$obj->rowid] = $obj->ref;
					$i++;
				}
			}
			return $ga;
		}
		else
		{
			dol_print_error($this->db);
			return -1;
		}
	}

	/**
	 *   \brief      Change les conditions de reglement de la commande
	 *   \param      cond_reglement_id      Id de la nouvelle condition de reglement
	 *   \return     int                    >0 si ok, <0 si ko
	 */
	function cond_reglement($cond_reglement_id)
	{
		dol_syslog('Commande::cond_reglement('.$cond_reglement_id.')');
		if ($this->statut >= 0)
		{
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'commande';
			$sql .= ' SET fk_cond_reglement = '.$cond_reglement_id;
			$sql .= ' WHERE rowid='.$this->id;
			if ( $this->db->query($sql) )
			{
				$this->cond_reglement_id = $cond_reglement_id;
				return 1;
			}
			else
			{
				dol_syslog('Commande::cond_reglement Erreur '.$sql.' - '.$this->db->error(), LOG_ERR);
				$this->error=$this->db->lasterror();
				return -1;
			}
		}
		else
		{
			dol_syslog('Commande::cond_reglement, etat commande incompatible', LOG_ERR);
			$this->error='Etat commande incompatible '.$this->statut;
			return -2;
		}
	}


	/**
	 *   \brief      Change le mode de reglement
	 *   \param      mode        Id du nouveau mode
	 *   \return     int         >0 si ok, <0 si ko
	 */
	function mode_reglement($mode_reglement_id)
	{
		dol_syslog('Commande::mode_reglement('.$mode_reglement_id.')');
		if ($this->statut >= 0)
		{
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'commande';
			$sql .= ' SET fk_mode_reglement = '.$mode_reglement_id;
			$sql .= ' WHERE rowid='.$this->id;
			if ( $this->db->query($sql) )
			{
				$this->mode_reglement_id = $mode_reglement_id;
				return 1;
			}
			else
			{
				dol_syslog('Commande::mode_reglement Erreur '.$sql.' - '.$this->db->error(), LOG_ERR);
				$this->error=$this->db->lasterror();
				return -1;
			}
		}
		else
		{
			dol_syslog('Commande::mode_reglement, etat facture incompatible', LOG_ERR);
			$this->error='Etat commande incompatible '.$this->statut;
			return -2;
		}
	}

	/**
	 *      \brief      Set customer ref
	 *      \param      user            User that make change
	 *      \param      ref_client      Customer ref
	 *      \return     int             <0 if KO, >0 if OK
	 */
	function set_ref_client($user, $ref_client)
	{
		if ($user->rights->commande->creer)
		{
			dol_syslog('Commande::set_ref_client this->id='.$this->id.', ref_client='.$ref_client);

			$sql = 'UPDATE '.MAIN_DB_PREFIX.'commande SET';
			$sql.= ' ref_client = '.(empty($ref_client) ? 'NULL' : '\''.addslashes($ref_client).'\'');
			$sql.= ' WHERE rowid = '.$this->id;

			if ($this->db->query($sql) )
			{
				$this->ref_client = $ref_client;
				return 1;
			}
			else
			{
				$this->error=$this->db->lasterror();
				dol_syslog('Commande::set_ref_client Erreur '.$this->error.' - '.$sql, LOG_ERR);
				return -2;
			}
		}
		else
		{
			return -1;
		}
	}


	/**
	 *        \brief      Classe la commande comme facturee
	 *        \return     int     <0 si ko, >0 si ok
	 */
	function classer_facturee()
	{
		global $conf;

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'commande SET facture = 1';
		$sql .= ' WHERE rowid = '.$this->id.' AND fk_statut > 0 ;';
		if ($this->db->query($sql) )
		{
			if (($conf->global->PROPALE_CLASSIFIED_INVOICED_WITH_ORDER == 1) && $this->propale_id)
			{
				$propal = new Propal($this->db);
				$propal->fetch($this->propale_id);
				$propal->classer_facturee();
			}
			return 1;
		}
		else
		{
			dol_print_error($this->db);
		}
	}


	/**
	 *  \brief    	Update a line in database
	 *  \param    	rowid            	Id of line to update
	 *  \param    	desc             	Description de la ligne
	 *  \param    	pu               	Prix unitaire
	 *  \param    	qty              	Quantity
	 *  \param    	remise_percent   	Pourcentage de remise de la ligne
	 *  \param    	tva_tx           	Taux TVA
	 * 	\param		txlocaltax1			Local tax 1 rate
	 *  \param		txlocaltax2			Local tax 2 rate
	 *  \param    	price_base_type		HT or TTC
	 *  \param    	info_bits        	Miscellanous informations on line
	 *  \param    	date_start        	Start date of the line
	 *  \param    	date_end          	End date of the line
	 * 	\param		type				Type of line (0=product, 1=service)
	 *  \return   	int              	< 0 si erreur, > 0 si ok
	 */
	function updateline($rowid, $desc, $pu, $qty, $remise_percent=0, $txtva, $txlocaltax1=0,$txlocaltax2=0, $price_base_type='HT', $info_bits=0, $date_start='', $date_end='', $type=0)
	{
        global $conf;

		dol_syslog("Commande::UpdateLine $rowid, $desc, $pu, $qty, $remise_percent, $txtva, $txlocaltax1, $txlocaltax2, $price_base_type, $info_bits, $date_start, $date_end, $type");
		include_once(DOL_DOCUMENT_ROOT.'/lib/price.lib.php');

		if ($this->brouillon)
		{
			$this->db->begin();

			// Clean parameters
			if (empty($qty)) $qty=0;
			if (empty($info_bits)) $info_bits=0;
			if (empty($txtva)) $txtva=0;
			if (empty($txlocaltax1)) $txlocaltax1=0;
			if (empty($txlocaltax2)) $txlocaltax2=0;
			if (empty($remise)) $remise=0;
			if (empty($remise_percent)) $remise_percent=0;
			$remise_percent=price2num($remise_percent);
			$qty=price2num($qty);
			$pu = price2num($pu);
			$txtva=price2num($txtva);
			$txlocaltax1=price2num($txtlocaltax1);
			$txlocaltax2=price2num($txtlocaltax2);

			// Calcul du total TTC et de la TVA pour la ligne a partir de
			// qty, pu, remise_percent et txtva
			// TRES IMPORTANT: C'est au moment de l'insertion ligne qu'on doit stocker
			// la part ht, tva et ttc, et ce au niveau de la ligne qui a son propre taux tva.
			$tabprice=calcul_price_total($qty, $pu, $remise_percent, $txtva, $txlocaltax1, $txlocaltax2, 0, $price_base_type, $info_bits);
			$total_ht  = $tabprice[0];
			$total_tva = $tabprice[1];
			$total_ttc = $tabprice[2];
			$total_localtax1 = $tabprice[9];
			$total_localtax2 = $tabprice[10];

			// Anciens indicateurs: $price, $subprice, $remise (a ne plus utiliser)
			$price = $pu;
			$subprice = $pu;
			$remise = 0;
			if ($remise_percent > 0)
			{
				$remise = round(($pu * $remise_percent / 100),2);
				$price = ($pu - $remise);
			}
			$price    = price2num($price);
			$subprice  = price2num($subprice);

			// Mise a jour ligne en base
			$sql = "UPDATE ".MAIN_DB_PREFIX."commandedet SET";
			$sql.= " description='".addslashes($desc)."'";
			$sql.= ",price='".price2num($price)."'";
			$sql.= ",subprice='".price2num($subprice)."'";
			$sql.= ",remise='".price2num($remise)."'";
			$sql.= ",remise_percent='".price2num($remise_percent)."'";
			$sql.= ",tva_tx='".price2num($txtva)."'";
			$sql.= ",localtax1_tx='".price2num($txlocaltax1)."'";
			$sql.= ",localtax2_tx='".price2num($txlocaltax2)."'";
			$sql.= ",qty='".price2num($qty)."'";
			if (! empty($type)) $sql.= ",product_type='".$type."'";
			$sql.= ",info_bits='".$info_bits."'";
			$sql.= ",total_ht='".price2num($total_ht)."'";
			$sql.= ",total_tva='".price2num($total_tva)."'";
			$sql.= ",total_localtax1='".price2num($total_localtax1)."'";
			$sql.= ",total_localtax2='".price2num($total_localtax2)."'";
			$sql.= ",total_ttc='".price2num($total_ttc)."'";
			if ($date_start) { $sql.= ",date_start='".$this->db->idate($date_start)."'"; }
			else { $sql.=',date_start=null'; }
			if ($date_end) { $sql.= ",date_end='".$this->db->idate($date_end)."'"; }
			else { $sql.=',date_end=null'; }

			$sql.= " WHERE rowid = ".$rowid;

			$result = $this->db->query($sql);
			if ($result > 0)
			{
				// Mise a jour info denormalisees
				$this->update_price();

				// Appel des triggers
				include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
				$interface=new Interfaces($this->db);
				$result=$interface->run_triggers('LINEORDER_UPDATE',$this,$user,$langs,$conf);
				if ($result < 0) { $error++; $this->errors=$interface->errors; }
				// Fin appel triggers

				$this->db->commit();
				return $result;
			}
			else
			{
				$this->error=$this->db->error();
				$this->db->rollback();
				return -1;
			}
		}
		else
		{
			$this->error="Commande::updateline Order status makes operation forbidden";
			return -2;
		}
	}


	/**
	 *	\brief		Delete the customer order
	 *	\user		User object
	 * 	\return		int		<=0 if KO, >0 if OK
	 */
	function delete($user)
	{
		global $conf, $langs;

		$err = 0;

		$this->db->begin();

		// Delete order details
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX."commandedet WHERE fk_commande = ".$this->id;
		dol_syslog("Commande::delete sql=".$sql);
		if (! $this->db->query($sql) )
		{
			dol_syslog("CustomerOrder::delete error", LOG_ERR);
			$err++;
		}

		// Delete order
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX."commande WHERE rowid = ".$this->id;
		dol_syslog("Commande::delete sql=".$sql);
		if (! $this->db->query($sql) )
		{
			dol_syslog("CustomerOrder::delete error", LOG_ERR);
			$err++;
		}

		// Delete linked object
		// TODO deplacer dans le common
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."element_element";
		$sql.= " WHERE fk_target = ".$this->id;
		$sql.= " AND targettype = '".$this->element."'";
		dol_syslog("Commande::delete sql=".$sql);
		if (! $this->db->query($sql) )
		{
			dol_syslog("CustomerOrder::delete error", LOG_ERR);
			$err++;
		}

		// Delete linked contacts
		$res = $this->delete_linked_contact();
		if ($res < 0)
		{
			dol_syslog("CustomerOrder::delete error", LOG_ERR);
			$err++;
		}

		// On efface le repertoire de pdf provisoire
		$comref = dol_sanitizeFileName($this->ref);
		if ($conf->commande->dir_output)
		{
			$dir = $conf->commande->dir_output . "/" . $comref ;
			$file = $conf->commande->dir_output . "/" . $comref . "/" . $comref . ".pdf";
			if (file_exists($file))
			{
				commande_delete_preview($this->db, $this->id, $this->ref);

				if (!dol_delete_file($file))
				{
					$this->error=$langs->trans("ErrorCanNotDeleteFile",$file);
					$this->db->rollback();
					return 0;
				}
			}
			if (file_exists($dir))
			{
				if (!dol_delete_dir($dir))
				{
					$this->error=$langs->trans("ErrorCanNotDeleteDir",$dir);
					$this->db->rollback();
					return 0;
				}
			}
		}

		if ($err == 0)
		{
			// Appel des triggers
			include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
			$interface=new Interfaces($this->db);
			$result=$interface->run_triggers('ORDER_DELETE',$this,$user,$langs,$conf);
			if ($result < 0) { $error++; $this->errors=$interface->errors; }
			// Fin appel triggers

			$this->db->commit();
			return 1;
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
	}


	/**
     *      Load indicators for dashboard (this->nbtodo and this->nbtodolate)
     *      @param          user    Objet user
     *      @return         int     <0 if KO, >0 if OK
	 */
	function load_board($user)
	{
		global $conf, $user;

		$now=gmmktime();

		$this->nbtodo=$this->nbtodolate=0;
		$clause = " WHERE";

		$sql = "SELECT c.rowid, c.date_creation as datec, c.fk_statut";
		$sql.= " FROM ".MAIN_DB_PREFIX."commande as c";
		if (!$user->rights->societe->client->voir && !$user->societe_id)
		{
			$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON c.fk_soc = sc.fk_soc";
			$sql.= " WHERE sc.fk_user = " .$user->id;
			$clause = " AND";
		}
		$sql.= $clause." c.entity = ".$conf->entity;
		$sql.= " AND ((c.fk_statut BETWEEN 1 AND 2) OR (c.fk_statut = 3 AND c.facture = 0))";
		if ($user->societe_id) $sql.=" AND c.fk_soc = ".$user->societe_id;

		$resql=$this->db->query($sql);
		if ($resql)
		{
			while ($obj=$this->db->fetch_object($resql))
			{
				$this->nbtodo++;
				if ($obj->fk_statut != 3 && $this->db->jdate($obj->datec) < ($now - $conf->commande->client->warning_delay)) $this->nbtodolate++;
			}
			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			return -1;
		}
	}

	/**
	 *    	\brief      Return source label of order
	 *    	\return     string      Label
	 */
	function getLabelSource()
	{
		global $langs;

		$label=$langs->trans('OrderSource'.$this->source);
		// \TODO Si libelle non trouve, on va chercher en base dans dictionnaire

		if ($label == 'OrderSource') return '';
		return $label;
	}

	/**
	 *    	\brief      Retourne le libelle du statut de la commande
	 *    	\param      mode        0=libelle long, 1=libelle court, 2=Picto + Libelle court, 3=Picto, 4=Picto + Libelle long, 5=Libelle court + Picto
	 *    	\return     string      Libelle
	 */
	function getLibStatut($mode)
	{
		return $this->LibStatut($this->statut,$this->facturee,$mode);
	}

	/**
	 *		\brief      Renvoi le libelle d'un statut donne
	 *    	\param      statut      Id statut
	 *    	\param      facturee    Si facturee
	 *    	\param      mode        0=libelle long, 1=libelle court, 2=Picto + Libelle court, 3=Picto, 4=Picto + Libelle long, 5=Libelle court + Picto
	 *    	\return     string		Libelle
	 */
	function LibStatut($statut,$facturee,$mode)
	{
		global $langs;

		if ($mode == 0)
		{
			if ($statut==-1) return $langs->trans('StatusOrderCanceled');
			if ($statut==0) return $langs->trans('StatusOrderDraft');
			if ($statut==1) return $langs->trans('StatusOrderValidated');
			if ($statut==2) return $langs->trans('StatusOrderOnProcess');
			if ($statut==3 && ! $facturee) return $langs->trans('StatusOrderToBill');
			if ($statut==3 && $facturee) return $langs->trans('StatusOrderProcessed');
		}
		if ($mode == 1)
		{
			if ($statut==-1) return $langs->trans('StatusOrderCanceledShort');
			if ($statut==0) return $langs->trans('StatusOrderDraftShort');
			if ($statut==1) return $langs->trans('StatusOrderValidatedShort');
			if ($statut==2) return $langs->trans('StatusOrderOnProcessShort');
			if ($statut==3 && ! $facturee) return $langs->trans('StatusOrderToBillShort');
			if ($statut==3 && $facturee) return $langs->trans('StatusOrderProcessed');
		}
		if ($mode == 2)
		{
			if ($statut==-1) return img_picto($langs->trans('StatusOrderCanceledShort'),'statut5').' '.$langs->trans('StatusOrderCanceled');
			if ($statut==0) return img_picto($langs->trans('StatusOrderDraftShort'),'statut0').' '.$langs->trans('StatusOrderDraft');
			if ($statut==1) return img_picto($langs->trans('StatusOrderValidatedShort'),'statut1').' '.$langs->trans('StatusOrderValidated');
			if ($statut==2) return img_picto($langs->trans('StatusOrderOnProcessShort'),'statut3').' '.$langs->trans('StatusOrderOnProcess');
			if ($statut==3 && ! $facturee) return img_picto($langs->trans('StatusOrderToBillShort'),'statut4').' '.$langs->trans('StatusOrderToBill');
			if ($statut==3 && $facturee) return img_picto($langs->trans('StatusOrderProcessedShort'),'statut6').' '.$langs->trans('StatusOrderProcessed');
		}
		if ($mode == 3)
		{
			if ($statut==-1) return img_picto($langs->trans('StatusOrderCanceled'),'statut5');
			if ($statut==0) return img_picto($langs->trans('StatusOrderDraft'),'statut0');
			if ($statut==1) return img_picto($langs->trans('StatusOrderValidated'),'statut1');
			if ($statut==2) return img_picto($langs->trans('StatusOrderOnProcess'),'statut3');
			if ($statut==3 && ! $facturee) return img_picto($langs->trans('StatusOrderToBill'),'statut4');
			if ($statut==3 && $facturee) return img_picto($langs->trans('StatusOrderProcessed'),'statut6');
		}
		if ($mode == 4)
		{
			if ($statut==-1) return img_picto($langs->trans('StatusOrderCanceled'),'statut5').' '.$langs->trans('StatusOrderCanceled');
			if ($statut==0) return img_picto($langs->trans('StatusOrderDraft'),'statut0').' '.$langs->trans('StatusOrderDraft');
			if ($statut==1) return img_picto($langs->trans('StatusOrderValidated'),'statut1').' '.$langs->trans('StatusOrderValidated');
			if ($statut==2) return img_picto($langs->trans('StatusOrderOnProcess'),'statut3').' '.$langs->trans('StatusOrderOnProcess');
			if ($statut==3 && ! $facturee) return img_picto($langs->trans('StatusOrderToBill'),'statut4').' '.$langs->trans('StatusOrderToBill');
			if ($statut==3 && $facturee) return img_picto($langs->trans('StatusOrderProcessed'),'statut6').' '.$langs->trans('StatusOrderProcessed');
		}
		if ($mode == 5)
		{
			if ($statut==-1) return $langs->trans('StatusOrderCanceledShort').' '.img_picto($langs->trans('StatusOrderCanceledShort'),'statut5');
			if ($statut==0) return $langs->trans('StatusOrderDraftShort').' '.img_picto($langs->trans('StatusOrderDraftShort'),'statut0');
			if ($statut==1) return $langs->trans('StatusOrderValidatedShort').' '.img_picto($langs->trans('StatusOrderValidatedShort'),'statut1');
			if ($statut==2) return $langs->trans('StatusOrderOnProcessShort').' '.img_picto($langs->trans('StatusOrderOnProcessShort'),'statut3');
			if ($statut==3 && ! $facturee) return $langs->trans('StatusOrderToBillShort').' '.img_picto($langs->trans('StatusOrderToBillShort'),'statut4');
			if ($statut==3 && $facturee) return $langs->trans('StatusOrderProcessedShort').' '.img_picto($langs->trans('StatusOrderProcessedShort'),'statut6');
		}
	}


	/**
     *      Return clicable link of object (with eventually picto)
     *      @param      withpicto       Add picto into link
     *      @param      option          Where point the link
     *      @return     string          String with URL
	 */
	function getNomUrl($withpicto=0,$option=0)
	{
		global $langs;

		$result='';
		$urlOption='';

		if ($option == 3) $urlOption = '/compta';
		if ($option == 4) $urlOption = '/expedition';

		$lien = '<a href="'.DOL_URL_ROOT.$urlOption.'/commande/fiche.php?id='.$this->id.'">';
		$lienfin='</a>';

		$picto='order';
		$label=$langs->trans("ShowOrder").': '.$this->ref;

		if ($withpicto) $result.=($lien.img_object($label,$picto).$lienfin);
		if ($withpicto && $withpicto != 2) $result.=' ';
		$result.=$lien.$this->ref.$lienfin;
		return $result;
	}


	/**
	 *      \brief     Charge les informations d'ordre info dans l'objet commande
	 *      \param     id       Id de la commande a charger
	 */
	function info($id)
	{
		$sql = 'SELECT c.rowid, date_creation as datec, tms as datem,';
		$sql.= ' date_valid as datev,';
		$sql.= ' date_cloture as datecloture,';
		$sql.= ' fk_user_author, fk_user_valid, fk_user_cloture';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'commande as c';
		$sql.= ' WHERE c.rowid = '.$id;
		$result=$this->db->query($sql);
		if ($result)
		{
			if ($this->db->num_rows($result))
			{
				$obj = $this->db->fetch_object($result);
				$this->id = $obj->rowid;
				if ($obj->fk_user_author)
				{
					$cuser = new User($this->db);
					$cuser->fetch($obj->fk_user_author);
					$this->user_creation   = $cuser;
				}

				if ($obj->fk_user_valid)
				{
					$vuser = new User($this->db);
					$vuser->fetch($obj->fk_user_valid);
					$this->user_validation = $vuser;
				}

				if ($obj->fk_user_cloture)
				{
					$cluser = new User($this->db);
					$cluser->fetch($obj->fk_user_cloture);
					$this->user_cloture   = $cluser;
				}

				$this->date_creation     = $this->db->jdate($obj->datec);
				$this->date_modification = $this->db->jdate($obj->datem);
				$this->date_validation   = $this->db->jdate($obj->datev);
				$this->date_cloture      = $this->db->jdate($obj->datecloture);
			}

			$this->db->free($result);

		}
		else
		{
			dol_print_error($this->db);
		}
	}


	/**
	 *		Initialise an example of instance with random values
	 *		Used to build previews or test instances
	 */
	function initAsSpecimen()
	{
		global $user,$langs,$conf;

		dol_syslog("Commande::initAsSpecimen");

		// Charge tableau des produits prodids
		$prodids = array();
		$sql = "SELECT rowid";
		$sql.= " FROM ".MAIN_DB_PREFIX."product";
		$sql.= " WHERE entity = ".$conf->entity;
		$resql = $this->db->query($sql);
		if ($resql)
		{
			$num_prods = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num_prods)
			{
				$i++;
				$row = $this->db->fetch_row($resql);
				$prodids[$i] = $row[0];
			}
		}

		// Initialise parametres
		$this->id=0;
		$this->ref = 'SPECIMEN';
		$this->specimen=1;
		$this->socid = 1;
		$this->date = time();
		$this->date_lim_reglement=$this->date+3600*24*30;
		$this->cond_reglement_code = 'RECEP';
		$this->mode_reglement_code = 'CHQ';
		$this->note_public='This is a comment (public)';
		$this->note='This is a comment (private)';
		// Lines
		$nbp = 5;
		$xnbp = 0;
		while ($xnbp < $nbp)
		{
			$line=new OrderLine($this->db);

			$line->desc=$langs->trans("Description")." ".$xnbp;
			$line->qty=1;
			$line->subprice=100;
			$line->price=100;
			$line->tva_tx=19.6;
			$line->total_ht=100;
			$line->total_ttc=119.6;
			$line->total_tva=19.6;
			$line->remise_percent=0;
			$prodid = rand(1, $num_prods);
			$line->fk_product=$prodids[$prodid];

			$this->lines[$xnbp]=$line;

			$xnbp++;
		}

		$this->amount_ht      = $xnbp*100;
		$this->total_ht       = $xnbp*100;
		$this->total_tva      = $xnbp*19.6;
		$this->total_ttc      = $xnbp*119.6;
	}


	/**
	 *      \brief      Charge indicateurs this->nb de tableau de bord
	 *      \return     int         <0 si ko, >0 si ok
	 */
	function load_state_board()
	{
		global $conf, $user;

		$this->nb=array();
		$clause = "WHERE";

		$sql = "SELECT count(co.rowid) as nb";
		$sql.= " FROM ".MAIN_DB_PREFIX."commande as co";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON co.fk_soc = s.rowid";
		if (!$user->rights->societe->client->voir && !$user->societe_id)
		{
			$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON s.rowid = sc.fk_soc";
			$sql.= " WHERE sc.fk_user = " .$user->id;
			$clause = "AND";
		}
		$sql.= " ".$clause." co.entity = ".$conf->entity;

		$resql=$this->db->query($sql);
		if ($resql)
		{
			while ($obj=$this->db->fetch_object($resql))
			{
				$this->nb["orders"]=$obj->nb;
			}
			return 1;
		}
		else
		{
			dol_print_error($this->db);
			$this->error=$this->db->error();
			return -1;
		}
	}

	/**
	 * 	Return an array of order lines
	 */
	function getLinesArray()
	{
		$lines = array();

		$sql = 'SELECT l.rowid, l.fk_product, l.product_type, l.description, l.price, l.qty, l.tva_tx, ';
		$sql.= ' l.fk_remise_except, l.remise_percent, l.subprice, l.info_bits,l.rang,l.special_code,';
		$sql.= ' l.total_ht, l.total_tva, l.total_ttc,';
		$sql.= ' l.date_start,';
		$sql.= ' l.date_end,';
		$sql.= ' p.label as product_label, p.ref, p.fk_product_type, p.rowid as prodid, ';
		$sql.= ' p.description as product_desc';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'commandedet as l';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON l.fk_product=p.rowid';
		$sql.= ' WHERE l.fk_commande = '.$this->id;
		$sql.= ' ORDER BY l.rang ASC, l.rowid';

		$resql = $this->db->query($sql);
		if ($resql)
		{
			$num = $this->db->num_rows($resql);
			$i = 0;

			while ($i < $num)
			{
				$obj = $this->db->fetch_object($resql);

				$this->lines[$i]->id				= $obj->rowid;
				$this->lines[$i]->description 		= $obj->description;
				$this->lines[$i]->fk_product		= $obj->fk_product;
				$this->lines[$i]->ref				= $obj->ref;
				$this->lines[$i]->product_label		= $obj->product_label;
				$this->lines[$i]->product_desc		= $obj->product_desc;
				$this->lines[$i]->fk_product_type	= $obj->fk_product_type;
				$this->lines[$i]->product_type		= $obj->product_type;
				$this->lines[$i]->qty				= $obj->qty;
				$this->lines[$i]->subprice			= $obj->subprice;
				$this->lines[$i]->fk_remise_except 	= $obj->fk_remise_except;
				$this->lines[$i]->remise_percent	= $obj->remise_percent;
				$this->lines[$i]->tva_tx			= $obj->tva_tx;
				$this->lines[$i]->info_bits			= $obj->info_bits;
				$this->lines[$i]->total_ht			= $obj->total_ht;
				$this->lines[$i]->total_tva			= $obj->total_tva;
				$this->lines[$i]->total_ttc			= $obj->total_ttc;
				$this->lines[$i]->special_code		= $obj->special_code;
				$this->lines[$i]->rang				= $obj->rang;
				$this->lines[$i]->date_start		= $this->db->jdate($obj->date_start);
				$this->lines[$i]->date_end			= $this->db->jdate($obj->date_end);

				$i++;
			}

			$this->db->free($resql);

			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog("Error sql=$sql, error=".$this->error,LOG_ERR);
			return -1;
		}
	}

}


/**
 *  \class      OrderLine
 *  \brief      Classe de gestion des lignes de commande
 */
class OrderLine
{
	var $db;
	var $error;

	// From llx_commandedet
	var $rowid;
	var $fk_facture;
	var $desc;          	// Description ligne
	var $fk_product;		// Id produit predefini
	var $product_type = 0;	// Type 0 = product, 1 = Service

	var $qty;				// Quantity (example 2)
	var $tva_tx;			// VAT Rate for product/service (example 19.6)
	var $localtax1_tx; 		// Local tax 1
	var $localtax2_tx; 		// Local tax 2
	var $subprice;      	// U.P. HT (example 100)
	var $remise_percent;	// % for line discount (example 20%)
	var $rang = 0;
	var $marge_tx;
	var $marque_tx;
	var $info_bits = 0;		// Bit 0: 	0 si TVA normal - 1 si TVA NPR
	// Bit 1:	0 ligne normale - 1 si ligne de remise fixe
	var $total_ht;			// Total HT  de la ligne toute quantite et incluant la remise ligne
	var $total_tva;			// Total TVA  de la ligne toute quantite et incluant la remise ligne
	var $total_localtax1;   // Total local tax 1 for the line
	var $total_localtax2;   // Total local tax 2 for the line
	var $total_ttc;			// Total TTC de la ligne toute quantite et incluant la remise ligne

	// Ne plus utiliser
	var $remise;
	var $price;

	// From llx_product
	var $ref;				// Reference produit
	var $product_libelle; 	// Label produit
	var $product_desc;  	// Description produit

	// Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
	// Start and end date of the line
	var $date_start;
	var $date_end;


	/**
	 *      \brief     Constructeur d'objets ligne de commande
	 *      \param     DB      handler d'acces base de donnee
	 */
	function OrderLine($DB)
	{
		$this->db= $DB;
	}

	/**
	 *  \brief     Load line order
	 *  \param     rowid           id line order
	 */
	function fetch($rowid)
	{
		$sql = 'SELECT cd.rowid, cd.fk_commande, cd.fk_product, cd.product_type, cd.description, cd.price, cd.qty, cd.tva_tx, cd.localtax1_tx, cd.localtax2_tx,';
		$sql.= ' cd.remise, cd.remise_percent, cd.fk_remise_except, cd.subprice,';
		$sql.= ' cd.info_bits, cd.total_ht, cd.total_tva, cd.total_localtax1, cd.total_localtax2, cd.total_ttc, cd.marge_tx, cd.marque_tx, cd.rang, cd.special_code,';
		$sql.= ' p.ref as product_ref, p.label as product_libelle, p.description as product_desc,';
		$sql.= ' cd.date_start, cd.date_end';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'commandedet as cd';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON cd.fk_product = p.rowid';
		$sql.= ' WHERE cd.rowid = '.$rowid;
		$result = $this->db->query($sql);
		if ($result)
		{
			$objp = $this->db->fetch_object($result);
			$this->rowid            = $objp->rowid;
			$this->fk_commande      = $objp->fk_commande;
			$this->desc             = $objp->description;
			$this->qty              = $objp->qty;
			$this->price            = $objp->price;
			$this->subprice         = $objp->subprice;
			$this->tva_tx           = $objp->tva_tx;
			$this->localtax1_tx		= $objp->localtax1_tx;
			$this->localtax2_tx		= $objp->localtax2_tx;
			$this->remise           = $objp->remise;
			$this->remise_percent   = $objp->remise_percent;
			$this->fk_remise_except = $objp->fk_remise_except;
			$this->fk_product       = $objp->fk_product;
			$this->product_type     = $objp->product_type;
			$this->info_bits        = $objp->info_bits;
			$this->total_ht         = $objp->total_ht;
			$this->total_tva        = $objp->total_tva;
			$this->total_localtax1  = $objp->total_localtax1;
			$this->total_localtax2  = $objp->total_localtax2;
			$this->total_ttc        = $objp->total_ttc;
			$this->marge_tx         = $objp->marge_tx;
			$this->marque_tx        = $objp->marque_tx;
			$this->special_code		= $objp->special_code;
			$this->rang             = $objp->rang;

			$this->ref	            = $objp->product_ref;
			$this->product_libelle  = $objp->product_libelle;
			$this->product_desc     = $objp->product_desc;

			$this->date_start       = $this->db->jdate($objp->date_start);
			$this->date_end         = $this->db->jdate($objp->date_end);

			$this->db->free($result);
		}
		else
		{
			dol_print_error($this->db);
		}
	}

	/**
	 *    	\brief     	Supprime la ligne de commande en base
	 *		\user		User object
	 *		\return		int <0 si ko, >0 si ok
	 */
	function delete($user)
	{
		global $langs, $conf;

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX."commandedet WHERE rowid='".$this->id."';";

		dol_syslog("OrderLine::delete sql=".$sql);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			// Appel des triggers
			include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
			$interface=new Interfaces($this->db);
			$result=$interface->run_triggers('LINEORDER_DELETE',$this,$user,$langs,$conf);
			if ($result < 0) { $error++; $this->errors=$interface->errors; }
			// Fin appel triggers

			return 1;
		}
		else
		{
			$this->error=$this->db->lasterror();
			dol_syslog("OrderLine::delete ".$this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 *   	\brief     	Insert line into database
	 *   	\param      notrigger		1 = disable triggers
	 *		\return		int				<0 if KO, >0 if OK
	 */
	function insert($notrigger=0)
	{
		global $langs, $conf, $user;

		dol_syslog("OrderLine::insert rang=".$this->rang);

		// Clean parameters
		if (empty($this->tva_tx)) $this->tva_tx=0;
		if (empty($this->localtax1_tx)) $this->localtax1_tx=0;
		if (empty($this->localtax2_tx)) $this->localtax2_tx=0;
		if (empty($this->total_localtax1)) $this->total_localtax1=0;
		if (empty($this->total_localtax2)) $this->total_localtax2=0;
        if (empty($this->rang)) $this->rang=0;
        if (empty($this->remise)) $this->remise=0;
        if (empty($this->remise_percent)) $this->remise_percent=0;
        if (empty($this->info_bits)) $this->info_bits=0;
        if (empty($this->special_code)) $this->special_code=0;

        // Check parameters
        if ($this->product_type < 0) return -1;

        $this->db->begin();

		// Insertion dans base de la ligne
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'commandedet';
		$sql.= ' (fk_commande, description, qty, tva_tx, localtax1_tx, localtax2_tx,';
		$sql.= ' fk_product, product_type, remise_percent, subprice, price, remise, fk_remise_except,';
		$sql.= ' special_code, rang, marge_tx, marque_tx,';
		$sql.= ' info_bits, total_ht, total_tva, total_localtax1, total_localtax2, total_ttc, date_start, date_end)';
		$sql.= " VALUES (".$this->fk_commande.",";
		$sql.= " '".addslashes($this->desc)."',";
		$sql.= " '".price2num($this->qty)."',";
		$sql.= " '".price2num($this->tva_tx)."',";
		$sql.= " '".price2num($this->localtax1_tx)."',";
		$sql.= " '".price2num($this->localtax2_tx)."',";
		if ($this->fk_product) { $sql.= "'".$this->fk_product."',"; }
		else { $sql.='null,'; }
		$sql.= " '".$this->product_type."',";
		$sql.= " '".price2num($this->remise_percent)."',";
		$sql.= " ".($this->subprice!=''?"'".price2num($this->subprice)."'":"null").",";
		$sql.= " ".($this->price!=''?"'".price2num($this->price)."'":"null").",";
		$sql.= " '".price2num($this->remise)."',";
		if ($this->fk_remise_except) $sql.= $this->fk_remise_except.",";
		else $sql.= 'null,';
		$sql.= ' '.$this->special_code.',';
		$sql.= ' '.$this->rang.',';
		if (isset($this->marge_tx)) $sql.= ' '.$this->marge_tx.',';
		else $sql.= ' null,';
		if (isset($this->marque_tx)) $sql.= ' '.$this->marque_tx.',';
		else $sql.= ' null,';
		$sql.= " '".$this->info_bits."',";
		$sql.= " '".price2num($this->total_ht)."',";
		$sql.= " '".price2num($this->total_tva)."',";
		$sql.= " '".price2num($this->total_localtax1)."',";
		$sql.= " '".price2num($this->total_localtax2)."',";
		// Updated by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
		// Insert in the database the start and end dates
		$sql.= " '".price2num($this->total_ttc)."',";
		if ($this->date_start) { $sql.= "'".$this->db->idate($this->date_start)."',"; }
		else { $sql.='null,'; }
		if ($this->date_end)   { $sql.= "'".$this->db->idate($this->date_end)."'"; }
		else { $sql.='null'; }
		$sql.= ')';

		dol_syslog("OrderLine::insert sql=".$sql);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$this->rowid=$this->db->last_insert_id(MAIN_DB_PREFIX.'commandedet');

			if (! $notrigger)
			{
				// Appel des triggers
				include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
				$interface=new Interfaces($this->db);
				$result=$interface->run_triggers('LINEORDER_INSERT',$this,$user,$langs,$conf);
				if ($result < 0) { $error++; $this->errors=$interface->errors; }
				// Fin appel triggers
			}

			$this->db->commit();
			return $this->rowid;
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog("OrderLine::insert Error ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -2;
		}
	}


	/**
	 *      \brief     	Mise a jour de l'objet ligne de commande en base
	 *		\return		int		<0 si ko, >0 si ok
	 */
	function update_total()
	{
		$this->db->begin();

		// Mise a jour ligne en base
		$sql = "UPDATE ".MAIN_DB_PREFIX."commandedet SET";
		$sql.= " total_ht='".price2num($this->total_ht)."'";
		$sql.= ",total_tva='".price2num($this->total_tva)."'";
		$sql.= ",total_localtax1='".price2num($this->total_localtax1)."'";
		$sql.= ",total_localtax2='".price2num($this->total_localtax2)."'";
		$sql.= ",total_ttc='".price2num($this->total_ttc)."'";
		$sql.= " WHERE rowid = ".$this->rowid;

		dol_syslog("OrderLine::update_total sql=$sql");

		$resql=$this->db->query($sql);
		if ($resql)
		{
			$this->db->commit();
			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog("OrderLine::update_total Error ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -2;
		}
	}
}

?>
