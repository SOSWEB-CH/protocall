<?php
/* Copyright (C) 2008-2009 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis@dolibarr.fr>
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
 *	\file			htdocs/lib/admin.lib.php
 *  \brief			Library of admin functions
 *  \version		$Id: admin.lib.php,v 1.82.2.2 2011/03/30 19:37:34 eldy Exp $
 */


/**
 *  \brief      Renvoi une version en chaine depuis une version en tableau
 *  \param	   versionarray        Tableau de version (vermajeur,vermineur,autre)
 *  \return     string              Chaine version
 */
function versiontostring($versionarray)
{
	$string='?';
	if (isset($versionarray[0])) $string=$versionarray[0];
	if (isset($versionarray[1])) $string.='.'.$versionarray[1];
	if (isset($versionarray[2])) $string.='.'.$versionarray[2];
	return $string;
}

/**
 *	Compare 2 versions (stored into 2 arrays)
 *	@param      versionarray1       Array of version (vermajor,verminor,patch)
 *	@param      versionarray2       Array of version (vermajor,verminor,patch)
 *	@return     int                 -4,-3,-2,-1 if versionarray1<versionarray2 (value depends on level of difference)
 * 									0 if same
 * 									1,2,3,4 if versionarray1>versionarray2 (value depends on level of difference)
 */
function versioncompare($versionarray1,$versionarray2)
{
	$ret=0;
	$level=0;
	while ($level < max(sizeof($versionarray1),sizeof($versionarray2)))
	{
		$operande1=isset($versionarray1[$level])?$versionarray1[$level]:0;
		$operande2=isset($versionarray2[$level])?$versionarray2[$level]:0;
		if (preg_match('/alpha|dev/i',$operande1)) $operande1=-3;
		if (preg_match('/alpha|dev/i',$operande2)) $operande2=-3;
		if (preg_match('/beta/i',$operande1)) $operande1=-2;
		if (preg_match('/beta/i',$operande2)) $operande2=-2;
		if (preg_match('/rc/i',$operande1)) $operande1=-1;
		if (preg_match('/rc/i',$operande2)) $operande2=-1;
		$level++;
		//print 'level '.$level.' '.$operande1.'-'.$operande2.'<br>';
		if ($operande1 < $operande2) { $ret = -$level; break; }
		if ($operande1 > $operande2) { $ret = $level; break; }
	}
	//print join('.',$versionarray1).'('.sizeof($versionarray1).') / '.join('.',$versionarray2).'('.sizeof($versionarray2).') => '.$ret;
	return $ret;
}


/**
 *	Return version PHP
 *	@return     array               Tableau de version (vermajeur,vermineur,autre)
 */
function versionphparray()
{
	return explode('.',PHP_VERSION);
}

/**
 *	\brief      Return version Dolibarr
 *	\return     array               Tableau de version (vermajeur,vermineur,autre)
 */
function versiondolibarrarray($fortest=0)
{
	return explode('.',DOL_VERSION);
}


/**
 *	Launch a sql file. Function used by:
 *  - Migrate process (dolibarr-xyz-abc.sql)
 *  - Loading sql menus (auguria)
 *  - Running specific Sql by a module init
 *  Install process however does not use it.
 *  Note that Sql files must have all comments at start of line.
 *	@param		sqlfile			Full path to sql file
 * 	@param		silent			1=Do not output anything, 0=Output line for update page
 * 	@param		entity			Entity targeted for multicompany module
 *	@param		usesavepoint	1=Run a savepoint before each request and a rollback to savepoint if error (this allow to have some request with errors inside global transactions).
 *	@param		handler			Handler targeted for menu
 * 	@return		int				<=0 if KO, >0 if OK
 */
