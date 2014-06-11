<?php
/* Copyright (C) 2003-2008 Rodolphe Quiedeville  <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2010 Regis Houssin         <regis@dolibarr.fr>
 * Copyright (C) 2007      Franky Van Liedekerke <franky.van.liedekerke@telenet.be>
 * Copyright (C) 2006-2008 Laurent Destailleur   <eldy@users.sourceforge.net>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/expedition/class/expedition.class.php
 *  \ingroup    expedition
 *  \brief      Fichier de la classe de gestion des expeditions
 *  \version    $Id: expedition.class.php,v 1.32 2010/12/15 07:30:55 hregis Exp $
 */

require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");
if ($conf->propal->enabled) require_once(DOL_DOCUMENT_ROOT."/comm/propal/class/propal.class.php");
if ($conf->commande->enabled) require_once(DOL_DOCUMENT_ROOT."/commande/class/commande.class.php");


/**
 *	\class      Expedition
 *	\brief      Class to manage shippings
 */
class Expedition extends CommonObject
{
	var $db;
	var $error;
	var $element="shipping";
	var $fk_element="fk_expedition";
	var $table_element="expedition";

	var $id;
	var $socid;
	var $ref_customer;
	var $brouillon;
	var $entrepot_id;
	var $modelpdf;
	var $origin;
	var $origin_id;
	var $lines=array();
	var $expedition_method_id;
	var $statut;

	var $trueWeight;
	var $weight_units;
	var $trueWidth;
	var $width_units;
	var $trueHeight;
	var $height_units;
	var $trueDepth;
	var $depth_units;
	// A denormalized value
	var $trueSize;

	var $date_delivery;		// Date delivery planed
	var $date_expedition;	// Date delivery real
	var $date_creation;
	var $date_valid;

	/**
	 * Initialisation
	 */
	function Expedition($DB)
	{
		$this->db = $DB;
		$this->lines = array();
		$this->products = array();

		// List of long language codes for status
		$this->statuts[-1] = 'StatusSendingCanceled';
		$this->statuts[0]  = 'StatusSendingDraft';
		$this->statuts[1]  = 'StatusSendingValidated';
	}

	/**
	 *    \brief      Cree expedition en base
	 *    \param      user        Objet du user qui cree
	 *    \return     int         <0 si erreur, id expedition creee si ok
	 */
	function create($user)
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT ."/product/stock/class/mouvementstock.class.php";
		$error = 0;

		// Clean parameters
		$this->brouillon = 1;
		$this->tracking_number = dol_sanitizeFileName($this->tracking_number);

		$this->user = $user;


		$this->db->begin();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."expedition (";
		$sql.= "ref";
		$sql.= ", ref_customer";
		$sql.= ", entity";
		$sql.= ", date_creation";
		$sql.= ", fk_user_author";
		$sql.= ", date_expedition";
		$sql.= ", date_delivery";
		$sql.= ", fk_soc";
		$sql.= ", fk_address";
		$sql.= ", fk_expedition_methode";
		$sql.= ", tracking_number";
		$sql.= ", weight";
		$sql.= ", size";
		$sql.= ", width";
		$sql.= ", height";
		$sql.= ", weight_units";
		$sql.= ", size_units";
		$sql.= ") VALUES (";
		$sql.= "'(PROV)'";
		$sql.= ", '".$this->ref_customer."'";
		$sql.= ", ".$conf->entity;
		$sql.= ", '".$this->db->idate(gmmktime())."'";
		$sql.= ", ".$user->id;
		$sql.= ", ".($this->date_expedition>0?"'".$this->db->idate($this->date_expedition)."'":"null");
		$sql.= ", ".($this->date_delivery>0?"'".$this->db->idate($this->date_delivery)."'":"null");
		$sql.= ", ".$this->socid;
		$sql.= ", ".($this->fk_delivery_address>0?$this->fk_delivery_address:"null");
		$sql.= ", ".($this->expedition_method_id>0?$this->expedition_method_id:"null");
		$sql.= ", '".addslashes($this->tracking_number)."'";
		$sql.= ", ".$this->weight;
		$sql.= ", ".$this->sizeS;	// TODO Should use this->trueDepth
		$sql.= ", ".$this->sizeW;	// TODO Should use this->trueWidth
		$sql.= ", ".$this->sizeH;	// TODO Should use this->trueHeight
		$sql.= ", ".$this->weight_units;
		$sql.= ", ".$this->size_units;
		$sql.= ")";

