<?php
/* Copyright (C) 2003,2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2003      Jean-Louis Bergamo   <jlb@j1b.org>
 * Copyright (C) 2004-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Sebastien Di Cintio  <sdicintio@ressource-toi.org>
 * Copyright (C) 2004      Benoit Mortier       <benoit.mortier@opensides.be>
 * Copyright (C) 2009      Regis Houssin        <regis@dolibarr.fr>
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
 *		\defgroup   agenda     Module agenda
 *      \brief      Module pour gerer l'agenda et actions
 *		\brief		$Id: modAgenda.class.php,v 1.34.2.1 2011/02/02 16:45:12 eldy Exp $
 */

/**
 *      \file       htdocs/includes/modules/modAgenda.class.php
 *      \ingroup    agenda
 *      \brief      Fichier de description et activation du module agenda
 */
include_once(DOL_DOCUMENT_ROOT ."/includes/modules/DolibarrModules.class.php");

/**
 *      \class      modAgenda
 *      \brief      Classe de description et activation du module Adherent
 */
class modAgenda extends DolibarrModules
{

	/**
	 *   \brief      Constructeur. Definit les noms, constantes et boites
	 *   \param      DB      handler d'acces base
	 */
	function modAgenda($DB)
	{
		$this->db = $DB;
		$this->numero = 2400;

		$this->family = "projects";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i','',get_class($this));
		$this->description = "Gestion de l'agenda et des actions";
		$this->version = 'dolibarr';                        // 'experimental' or 'dolibarr' or version
		// Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->special = 0;
		$this->picto='calendar';

		// Data directories to create when module is enabled
		$this->dirs = array("/agenda/temp");

		// Config pages
		//-------------
		$this->config_page_url = array("agenda.php");

		// Dependancies
		//-------------
		$this->depends = array();
		$this->requiredby = array();
		$this->langfiles = array("companies");

		// Constantes
		//-----------
		$this->const = array();

		// New pages on tabs
		// -----------------
		$this->tabs = array();

		// Boxes
		//------
		$this->boxes = array();
		$this->boxes[0][1] = "box_actions.php";

		// Permissions
		//------------
		$this->rights = array();
		$this->rights_class = 'agenda';
		$r=0;

		// $this->rights[$r][0]     Id permission (unique tous modules confondus)
		// $this->rights[$r][1]     Libelle par defaut si traduction de cle "PermissionXXX" non trouvee (XXX = Id permission)
		// $this->rights[$r][2]     Non utilise
		// $this->rights[$r][3]     1=Permis par defaut, 0=Non permis par defaut
		// $this->rights[$r][4]     Niveau 1 pour nommer permission dans code
		// $this->rights[$r][5]     Niveau 2 pour nommer permission dans code
		// $r++;

		$this->rights[$r][0] = 2401;
		$this->rights[$r][1] = 'Read actions/tasks linked to his account';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'myactions';
		$this->rights[$r][5] = 'read';
		$r++;

		$this->rights[$r][0] = 2402;
		$this->rights[$r][1] = 'Create/modify actions/tasks linked to his account';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'myactions';
		$this->rights[$r][5] = 'create';
		$r++;

		$this->rights[$r][0] = 2403;
		$this->rights[$r][1] = 'Delete actions/tasks linked to his account';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'myactions';
		$this->rights[$r][5] = 'delete';
		$r++;

		$this->rights[$r][0] = 2411;
		$this->rights[$r][1] = 'Read actions/tasks of others';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'allactions';
		$this->rights[$r][5] = 'read';
		$r++;

		$this->rights[$r][0] = 2412;
		$this->rights[$r][1] = 'Create/modify actions/tasks of others';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'allactions';
		$this->rights[$r][5] = 'create';
		$r++;

		$this->rights[$r][0] = 2413;
		$this->rights[$r][1] = 'Delete actions/tasks of others';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'allactions';
		$this->rights[$r][5] = 'delete';
		$r++;

		// Main menu entries
		$this->menu = array();			// List of menus to add
		$r=0;

		// Add here entries to declare new menus
		// Example to declare the Top Menu entry:
		// $this->menu[$r]=array(	'fk_menu'=>0,			// Put 0 if this is a top menu
		//							'type'=>'top',			// This is a Top menu entry
		//							'titre'=>'MyModule top menu',
		//							'mainmenu'=>'mymodule',
		//							'leftmenu'=>'1',		// Use 1 if you also want to add left menu entries using this descriptor.
		//							'url'=>'/mymodule/pagetop.php',
		//							'langs'=>'mylangfile',	// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
		//							'position'=>100,
		//							'enabled'=>'1',			// Define condition to show or hide menu entry. Use '$conf->mymodule->enabled' if entry must be visible if module is enabled.
		//							'perms'=>'1',			// Use 'perms'=>'$user->rights->mymodule->level1->level2' if you want your menu with a permission rules
		//							'target'=>'',
		//							'user'=>2);				// 0=Menu for internal users, 1=external users, 2=both
		// $r++;
		$this->menu[$r]=array('fk_menu'=>0,
													'type'=>'top',
													'titre'=>'Agenda',
													'mainmenu'=>'agenda',
													'leftmenu'=>'1',		// Use 1 if you also want to add left menu entries using this descriptor.
													'url'=>'/comm/action/index.php',
													'langs'=>'agenda',
													'position'=>100,
													'perms'=>'$user->rights->agenda->myactions->read',
													'enabled'=>'$conf->agenda->enabled',
													'target'=>'',
													'user'=>2);
		$r++;

		$this->menu[$r]=array('fk_menu'=>'r=0',
													'type'=>'left',
													'titre'=>'Actions',
													'mainmenu'=>'agenda',
													'url'=>'/comm/action/index.php?mainmenu=agenda&amp;leftmenu=agenda',
													'langs'=>'agenda',
													'position'=>100,
													'perms'=>'$user->rights->agenda->myactions->read',
													'enabled'=>'$conf->agenda->enabled',
													'target'=>'',
													'user'=>2);
		$r++;
		$this->menu[$r]=array('fk_menu'=>'r=1',
													'type'=>'left',
													'titre'=>'NewAction',
													'mainmenu'=>'agenda',
													'url'=>'/comm/action/fiche.php?mainmenu=agenda&amp;leftmenu=agenda&amp;action=create',
													'langs'=>'commercial',
													'position'=>101,
													'perms'=>'($user->rights->agenda->myactions->create||$user->rights->agenda->allactions->create)',
													'enabled'=>'$conf->agenda->enabled',
													'target'=>'',
													'user'=>2);
		$r++;
		// Calendar
		$this->menu[$r]=array('fk_menu'=>'r=1',
													'type'=>'left',
													'titre'=>'Calendar',
													'mainmenu'=>'agenda',
													'url'=>'/comm/action/index.php?mainmenu=agenda&amp;leftmenu=agenda',
													'langs'=>'agenda',
													'position'=>102,
													'perms'=>'$user->rights->agenda->myactions->read',
													'enabled'=>'$conf->agenda->enabled',
													'target'=>'',
													'user'=>2);
		$r++;
		$this->menu[$r]=array('fk_menu'=>'r=3',
													'type'=>'left',
													'titre'=>'MenuToDoMyActions',
													'mainmenu'=>'agenda',
													'url'=>'/comm/action/index.php?mainmenu=agenda&amp;leftmenu=agenda&amp;status=todo&amp;filter=mine',
													'langs'=>'agenda',
													'position'=>103,
													'perms'=>'$user->rights->agenda->myactions->read',
													'enabled'=>'$conf->agenda->enabled',
													'target'=>'',
													'user'=>2);
		$r++;
		$this->menu[$r]=array('fk_menu'=>'r=3',
													'type'=>'left',
													'titre'=>'MenuDoneMyActions',
													'mainmenu'=>'agenda',
													'url'=>'/comm/action/index.php?mainmenu=agenda&amp;leftmenu=agenda&amp;status=done&amp;filter=mine',
													'langs'=>'agenda',
													'position'=>104,
													'perms'=>'$user->rights->agenda->myactions->read',
													'enabled'=>'$conf->agenda->enabled',
													'target'=>'',
													'user'=>2);
		$r++;
		$this->menu[$r]=array('fk_menu'=>'r=3',
													'type'=>'left',
													'titre'=>'MenuToDoActions',
													'mainmenu'=>'agenda',
													'url'=>'/comm/action/index.php?mainmenu=agenda&amp;leftmenu=agenda&amp;status=todo',
													'langs'=>'agenda',
													'position'=>105,
													'perms'=>'$user->rights->agenda->allactions->read',
													'enabled'=>'$user->rights->agenda->allactions->read',
													'target'=>'',
													'user'=>2);
		$r++;
		$this->menu[$r]=array('fk_menu'=>'r=3',
													'type'=>'left',
													'titre'=>'MenuDoneActions',
													'mainmenu'=>'agenda',
													'url'=>'/comm/action/index.php?mainmenu=agenda&amp;leftmenu=agenda&amp;status=done',
													'langs'=>'agenda',
													'position'=>106,
													'perms'=>'$user->rights->agenda->allactions->read',
													'enabled'=>'$user->rights->agenda->allactions->read',
													'target'=>'',
													'user'=>2);
		$r++;
		// List
		$this->menu[$r]=array('fk_menu'=>'r=1',
													'type'=>'left',
													'titre'=>'List',
													'mainmenu'=>'agenda',
													'url'=>'/comm/action/listactions.php?mainmenu=agenda&amp;leftmenu=agenda',
													'langs'=>'agenda',
													'position'=>112,
													'perms'=>'$user->rights->agenda->myactions->read',
													'enabled'=>'$conf->agenda->enabled',
													'target'=>'',
													'user'=>2);
		$r++;
		$this->menu[$r]=array('fk_menu'=>'r=8',
													'type'=>'left',
													'titre'=>'MenuToDoMyActions',
													'mainmenu'=>'agenda',
													'url'=>'/comm/action/listactions.php?mainmenu=agenda&amp;leftmenu=agenda&amp;status=todo&amp;filter=mine',
													'langs'=>'agenda',
													'position'=>113,
													'perms'=>'$user->rights->agenda->myactions->read',
													'enabled'=>'$conf->agenda->enabled',
													'target'=>'',
													'user'=>2);
		$r++;
		$this->menu[$r]=array('fk_menu'=>'r=8',
													'type'=>'left',
													'titre'=>'MenuDoneMyActions',
													'mainmenu'=>'agenda',
													'url'=>'/comm/action/listactions.php?mainmenu=agenda&amp;leftmenu=agenda&amp;status=done&amp;filter=mine',
													'langs'=>'agenda',
													'position'=>114,
													'perms'=>'$user->rights->agenda->myactions->read',
													'enabled'=>'$conf->agenda->enabled',
													'target'=>'',
													'user'=>2);
		$r++;
		$this->menu[$r]=array('fk_menu'=>'r=8',
													'type'=>'left',
													'titre'=>'MenuToDoActions',
													'mainmenu'=>'agenda',
													'url'=>'/comm/action/listactions.php?mainmenu=agenda&amp;leftmenu=agenda&amp;status=todo',
													'langs'=>'agenda',
													'position'=>115,
													'perms'=>'$user->rights->agenda->allactions->read',
													'enabled'=>'$user->rights->agenda->allactions->read',
													'target'=>'',
													'user'=>2);
		$r++;
		$this->menu[$r]=array('fk_menu'=>'r=8',
													'type'=>'left',
													'titre'=>'MenuDoneActions',
													'mainmenu'=>'agenda',
													'url'=>'/comm/action/listactions.php?mainmenu=agenda&amp;leftmenu=agenda&amp;status=done',
													'langs'=>'agenda',
													'position'=>116,
													'perms'=>'$user->rights->agenda->allactions->read',
													'enabled'=>'$user->rights->agenda->allactions->read',
													'target'=>'',
													'user'=>2);
		$r++;
		// Reports
		$this->menu[$r]=array('fk_menu'=>'r=1',
													'type'=>'left',
													'titre'=>'Reportings',
													'mainmenu'=>'agenda',
													'url'=>'/comm/action/rapport/index.php?mainmenu=agenda&amp;leftmenu=agenda',
													'langs'=>'agenda',
													'position'=>120,
													'perms'=>'$user->rights->agenda->allactions->read',
													'enabled'=>'$conf->agenda->enabled',
													'target'=>'',
													'user'=>2);
		$r++;


		// Exports
		//--------
		$r=0;

		// $this->export_code[$r]          Code unique identifiant l'export (tous modules confondus)
		// $this->export_label[$r]         Libelle par defaut si traduction de cle "ExportXXX" non trouvee (XXX = Code)
		// $this->export_permission[$r]    Liste des codes permissions requis pour faire l'export
		// $this->export_fields_sql[$r]    Liste des champs exportables en codif sql
		// $this->export_fields_name[$r]   Liste des champs exportables en codif traduction
		// $this->export_sql[$r]           Requete sql qui offre les donnees a l'export
	}


	/**
	 *   \brief      Fonction appelee lors de l'activation du module. Insere en base les constantes, boites, permissions du module.
	 *               Definit egalement les repertoires de donnees a creer pour ce module.
	 *	\param		options		Options when enabling module
	 */
	function init($options='')
	{
		// Prevent pb of modules not correctly disabled
		//$this->remove($options);

		$sql = array();

		return $this->_init($sql,$options);
	}

	/**
	 *	\brief      Fonction appelee lors de la desactivation d'un module.
	 *              Supprime de la base les constantes, boites et permissions du module.
	 *	\param		options		Options when disabling module
	 */
	function remove($options='')
	{
		$sql = array();

		return $this->_remove($sql,$options);
	}

}
?>