function run_sql($sqlfile,$silent=1,$entity='',$usesavepoint=1,$handler='')
{
	global $db, $conf, $langs, $user;

	dol_syslog("Admin.lib::run_sql run sql file ".$sqlfile, LOG_DEBUG);

	$ok=0;
	$error=0;
	$i=0;
	$buffer = '';
	$arraysql = Array();

	// Get version of database
	$versionarray=$db->getVersionArray();

	$fp = fopen($sqlfile,"r");
	if ($fp)
	{
		while (!feof ($fp))
		{
			$buf = fgets($fp, 4096);

			// Cas special de lignes autorisees pour certaines versions uniquement
			if (preg_match('/^--\sV([0-9\.]+)/i',$buf,$reg))
			{
				$versioncommande=explode('.',$reg[1]);
				//print var_dump($versioncommande);
				//print var_dump($versionarray);
				if (sizeof($versioncommande) && sizeof($versionarray)
				&& versioncompare($versioncommande,$versionarray) <= 0)
				{
					// Version qualified, delete SQL comments
					$buf=preg_replace('/^--\sV([0-9\.]+)/i','',$buf);
					//print "Ligne $i qualifi?e par version: ".$buf.'<br>';
				}
			}

			// Add line buf to buffer if not a comment
			if (! preg_match('/^--/',$buf))
			{
			    $buffer .= trim($buf);
			}

			//          print $buf.'<br>';

			if (preg_match('/;/',$buffer))	// If string contains ';', it's end of a request string, we save it in arraysql.
			{
				// Found new request
				if ($buffer) $arraysql[$i]=$buffer;
				$i++;
				$buffer='';
			}
		}

		if ($buffer) $arraysql[$i]=$buffer;
		fclose($fp);
	}
	else
	{
		dol_syslog("Admin.lib::run_sql failed to open file ".$sqlfile, LOG_ERR);
	}

	// Loop on each request to see if there is a __+MAX_table__ key
	$listofmaxrowid=array();	// This is a cache table
	foreach($arraysql as $i => $sql)
	{
		$newsql=$sql;

		// Replace __+MAX_table__ with max of table
		while (preg_match('/__\+MAX_([A-Za-z_]+)__/i',$newsql,$reg))
		{
			$table=$reg[1];
			if (! isset($listofmaxrowid[$table]))
			{
				//var_dump($db);
				$sqlgetrowid='SELECT MAX(rowid) as max from '.$table;
				$resql=$db->query($sqlgetrowid);
				if ($resql)
				{
					$obj=$db->fetch_object($resql);
					$listofmaxrowid[$table]=$obj->max;
					if (empty($listofmaxrowid[$table])) $listofmaxrowid[$table]=0;
				}
				else
				{
					dol_syslog('Admin.lib::run_sql Failed to get max rowid for '.$table.' '.$db->lasterror().' sql='.$sqlgetrowid, LOG_ERR);
					if (! $silent) print '<tr><td valign="top" colspan="2">';
					if (! $silent) print '<div class="error">'.$langs->trans("Failed to get max rowid for ".$table)."</div></td>";
					if (! $silent) print '</tr>';
					$error++;
					break;
				}
			}
			$from='__+MAX_'.$table.'__';
			$to='+'.$listofmaxrowid[$table];
			$newsql=str_replace($from,$to,$newsql);
			dol_syslog('Admin.lib::run_sql New Request '.($i+1).' (replacing '.$from.' to '.$to.') sql='.$newsql, LOG_DEBUG);

			$arraysql[$i]=$newsql;
		}
	}

	// Loop on each request to execute request
	$cursorinsert=0;
	$listofinsertedrowid=array();
	foreach($arraysql as $i => $sql)
	{
		if ($sql)
		{
			if (!empty($handler)) $sql=preg_replace('/__HANDLER__/i',"'".$handler."'",$sql);

			$newsql=preg_replace('/__ENTITY__/i',(!empty($entity)?$entity:$conf->entity),$sql);

			// Ajout trace sur requete (eventuellement a commenter si beaucoup de requetes)
			if (! $silent) print '<tr><td valign="top">'.$langs->trans("Request").' '.($i+1)." sql='".$newsql."'</td></tr>\n";
			dol_syslog('Admin.lib::run_sql Request '.($i+1).' sql='.$newsql, LOG_DEBUG);

			// Replace __x__ with rowid of insert nb x
			while (preg_match('/__([0-9]+)__/',$newsql,$reg))
			{
				$cursor=$reg[1];
				if (empty($listofinsertedrowid[$cursor]))
				{
					if (! $silent) print '<tr><td valign="top" colspan="2">';
					if (! $silent) print '<div class="error">'.$langs->trans("FileIsNotCorrect")."</div></td>";
					if (! $silent) print '</tr>';
					$error++;
					break;
				}
				$from='__'.$cursor.'__';
				$to=$listofinsertedrowid[$cursor];
				$newsql=str_replace($from,$to,$newsql);
				dol_syslog('Admin.lib::run_sql New Request '.($i+1).' (replacing '.$from.' to '.$to.') sql='.$newsql, LOG_DEBUG);
			}

			$result=$db->query($newsql,$usesavepoint);
			if ($result)
			{
				if (! $silent) print '<!-- Result = OK -->'."\n";

				if (preg_replace('/insert into ([^\s]+)/i',$newsql,$reg))
				{
					$cursorinsert++;

					// It's an insert
					$table=preg_replace('/([^a-zA-Z_]+)/i','',$reg[1]);
					$insertedrowid=$db->last_insert_id($table);
					$listofinsertedrowid[$cursorinsert]=$insertedrowid;
					dol_syslog('Admin.lib::run_sql Insert nb '.$cursorinsert.', done in table '.$table.', rowid is '.$listofinsertedrowid[$cursorinsert], LOG_DEBUG);
				}
				// 	          print '<td align="right">OK</td>';
			}
			else
			{
				$errno=$db->errno();
				if (! $silent) print '<!-- Result = '.$errno.' -->'."\n";

				$okerror=array( 'DB_ERROR_TABLE_ALREADY_EXISTS',
				'DB_ERROR_COLUMN_ALREADY_EXISTS',
				'DB_ERROR_KEY_NAME_ALREADY_EXISTS',
				'DB_ERROR_TABLE_OR_KEY_ALREADY_EXISTS',		// PgSql use same code for table and key already exist
				'DB_ERROR_RECORD_ALREADY_EXISTS',
				'DB_ERROR_NOSUCHTABLE',
				'DB_ERROR_NOSUCHFIELD',
				'DB_ERROR_NO_FOREIGN_KEY_TO_DROP',
				'DB_ERROR_NO_INDEX_TO_DROP',
				'DB_ERROR_CANNOT_CREATE',    		// Qd contrainte deja existante
				'DB_ERROR_CANT_DROP_PRIMARY_KEY',
				'DB_ERROR_PRIMARY_KEY_ALREADY_EXISTS'
				);
				if (in_array($errno,$okerror))
				{
					//if (! $silent) print $langs->trans("OK");
				}
				else
				{
					if (! $silent) print '<tr><td valign="top" colspan="2">';
					if (! $silent) print '<div class="error">'.$langs->trans("Error")." ".$db->errno().": ".$newsql."<br>".$db->error()."</div></td>";
					if (! $silent) print '</tr>'."\n";
					dol_syslog('Admin.lib::run_sql Request '.($i+1)." Error ".$db->errno()." ".$newsql."<br>".$db->error(), LOG_ERR);
					$error++;
				}
			}

			if (! $silent) print '</tr>'."\n";
		}
	}

	if ($error == 0)
	{
		if (! $silent) print '<tr><td>'.$langs->trans("ProcessMigrateScript").'</td>';
		if (! $silent) print '<td align="right">'.$langs->trans("OK").'</td></tr>'."\n";
		$ok = 1;
	}
	else
	{
		if (! $silent) print '<tr><td>'.$langs->trans("ProcessMigrateScript").'</td>';
		if (! $silent) print '<td align="right"><font class="error">'.$langs->trans("KO").'</font></td></tr>'."\n";
		$ok = 0;
	}

	return $ok;
}


