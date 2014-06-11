<?php
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2009 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2010-2011 Juanjo Menent        <jmenent@2byte.es>
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
 *
 * $Id: ligne-prelevement.class.php,v 1.5 2011/01/05 09:57:55 simnandez Exp $
 */

/*
 \file       htdocs/compta/prelevement/ligne-prelevement.class.php
 \ingroup    prelevement
 \brief      Fichier de la classe des lignes de prelevements
 \version    $Revision: 1.5 $
 */


/**
 *       \class      LignePrelevement
 *       \brief      Classe permettant la gestion des prelevements
 */

class LignePrelevement
{
	var $id;
	var $db;

	var $statuts = array();


	/**
	 *    \brief      Constructeur de la classe
	 *    \param      DB          Handler acces base de donnees
	 *    \param      user        Objet user
	 */
	function LignePrelevement($DB, $user)
	{
		global $conf,$langs;
		
		$this->db = $DB ;
		$this->user = $user;

		// List of language codes for status
		
		$langs->load("withdrawals");
		$this->statuts[0]=$langs->trans("StatusWaiting");
		$this->statuts[2]=$langs->trans("StatusCredited");
		$this->statuts[3]=$langs->trans("StatusRefused");
	}

	/**
	 *    \brief      Recupere l'objet prelevement
	 *    \param      rowid       id de la facture a recuperer
	 */
	function fetch($rowid)
	{
		global $conf;

		$result = 0;

		$sql = "SELECT pl.rowid, pl.amount, p.ref, p.rowid as bon_rowid";
		$sql.= ", pl.statut, pl.fk_soc";
		$sql.= " FROM ".MAIN_DB_PREFIX."prelevement_lignes as pl";
		$sql.= ", ".MAIN_DB_PREFIX."prelevement_bons as p";
		$sql.= " WHERE pl.rowid=".$rowid;
		$sql.= " AND p.rowid = pl.fk_prelevement_bons";
		$sql.= " AND p.entity = ".$conf->entity;

		$resql=$this->db->query($sql);
		if ($resql)
		{
			if ($this->db->num_rows($resql))
			{
				$obj = $this->db->fetch_object($resql);

				$this->id              = $obj->rowid;
				$this->amount          = $obj->amount;
				$this->socid           = $obj->fk_soc;
				$this->statut          = $obj->statut;
				$this->bon_ref         = $obj->ref;
				$this->bon_rowid       = $obj->bon_rowid;
			}
			else
			{
				$result++;
				dol_syslog("LignePrelevement::Fetch rowid=$rowid numrows=0");
			}

			$this->db->free($resql);
		}
		else
		{
			$result++;
			dol_syslog("LignePrelevement::Fetch rowid=$rowid");
			dol_syslog($this->db->error());
		}

		return $result;
	}
	
/**
	 *    Return status label of object
	 *    @param      mode        0=Label, 1=Picto + label, 2=Picto, 3=Label + Picto
	 * 	  @return     string      Label
	 */
	function getLibStatut($mode=0)
	{
		return $this->LibStatut($this->statut,$mode);
	}

	/**
	 *    Return status label for a status
	 *    @param      statut      id statut
	 *    @param      mode        0=Label, 1=Picto + label, 2=Picto, 3=Label + Picto
	 * 	  @return     string      Label
	 */
	function LibStatut($statut,$mode=0)
	{
		global $langs;

		if ($mode == 0)
		{
			return $langs->trans($this->statuts[$statut]);
		}
		
		if ($mode == 1)
		{
			if ($statut==0) return img_picto($langs->trans($this->statuts[$statut]),'statut0').' '.$langs->trans($this->statuts[$statut]);
			if ($statut==2) return img_picto($langs->trans($this->statuts[$statut]),'statut4').' '.$langs->trans($this->statuts[$statut]);
			if ($statut==3) return img_picto($langs->trans($this->statuts[$statut]),'statut8').' '.$langs->trans($this->statuts[$statut]);
		}
		if ($mode == 2)
		{
			if ($statut==0) return img_picto($langs->trans($this->statuts[$statut]),'statut0');
			if ($statut==2) return img_picto($langs->trans($this->statuts[$statut]),'statut4');
			if ($statut==3) return img_picto($langs->trans($this->statuts[$statut]),'statut8');
		}
		
		if ($mode == 3)
		{
			if ($statut==0) return $langs->trans($this->statuts[$statut]).' '.img_picto($langs->trans($this->statuts[$statut]),'statut0');
			if ($statut==2) return $langs->trans($this->statuts[$statut]).' '.img_picto($langs->trans($this->statuts[$statut]),'statut4');
			if ($statut==3) return $langs->trans($this->statuts[$statut]).' '.img_picto($langs->trans($this->statuts[$statut]),'statut8');
		}
	}
}

?>