		$resql=$this->db->query($sql);
		if ($resql)
		{
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."expedition");

			$sql = "UPDATE ".MAIN_DB_PREFIX."expedition";
			$sql.= " SET ref = '(PROV".$this->id.")'";
			$sql.= " WHERE rowid = ".$this->id;

			dol_syslog("Expedition::create sql=".$sql, LOG_DEBUG);
			if ($this->db->query($sql))
			{
				// Insertion des lignes
				for ($i = 0 ; $i < sizeof($this->lines) ; $i++)
				{
					if (! $this->create_line($this->lines[$i]->entrepot_id, $this->lines[$i]->origin_line_id, $this->lines[$i]->qty) > 0)
					{
						$error++;
					}
				}

				if (! $error && $this->id && $this->origin_id)
				{
					$ret = $this->add_object_linked();
					if (!$ret)
					{
						$error++;
					}

					// TODO uniformiser les statuts
					$ret = $this->setStatut(2,$this->origin_id,$this->origin);
					if (! $ret)
					{
						$error++;
					}
				}

				if (! $error)
				{
					$this->db->commit();
					return $this->id;
				}
				else
				{
					$error++;
					$this->error=$this->db->lasterror()." - sql=$sql";
					$this->db->rollback();
					return -3;
				}
			}
			else
			{
				$error++;
				$this->error=$this->db->lasterror()." - sql=$sql";
				$this->db->rollback();
				return -2;
			}
		}
		else
		{
			$error++;
			$this->error=$this->db->error()." - sql=$sql";
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *
	 *
	 */
	function create_line($entrepot_id, $origin_line_id, $qty)
	{
		$error = 0;

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."expeditiondet (";
		$sql.= "fk_expedition";
		$sql.= ", fk_entrepot";
		$sql.= ", fk_origin_line";
		$sql.= ", qty";
		$sql.= ") VALUES (";
		$sql.= $this->id;
		$sql.= ", ".($entrepot_id?$entrepot_id:'null');
		$sql.= ", ".$origin_line_id;
		$sql.= ", ".$qty;
		$sql.= ")";

		if (! $this->db->query($sql))
		{
			$error++;
		}

		if (! $error) return 1;
		else return -1;
	}

	/**
	 *		\brief		Lit une expedition
	 *		\param		id		Id of object
	 * 		\param		ref		Ref of object
	 */
	function fetch($id,$ref='')
	{
		global $conf;

		if (empty($id) && empty($ref)) return -1;

		$sql = "SELECT e.rowid, e.ref, e.fk_soc as socid, e.date_creation, e.ref_customer, e.fk_user_author, e.fk_statut";
		$sql.= ", e.weight, e.weight_units, e.size, e.size_units, e.width, e.height";
		$sql.= ", e.date_expedition as date_expedition, e.model_pdf, e.fk_address, e.date_delivery";
		$sql.= ", e.fk_expedition_methode, e.tracking_number";
		$sql.= ", el.fk_source as origin_id, el.sourcetype as origin";
		$sql.= " FROM ".MAIN_DB_PREFIX."expedition as e";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_element as el ON el.fk_target = e.rowid AND el.targettype = '".$this->element."'";
		if ($id) $sql.= " WHERE e.rowid = ".$id;
		if ($ref) $sql.= " WHERE e.ref = '".$ref."'";

		dol_syslog("Expedition::fetch sql=".$sql);
		$result = $this->db->query($sql) ;
		if ($result)
		{
			if ($this->db->num_rows($result))
			{
				$obj = $this->db->fetch_object($result);

				$this->id                   = $obj->rowid;
				$this->ref                  = $obj->ref;
				$this->socid                = $obj->socid;
				$this->ref_customer			= $obj->ref_customer;
				$this->statut               = $obj->fk_statut;
				$this->user_author_id       = $obj->fk_user_author;
				$this->date_creation        = $this->db->jdate($obj->date_creation);
				$this->date                 = $this->db->jdate($obj->date_expedition);	// TODO obsolete
				$this->date_expedition      = $this->db->jdate($obj->date_expedition);	// TODO obsolete
				$this->date_shipping        = $this->db->jdate($obj->date_expedition);	// Date real
				$this->date_delivery        = $this->db->jdate($obj->date_delivery);	// Date planed
				$this->fk_delivery_address  = $obj->fk_address;
				$this->modelpdf             = $obj->model_pdf;
				$this->expedition_method_id = $obj->fk_expedition_methode;
				$this->tracking_number      = $obj->tracking_number;
				$this->origin               = ($obj->origin?$obj->origin:'commande'); // For compatibility
				$this->origin_id            = $obj->origin_id;

				$this->trueWeight           = $obj->weight;
				$this->weight_units         = $obj->weight_units;

				$this->trueWidth            = $obj->width;
				$this->width_units          = $obj->size_units;
				$this->trueHeight           = $obj->height;
				$this->height_units         = $obj->size_units;
				$this->trueDepth            = $obj->size;
				$this->depth_units          = $obj->size_units;

				// A denormalized value
				$this->trueSize           	= $obj->size."x".$obj->width."x".$obj->height;
				$this->size_units           = $obj->size_units;

				$this->db->free($result);

				if ($this->statut == 0) $this->brouillon = 1;

				$file = $conf->expedition->dir_output . "/" .get_exdir($expedition->id,2) . "/" . $this->id.".pdf";
				$this->pdf_filename = $file;

				/*
				 * Lines
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
				dol_syslog('Expedition::Fetch Error rowid='.$rowid.' numrows=0 sql='.$sql);
				$this->error='Delivery with id '.$rowid.' not found sql='.$sql;
				return -2;
			}
		}
		else
		{
			dol_syslog('Expedition::Fetch Error rowid='.$rowid.' Erreur dans fetch de l\'expedition');
			$this->error=$this->db->error();
			return -1;
		}
	}

	/**
	 *        \brief      Validate object and update stock if option enabled
	 *        \param      user        Objet de l'utilisateur qui valide
	 *        \return     int
	 */
	function valid($user)
	{
		global $conf;

		dol_syslog("Expedition::valid");

		// Protection
		if ($this->statut)
		{
			dol_syslog("Expedition::valid no draft status", LOG_WARNING);
			return 0;
		}

		if (! $user->rights->expedition->valider)
		{
			$this->error='Permission denied';
			dol_syslog("Expedition::valid ".$this->error, LOG_ERR);
			return -1;
		}

		$this->db->begin();

		$error = 0;

		// Define new ref
		$num = "EXP".$this->id;

		$now=dol_now();

		// Validate
		$sql = "UPDATE ".MAIN_DB_PREFIX."expedition SET";
		$sql.= " ref='".$num."'";
		$sql.= ", fk_statut = 1";
		$sql.= ", date_valid = '".$this->db->idate($now)."'";
		$sql.= ", fk_user_valid = ".$user->id;
		$sql.= " WHERE rowid = ".$this->id;

		dol_syslog("Expedition::valid update expedition sql=".$sql);
		$resql=$this->db->query($sql);
		if (! $resql)
		{
			dol_syslog("Expedition::valid Echec update - 10 - sql=".$sql, LOG_ERR);
			$this->error=$this->db->lasterror();
			$error++;
		}

		// If stock increment is done on sending (recommanded choice)
		if (! $error && $conf->stock->enabled && $conf->global->STOCK_CALCULATE_ON_SHIPMENT)
		{
			require_once DOL_DOCUMENT_ROOT."/product/stock/class/mouvementstock.class.php";

			// Loop on each product line to add a stock movement
			// TODO possibilite d'expedier a partir d'une propale ou autre origine
			$sql = "SELECT cd.fk_product, cd.subprice, ed.qty, ed.fk_entrepot";
			$sql.= " FROM ".MAIN_DB_PREFIX."commandedet as cd";
			$sql.= ", ".MAIN_DB_PREFIX."expeditiondet as ed";
			$sql.= " WHERE ed.fk_expedition = ".$this->id;
			$sql.= " AND cd.rowid = ed.fk_origin_line";

			dol_syslog("Expedition::valid select details sql=".$sql);
			$resql=$this->db->query($sql);
			if ($resql)
			{
				$num = $this->db->num_rows($resql);
				$i=0;
				while($i < $num)
				{
					dol_syslog("Expedition::valid movement index ".$i);
					$obj = $this->db->fetch_object($resql);

					if ($this->lines[$i]->fk_product > 0 && $this->lines[$i]->product_type == 0)
					{
						//var_dump($this->lines[$i]);
						$mouvS = new MouvementStock($this->db);
						// We decrement stock of product (and sub-products)
						// We use warehouse selected for each line
						$result=$mouvS->livraison($user, $obj->fk_product, $obj->fk_entrepot, $obj->qty, $obj->subprice);
						if ($result < 0) { $error++; break; }
					}

					$i++;
				}
			}
			else
			{
				$this->db->rollback();
				$this->error=$this->db->error();
				dol_syslog("Expedition::valid ".$this->error, LOG_ERR);
				return -2;
			}
		}

		if (! $error)
		{
			// On efface le repertoire de pdf provisoire
			$expeditionref = dol_sanitizeFileName($this->ref);
			if ($conf->expedition->dir_output)
			{
				$dir = $conf->expedition->dir_output . "/" . $expeditionref;
				$file = $dir . "/" . $expeditionref . ".pdf";
				if (file_exists($file))
				{
					if (!dol_delete_file($file))
					{
						$this->error=$langs->trans("ErrorCanNotDeleteFile",$file);
					}
				}
				if (file_exists($dir))
				{
					if (!dol_delete_dir($dir))
					{
						$this->error=$langs->trans("ErrorCanNotDeleteDir",$dir);
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
			include_once(DOL_DOCUMENT_ROOT."/core/class/interfaces.class.php");
			$interface=new Interfaces($this->db);
			$result=$interface->run_triggers('SHIPPING_VALIDATE',$this,$user,$langs,$conf);
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
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::valid ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
	}


	/**
	 *      \brief      Cree un bon de livraison a partir de l'expedition
	 *      \param      user        Utilisateur
	 *      \return     int         <0 si ko, >=0 si ok
	 */
	function create_delivery($user)
	{
		global $conf;

		if ($conf->livraison_bon->enabled)
		{
			if ($this->statut == 1)
			{
				// Expedition validee
				include_once(DOL_DOCUMENT_ROOT."/livraison/class/livraison.class.php");
				$delivery = new Livraison($this->db);
				$result=$delivery->create_from_sending($user, $this->id);
				if ($result > 0)
				{
					return $result;
				}
				else
				{
					$this->error=$delivery->error;
					return $result;
				}
			}
			else return 0;
		}
		else return 0;
	}

	/**
	 * Ajoute une ligne
	 *
	 */
	function addline( $entrepot_id, $id, $qty )
	{
		$num = sizeof($this->lines);
		$line = new ExpeditionLigne($this->db);

		$line->entrepot_id = $entrepot_id;
		$line->origin_line_id = $id;
		$line->qty = $qty;

		$this->lines[$num] = $line;
	}

	/**
	 *
	 *
	 */
	function deleteline($lineid)
	{
		if ($this->statut == 0)
		{
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."commandedet";
			$sql.= " WHERE rowid = ".$lineid;

			if ($this->db->query($sql) )
			{
				$this->update_price();

				return 1;
			}
			else
			{
				return 0;
			}
		}
	}

    /**
     *      \brief      Update database
     *      \param      user        	User that modify
     *      \param      notrigger	    0=launch triggers after, 1=disable triggers
     *      \return     int         	<0 if KO, >0 if OK
     */
    function update($user=0, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters

		if (isset($this->ref)) $this->ref=trim($this->ref);
		if (isset($this->entity)) $this->entity=trim($this->entity);
		if (isset($this->ref_customer)) $this->ref_customer=trim($this->ref_customer);
		if (isset($this->socid)) $this->socid=trim($this->socid);
		if (isset($this->fk_user_author)) $this->fk_user_author=trim($this->fk_user_author);
		if (isset($this->fk_user_valid)) $this->fk_user_valid=trim($this->fk_user_valid);
		if (isset($this->fk_adresse_livraison)) $this->fk_adresse_livraison=trim($this->fk_adresse_livraison);
		if (isset($this->expedition_method_id)) $this->expedition_method_id=trim($this->expedition_method_id);
		if (isset($this->tracking_number)) $this->tracking_number=trim($this->tracking_number);
		if (isset($this->statut)) $this->statut=trim($this->statut);
		if (isset($this->trueDepth)) $this->trueDepth=trim($this->trueDepth);
		if (isset($this->trueWidth)) $this->trueWidth=trim($this->trueWidth);
		if (isset($this->trueHeight)) $this->trueHeight=trim($this->trueHeight);
		if (isset($this->size_units)) $this->size_units=trim($this->size_units);
		if (isset($this->weight_units)) $this->weight_units=trim($this->weight_units);
		if (isset($this->trueWeight)) $this->weight=trim($this->trueWeight);
		if (isset($this->note)) $this->note=trim($this->note);
		if (isset($this->model_pdf)) $this->model_pdf=trim($this->model_pdf);



		// Check parameters
		// Put here code to add control on parameters values

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."expedition SET";

		$sql.= " tms=".(dol_strlen($this->tms)!=0 ? "'".$this->db->idate($this->tms)."'" : 'null').",";
		$sql.= " ref=".(isset($this->ref)?"'".addslashes($this->ref)."'":"null").",";
		$sql.= " ref_customer=".(isset($this->ref_customer)?"'".addslashes($this->ref_customer)."'":"null").",";
		$sql.= " fk_soc=".(isset($this->socid)?$this->socid:"null").",";
		$sql.= " date_creation=".(dol_strlen($this->date_creation)!=0 ? "'".$this->db->idate($this->date_creation)."'" : 'null').",";
		$sql.= " fk_user_author=".(isset($this->fk_user_author)?$this->fk_user_author:"null").",";
		$sql.= " date_valid=".(dol_strlen($this->date_valid)!=0 ? "'".$this->db->idate($this->date_valid)."'" : 'null').",";
		$sql.= " fk_user_valid=".(isset($this->fk_user_valid)?$this->fk_user_valid:"null").",";
		$sql.= " date_expedition=".(dol_strlen($this->date_expedition)!=0 ? "'".$this->db->idate($this->date_expedition)."'" : 'null').",";
		$sql.= " date_delivery=".(dol_strlen($this->date_delivery)!=0 ? "'".$this->db->idate($this->date_delivery)."'" : 'null').",";
		$sql.= " fk_address=".(isset($this->fk_adresse_livraison)?$this->fk_adresse_livraison:"null").",";
		$sql.= " fk_expedition_methode=".(isset($this->expedition_method_id)?$this->expedition_method_id:"null").",";
		$sql.= " tracking_number=".(isset($this->tracking_number)?"'".addslashes($this->tracking_number)."'":"null").",";
		$sql.= " fk_statut=".(isset($this->statut)?$this->statut:"null").",";
		$sql.= " height=".(isset($this->trueHeight)?$this->trueHeight:"null").",";
		$sql.= " width=".(isset($this->trueWidth)?$this->trueWidth:"null").",";
		$sql.= " size_units=".(isset($this->size_units)?$this->size_units:"null").",";
		$sql.= " size=".(isset($this->trueDepth)?$this->trueDepth:"null").",";
		$sql.= " weight_units=".(isset($this->weight_units)?$this->weight_units:"null").",";
		$sql.= " weight=".(isset($this->trueWeight)?$this->trueWeight:"null").",";
		$sql.= " note=".(isset($this->note)?"'".addslashes($this->note)."'":"null").",";
		$sql.= " model_pdf=".(isset($this->model_pdf)?"'".addslashes($this->model_pdf)."'":"null").",";
		$sql.= " entity=".$conf->entity;

        $sql.= " WHERE rowid=".$this->id;

		$this->db->begin();

		dol_syslog(get_class($this)."::update sql=".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
		{
			if (! $notrigger)
			{
	            // Call triggers
	            include_once(DOL_DOCUMENT_ROOT."/core/class/interfaces.class.php");
	            $interface=new Interfaces($this->db);
	            $result=$interface->run_triggers('SHIPPING_MODIFY',$this,$user,$langs,$conf);
	            if ($result < 0) { $error++; $this->errors=$interface->errors; }
	            // End call triggers
	    	}
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::update ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
    }

    /**
	 * 	\brief		Delete shipping
	 */
	function delete()
	{
		$this->db->begin();

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."expeditiondet";
		$sql.= " WHERE fk_expedition = ".$this->id;

		if ( $this->db->query($sql) )
		{
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."element_element";
			$sql.= " WHERE fk_target = ".$this->id;
			$sql.= " AND targettype = '".$this->element."'";

			if ( $this->db->query($sql) )
			{
				$sql = "DELETE FROM ".MAIN_DB_PREFIX."expedition";
				$sql.= " WHERE rowid = ".$this->id;

				if ( $this->db->query($sql) )
				{
					$this->db->commit();

					// On efface le repertoire de pdf provisoire
					$expref = dol_sanitizeFileName($this->ref);
					if ($conf->expedition->dir_output)
					{
						$dir = $conf->expedition->dir_output . "/" . $expref ;
						$file = $conf->expedition->dir_output . "/" . $expref . "/" . $expref . ".pdf";
						if (file_exists($file))
						{
							if (!dol_delete_file($file))
							{
								$this->error=$langs->trans("ErrorCanNotDeleteFile",$file);
								return 0;
							}
						}
						if (file_exists($dir))
						{
							if (!dol_delete_dir($dir))
							{
								$this->error=$langs->trans("ErrorCanNotDeleteDir",$dir);
								return 0;
							}
						}
					}
					// TODO il faut incrementer le stock si on supprime une expedition validee
					return 1;
				}
				else
				{
					$this->error=$this->db->lasterror()." - sql=$sql";
					$this->db->rollback();
					return -3;
				}
			}
			else
			{
				$this->error=$this->db->lasterror()." - sql=$sql";
				$this->db->rollback();
				return -2;
			}
		}
		else
		{
			$this->error=$this->db->lasterror()." - sql=$sql";
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *
	 *
	 */
	function fetch_lines()
	{
		// TODO: recuperer les champs du document associe a part

		$sql = "SELECT cd.rowid, cd.fk_product, cd.description, cd.qty as qty_asked";
		$sql.= ", ed.qty as qty_shipped, ed.fk_origin_line, ed.fk_entrepot";
		$sql.= ", p.ref, p.fk_product_type, p.label, p.weight, p.weight_units, p.volume, p.volume_units";
		$sql.= " FROM (".MAIN_DB_PREFIX."expeditiondet as ed,";
		$sql.= " ".MAIN_DB_PREFIX."commandedet as cd)";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = cd.fk_product";
		$sql.= " WHERE ed.fk_expedition = ".$this->id;
		$sql.= " AND ed.fk_origin_line = cd.rowid";

		dol_syslog("Expedition::fetch_lines sql=".$sql);
		$resql = $this->db->query($sql);
		if ($resql)
		{
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num)
			{
				$line = new ExpeditionLigne($this->db);
				$obj = $this->db->fetch_object($resql);

				$line->fk_origin_line 	= $obj->fk_origin_line;
				$line->origin_line_id 	= $obj->fk_origin_line;	// TODO deprecated
				$line->entrepot_id    	= $obj->fk_entrepot;
				$line->fk_product     	= $obj->fk_product;
				$line->fk_product_type	= $obj->fk_product_type;
				$line->ref            	= $obj->ref;
				$line->label          	= $obj->label;
				$line->libelle        	= $obj->label;			// TODO deprecated
				$line->description    	= $obj->description;
				$line->qty_asked      	= $obj->qty_asked;
				$line->qty_shipped    	= $obj->qty_shipped;
				$line->weight         	= $obj->weight;
				$line->weight_units   	= $obj->weight_units;
				$line->volume         	= $obj->volume;
				$line->volume_units   	= $obj->volume_units;

				$this->lines[$i] = $line;

				$i++;
			}
			$this->db->free($resql);
			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog('Expedition::fetch_lines: Error '.$this->error, LOG_ERR);
			return -3;
		}
	}

	/**
	 *	\brief      Renvoie nom clicable (avec eventuellement le picto)
	 *	\param		withpicto		0=Pas de picto, 1=Inclut le picto dans le lien, 2=Picto seul
	 *	\return		string			Chaine avec URL
	 */
	function getNomUrl($withpicto=0)
	{
		global $langs;

		$result='';
		$urlOption='';


		$lien = '<a href="'.DOL_URL_ROOT.'/expedition/fiche.php?id='.$this->id.'">';
		$lienfin='</a>';

		$picto='sending';
		$label=$langs->trans("ShowSending").': '.$this->ref;

		if ($withpicto) $result.=($lien.img_object($label,$picto).$lienfin);
		if ($withpicto && $withpicto != 2) $result.=' ';
		$result.=$lien.$this->ref.$lienfin;
		return $result;
	}

	/**
	 *    \brief      Retourne le libelle du statut d'une expedition
	 *    \return     string      Libelle
	 */
	function getLibStatut($mode=0)
	{
		return $this->LibStatut($this->statut,$mode);
	}

	/**
	 *    	\brief      Return label of a status
	 * 		\param      statut		Id statut
	 *    	\param      mode        0=Long label, 1=Short label, 2=Picto + Short label, 3=Picto, 4=Picto + Long label, 5=Short label + Picto
	 *    	\return     string		Label of status
	 */
	function LibStatut($statut,$mode)
	{
		global $langs;

		if ($mode==0)
		{
			if ($statut==0) return $langs->trans($this->statuts[$statut]);
			if ($statut==1) return $langs->trans($this->statuts[$statut]);
		}
		if ($mode==1)
		{
			if ($statut==0) return $langs->trans($this->statuts[$statut]);
			if ($statut==1) return $langs->trans($this->statuts[$statut]);
		}
		if ($mode == 4)
		{
			if ($statut==0) return img_picto($this->statuts[$statut],'statut0').' '.$langs->trans($this->statuts[$statut]);
			if ($statut==1) return img_picto($this->statuts[$statut],'statut4').' '.$langs->trans($this->statuts[$statut]);
		}
		if ($mode == 5)
		{
			if ($statut==0) return $langs->trans('StatusSendingDraftShort').' '.img_picto($langs->trans($this->statuts[$statut]),'statut0');
			if ($statut==1) return $langs->trans('StatusSendingValidatedShort').' '.img_picto($langs->trans($this->statuts[$statut]),'statut4');
		}
	}

	/**
	 *		\brief		Initialise la facture avec valeurs fictives aleatoire
	 *					Sert a generer une facture pour l'aperu des modeles ou dem
	 */
	function initAsSpecimen()
	{
		global $user,$langs,$conf;

		dol_syslog("Expedition::initAsSpecimen");

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

		$order=new Commande($this->db);
		$order->initAsSpecimen();

		// Initialise parametres
		$this->id=0;
		$this->ref = 'SPECIMEN';
		$this->specimen=1;
		$this->statut               = 1;
		if ($conf->livraison_bon->enabled)
		{
			$this->livraison_id     = 0;
		}
		$this->date                 = time();
		$this->entrepot_id          = 0;
		$this->fk_delivery_address  = 0;
		$this->socid                = 1;

		$this->commande_id          = 0;
		$this->commande             = $order;

        $this->origin_id            = 1;
        $this->origin               = 'commande';

		$nbp = 5;
		$xnbp = 0;
		while ($xnbp < $nbp)
		{
			$line=new ExpeditionLigne($this->db);
			$line->desc=$langs->trans("Description")." ".$xnbp;
			$line->libelle=$langs->trans("Description")." ".$xnbp;
			$line->qty=10;
			$line->qty_asked=5;
			$line->qty_shipped=4;
			$line->fk_product=$this->commande->lines[$xnbp]->fk_product;

			$this->lines[]=$line;
			$xnbp++;
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
		if ($user->rights->expedition->creer)
		{
			$sql = "UPDATE ".MAIN_DB_PREFIX."expedition";
			$sql.= " SET date_delivery = ".($date_livraison ? "'".$this->db->idate($date_livraison)."'" : 'null');
			$sql.= " WHERE rowid = ".$this->id;

			dol_syslog("Expedition::set_date_livraison sql=".$sql,LOG_DEBUG);
			$resql=$this->db->query($sql);
			if ($resql)
			{
				$this->date_delivery = $date_livraison;
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
	 *	\brief	Fetch deliveries method and return an array. Load array this->meths(rowid=>label).
	 */
	function fetch_delivery_methods()
	{
		global $langs;
		$meths = array();

		$sql = "SELECT em.rowid, em.code, em.libelle";
		$sql.= " FROM ".MAIN_DB_PREFIX."c_shipment_mode as em";
		$sql.= " WHERE em.active = 1";
		$sql.= " ORDER BY em.libelle ASC";

		$resql = $this->db->query($sql);
		if ($resql)
		{
			while ($obj = $this->db->fetch_object($resql))
			{
				$label=$langs->trans('SendingMethod'.$obj->code);
				$this->meths[$obj->rowid] = ($label != 'SendingMethod'.$obj->code?$label:$obj->libelle);
			}
		}
	}

	/**
	 *	Get tracking url status
	 */
	function GetUrlTrackingStatus()
	{
		$sql = "SELECT em.code";
		$sql.= " FROM ".MAIN_DB_PREFIX."c_shipment_mode as em";
		$sql.= " WHERE em.rowid = ".$this->expedition_method_id;

		$resql = $this->db->query($sql);
		if ($resql)
		{
			if ($obj = $this->db->fetch_object($resql))
			{
				$code = $obj->code;
			}
		}

		if ($code)
		{
			$classname = "methode_expedition_".strtolower($code);

			$url='';
			if (file_exists(DOL_DOCUMENT_ROOT."/includes/modules/expedition/methode_expedition_".strtolower($code).".modules.php"))
			{
				require_once(DOL_DOCUMENT_ROOT."/includes/modules/expedition/methode_expedition_".strtolower($code).".modules.php");
				$obj = new $classname();
				$url = $obj->provider_url_status($this->tracking_number);
			}

			if ($url)
			{
				$this->tracking_url = sprintf('<a target="_blank" href="%s">url</a>',$url,$url);
			}
			else
			{
				$this->tracking_url = '';
			}
		}
		else
		{
			$this->tracking_url = '';
		}
	}
}


/**
 *  \class      ExpeditionLigne
 *  \brief      Classe de gestion des lignes de bons d'expedition
 */
class ExpeditionLigne
{
	var $db;

	// From llx_expeditiondet
	var $qty;
	var $qty_shipped;
	var $fk_product;

	// From llx_commandedet or llx_propaldet
	var $qty_asked;
	var $libelle;       // Label produit
	var $product_desc;  // Description produit
	var $ref;


	function ExpeditionLigne($DB)
	{
		$this->db=$DB;
	}

}

?>