/**
 *	\brief		Effacement d'une constante dans la base de donnees
 *	\sa			dolibarr_get_const, dolibarr_sel_const
 *	\param	    db          Handler d'acces base
 *	\param	    name		Name of constant or rowid of line
 *	\param	    entity		Multi company id, -1 for all entities
 *	\return     int         <0 if KO, >0 if OK
 */
function dolibarr_del_const($db, $name, $entity=1)
{
	global $conf;

	$sql = "DELETE FROM ".MAIN_DB_PREFIX."const";
	$sql.= " WHERE (".$db->decrypt('name')." = '".$db->escape($name)."'";
	if (is_numeric($name)) $sql.= " OR rowid = '".$db->escape($name)."'";
	$sql.= ")";
	if ($entity >= 0) $sql.= " AND entity = ".$entity;

	dol_syslog("admin.lib::dolibarr_del_const sql=".$sql);
	$resql=$db->query($sql);
	if ($resql)
	{
		$conf->global->$name='';
		return 1;
	}
	else
	{
		dol_print_error($db);
		return -1;
	}
}

/**
 *	\brief      Recupere une constante depuis la base de donnees.
 *	\sa			dolibarr_del_const, dolibarr_set_const
 *	\param	    db          Handler d'acces base
 *	\param	    name				Nom de la constante
 *	\param	    entity			Multi company id
 *	\return     string      Valeur de la constante
 */
