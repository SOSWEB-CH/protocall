<?php
/* Copyright (C) 2010 Regis Houssin       <regis@dolibarr.fr>
 * Copyright (C) 2010 Laurent Destailleur <eldy@users.sourceforge.net>
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
 * or see http://www.gnu.org/
 */

/**
 *	\file       htdocs/includes/modules/project/mod_project_simple.php
 *	\ingroup    project
 *	\brief      File with class to manage the numbering module Simple for project references
 *	\version    $Id: mod_project_simple.php,v 1.5 2010/06/01 07:31:43 eldy Exp $
 */

require_once(DOL_DOCUMENT_ROOT ."/includes/modules/project/modules_project.php");


/**
 * 	\class      mod_project_simple
 * 	\brief      Class to manage the numbering module Simple for project references
 */
class mod_project_simple extends ModeleNumRefProjects
{
	var $version='dolibarr';		// 'development', 'experimental', 'dolibarr'
	var $prefix='PJ';
    var $error='';
	var $nom = "Simple";


    /**     \brief      Return description of numbering module
     *      \return     string      Text with description
     */
    function info()
    {
    	global $langs;
      	return $langs->trans("SimpleNumRefModelDesc",$this->prefix);
    }


    /**     \brief      Return an example of numbering module values
     *      \return     string      Example
     */
    function getExample()
    {
        return $this->prefix."0501-0001";
    }


    /**     \brief      Test si les numeros deja en vigueur dans la base ne provoquent pas de
     *                  de conflits qui empechera cette numerotation de fonctionner.
     *      \return     boolean     false si conflit, true si ok
     */
    function canBeActivated()
    {
    	global $conf,$langs;

        $coyymm=''; $max='';

		$posindice=8;
		$sql = "SELECT MAX(SUBSTRING(ref FROM ".$posindice.")) as max";
        $sql.= " FROM ".MAIN_DB_PREFIX."projet";
		$sql.= " WHERE ref LIKE '".$this->prefix."____-%'";
        $sql.= " AND entity = ".$conf->entity;
        $resql=$db->query($sql);
        if ($resql)
        {
            $row = $db->fetch_row($resql);
            if ($row) { $coyymm = substr($row[0],0,6); $max=$row[0]; }
        }
        if (! $coyymm || preg_match('/'.$this->prefix.'[0-9][0-9][0-9][0-9]/i',$coyymm))
        {
            return true;
        }
        else
        {
			$langs->load("errors");
			$this->error=$langs->trans('ErrorNumRefModel',$max);
            return false;
        }
    }


   /**
	*  \brief      Return next value
	*  \param      objsoc		Object third party
	*  \param      project		Object project
	*  \return     string		Value if OK, 0 if KO
	*/
    function getNextValue($objsoc=0,$project='')
    {
		global $db,$conf;

		// D'abord on recupere la valeur max
		$posindice=8;
		$sql = "SELECT MAX(SUBSTRING(ref FROM ".$posindice.")) as max";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet";
		$sql.= " WHERE ref like '".$this->prefix."____-%'";
		$sql.= " AND entity = ".$conf->entity;

		$resql=$db->query($sql);
		if ($resql)
		{
			$obj = $db->fetch_object($resql);
			if ($obj) $max = intval($obj->max);
			else $max=0;
		}
		else
		{
			dol_syslog("mod_project_simple::getNextValue sql=".$sql);
			return -1;
		}

		$date=empty($project->date_c)?dol_now():$project->date_c;

		//$yymm = strftime("%y%m",time());
		$yymm = strftime("%y%m",$date);
		$num = sprintf("%04s",$max+1);

		dol_syslog("mod_project_simple::getNextValue return ".$this->prefix.$yymm."-".$num);
		return $this->prefix.$yymm."-".$num;
    }


    /**     \brief      Return next reference not yet used as a reference
     *      \param      objsoc      Object third party
     *      \param      project		Object project
     *      \return     string      Next not used reference
     */
    function project_get_num($objsoc=0,$project='')
    {
        return $this->getNextValue($objsoc,$project);
    }
}

?>