function dolibarr_get_const($db, $name, $entity=1)
{
	global $conf;
	$value='';

	$sql = "SELECT ".$db->decrypt('value')." as value";
	$sql.= " FROM ".MAIN_DB_PREFIX."const";
	$sql.= " WHERE name = ".$db->encrypt($name,1);
	$sql.= " AND entity = ".$entity;

	dol_syslog("admin.lib::dolibarr_get_const sql=".$sql);
	$resql=$db->query($sql);
	if ($resql)
	{
		$obj=$db->fetch_object($resql);
		if ($obj) $value=$obj->value;
	}
	return $value;
}


/**
 *	\brief      Insert a parameter (key,value) into database.
 *	\sa			dolibarr_del_const, dolibarr_get_const
 *	\param	    db          Database handler
 *	\param	    name		Name of constant
 *	\param	    value		Value of constant
 *	\param	    type		Type of constante (chaine par defaut)
 *	\param	    visible	    Is constant visible in Setup->Other page (0 by default)
 *	\param	    note		Note on parameter
 *	\param	    entity		Multi company id (0 means all entities)
 *	\return     int         -1 if KO, 1 if OK
 */
function dolibarr_set_const($db, $name, $value, $type='chaine', $visible=0, $note='', $entity=1)
{
	global $conf;

	// Clean parameters
	$name=trim($name);

	// Check parameters
	if (empty($name))
	{
		dol_print_error($db,"Error: Call to function dolibarr_set_const with wrong parameters", LOG_ERR);
		exit;
	}

	//dol_syslog("dolibarr_set_const name=$name, value=$value type=$type, visible=$visible, note=$note entity=$entity");

	$db->begin();

	$sql = "DELETE FROM ".MAIN_DB_PREFIX."const";
	$sql.= " WHERE name = ".$db->encrypt($name,1);
	if ($entity > 0) $sql.= " AND entity = ".$entity;

	dol_syslog("admin.lib::dolibarr_set_const sql=".$sql, LOG_DEBUG);
	$resql=$db->query($sql);

	if (strcmp($value,''))	// true if different. Must work for $value='0' or $value=0
	{
		$sql = "INSERT INTO llx_const(name,value,type,visible,note,entity)";
		$sql.= " VALUES (";
		$sql.= $db->encrypt($name,1);
		$sql.= ", ".$db->encrypt($value,1);
		$sql.= ",'".$type."',".$visible.",'".$db->escape($note)."',".$entity.")";

		//print "sql".$value."-".pg_escape_string($value)."-".$sql;exit;
        //print "xx".$db->escape($value);
        //print $sql;exit;
		dol_syslog("admin.lib::dolibarr_set_const sql=".$sql, LOG_DEBUG);
		$resql=$db->query($sql);
	}

	if ($resql)
	{
		$db->commit();
		$conf->global->$name=$value;
		return 1;
	}
	else
	{
		$error=$db->lasterror();
		dol_syslog("admin.lib::dolibarr_set_const ".$error, LOG_ERR);
		$db->rollback();
		return -1;
	}
}


/**
 *  \brief      	Define head array for tabs of security setup pages
 *  \return			Array of head
 *  \version    	$Id: admin.lib.php,v 1.82.2.2 2011/03/30 19:37:34 eldy Exp $
 */
function security_prepare_head()
{
	global $langs, $conf, $user;
	$h = 0;
	$head = array();

	$head[$h][0] = DOL_URL_ROOT."/admin/perms.php";
	$head[$h][1] = $langs->trans("DefaultRights");
	$head[$h][2] = 'default';
	$h++;

	$head[$h][0] = DOL_URL_ROOT."/admin/security.php";
	$head[$h][1] = $langs->trans("Passwords");
	$head[$h][2] = 'passwords';
	$h++;

	$head[$h][0] = DOL_URL_ROOT."/admin/security_other.php";
	$head[$h][1] = $langs->trans("Miscellanous");
	$head[$h][2] = 'misc';
	$h++;

	$head[$h][0] = DOL_URL_ROOT."/admin/events.php";
	$head[$h][1] = $langs->trans("Audit");
	$head[$h][2] = 'audit';
	$h++;

	return $head;
}


/**
 * 	Return list of session
 *	@return		array			Array list of sessions
 */
function listOfSessions()
{
	global $conf;

	$arrayofSessions = array();
	$sessPath = ini_get("session.save_path").'/';
	dol_syslog('admin.lib:listOfSessions sessPath='.$sessPath);

	$dh = @opendir($sessPath);
	if ($dh)
	{
		while(($file = @readdir($dh)) !== false)
		{
			if (preg_match('/^sess_/i',$file) && $file != "." && $file != "..")
			{
				$fullpath = $sessPath.$file;
				if(! @is_dir($fullpath) && is_readable($fullpath))
				{
					$sessValues = file_get_contents($fullpath);	// get raw session data

					if (preg_match('/dol_login/i',$sessValues) && // limit to dolibarr session
						preg_match('/dol_entity\|s:([0-9]+):"('.$conf->entity.')"/i',$sessValues) && // limit to current entity
						preg_match('/dol_company\|s:([0-9]+):"('.$conf->global->MAIN_INFO_SOCIETE_NOM.')"/i',$sessValues)) // limit to company name
					{
						$tmp=explode('_', $file);
						$idsess=$tmp[1];
						$login = preg_match('/dol_login\|s:[0-9]+:"([A-Za-z0-9]+)"/i',$sessValues,$regs);
						$arrayofSessions[$idsess]["login"] = $regs[1];
						$arrayofSessions[$idsess]["age"] = time()-filectime( $fullpath );
						$arrayofSessions[$idsess]["creation"] = filectime( $fullpath );
						$arrayofSessions[$idsess]["modification"] = filemtime( $fullpath );
						$arrayofSessions[$idsess]["raw"] = $sessValues;
					}
				}
			}
		}
		@closedir($dh);
	}

	return $arrayofSessions;
}

/**
 * 	Purge existing sessions
 * 	@param		mysessionid		To avoid to try to delete my own session
 * 	@return		int		>0 if OK, <0 if KO
 */
function purgeSessions($mysessionid)
{
	global $conf;

	$arrayofSessions = array();
	$sessPath = ini_get("session.save_path")."/";
	dol_syslog('admin.lib:purgeSessions mysessionid='.$mysessionid.' sessPath='.$sessPath);

	$error=0;
	$dh = @opendir($sessPath);
	while(($file = @readdir($dh)) !== false)
	{
		if ($file != "." && $file != "..")
		{
			$fullpath = $sessPath.$file;
			if(! @is_dir($fullpath))
			{
				$sessValues = file_get_contents($fullpath);	// get raw session data

				if (preg_match('/dol_login/i',$sessValues) && // limit to dolibarr session
					preg_match('/dol_entity\|s:([0-9]+):"('.$conf->entity.')"/i',$sessValues) && // limit to current entity
					preg_match('/dol_company\|s:([0-9]+):"('.$conf->global->MAIN_INFO_SOCIETE_NOM.')"/i',$sessValues)) // limit to company name
				{
					$tmp=explode('_', $file);
					$idsess=$tmp[1];
					// We remove session if it's not ourself
					if ($idsess != $mysessionid)
					{
						$res=@unlink($fullpath);
						if (! $res) $error++;
					}
				}
			}
		}
	}
	@closedir($dh);

	if (! $error) return 1;
	else return -$error;
}



/**
 *  Enable a module
 *  @param      value       Nom du module a activer
 *  @param      withdeps    Active/desactive aussi les dependances
 *  @return     string      Error message or '';
 */
function Activate($value,$withdeps=1)
{
    global $db, $modules, $langs, $conf;

    $modName = $value;

    $ret='';

    // Activate module
    if ($modName)
    {
        $file = $modName . ".class.php";

        // Loop on each directory
        foreach ($conf->file->dol_document_root as $dol_document_root)
        {
            $dir = $dol_document_root."/includes/modules/";

        	$found=@include_once($dir.$file);
            if ($found) break;
        }

        $objMod = new $modName($db);

        // Test if PHP version ok
        $verphp=versionphparray();
        $vermin=$objMod->phpmin;
        if (is_array($vermin) && versioncompare($verphp,$vermin) < 0)
        {
            return $langs->trans("ErrorModuleRequirePHPVersion",versiontostring($vermin));
        }

        // Test if Dolibarr version ok
        $verdol=versiondolibarrarray();
        $vermin=$objMod->need_dolibarr_version;
        //print 'eee'.versioncompare($verdol,$vermin).join(',',$verdol).' - '.join(',',$vermin);exit;
        if (is_array($vermin) && versioncompare($verdol,$vermin) < 0)
        {
            return $langs->trans("ErrorModuleRequireDolibarrVersion",versiontostring($vermin));
        }

        // Test if javascript requirement ok
        if (! empty($objMod->need_javascript_ajax) && empty($conf->use_javascript_ajax))
        {
            return $langs->trans("ErrorModuleRequireJavascript");
        }

        $result=$objMod->init();
        if ($result <= 0) $ret=$objMod->error;
    }

    if (! $ret && $withdeps)
    {
        if (is_array($objMod->depends) && !empty($objMod->depends))
        {
            // Activation des modules dont le module depend
            for ($i = 0; $i < sizeof($objMod->depends); $i++)
            {
                if (file_exists(DOL_DOCUMENT_ROOT."/includes/modules/".$objMod->depends[$i].".class.php"))
                {
                    Activate($objMod->depends[$i]);
                }
            }
        }

        if (is_array($objMod->conflictwith) && !empty($objMod->conflictwith))
        {
            // Desactivation des modules qui entrent en conflit
            for ($i = 0; $i < sizeof($objMod->conflictwith); $i++)
            {
                if (file_exists(DOL_DOCUMENT_ROOT."/includes/modules/".$objMod->conflictwith[$i].".class.php"))
                {
                    UnActivate($objMod->conflictwith[$i],0);
                }
            }
        }
    }

    return $ret;
}


/**
 *  Disable a module
 *  @param      value               Nom du module a desactiver
 *  @param      requiredby          1=Desactive aussi modules dependants
 *  @return     string              Error message or '';
 */
function UnActivate($value,$requiredby=1)
{
    global $db, $modules, $conf;

    $modName = $value;

    $ret='';

    // Desactivation du module
    if ($modName)
    {
        $file = $modName . ".class.php";

        // Loop on each directory
        foreach ($conf->file->dol_document_root as $dol_document_root)
        {
            $dir = $dol_document_root."/includes/modules/";

        	$found=@include_once($dir.$file);
            if ($found) break;
        }

        if ($found)
        {
            $objMod = new $modName($db);
            $result=$objMod->remove();
        }
        else
        {
            $genericMod = new DolibarrModules($db);
            $genericMod->name=preg_replace('/^mod/i','',$modName);
            $genericMod->style_sheet=1;
            $genericMod->rights_class=strtolower(preg_replace('/^mod/i','',$modName));
            $genericMod->const_name='MAIN_MODULE_'.strtoupper(preg_replace('/^mod/i','',$modName));
            dol_syslog("modules::UnActivate Failed to find module file, we use generic function with name ".$genericMod->name);
            $genericMod->_remove();
        }
    }

    // Desactivation des modules qui dependent de lui
    if ($requiredby)
    {
        for ($i = 0; $i < sizeof($objMod->requiredby); $i++)
        {
            UnActivate($objMod->requiredby[$i]);
        }
    }

    return $ret;
}

?>