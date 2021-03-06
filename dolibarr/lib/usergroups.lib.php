<?php
/* Copyright (C) 2006-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2010      Regis Houssin		<regis@dolibarr.fr>
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
 *	    \file       htdocs/lib/usergroups.lib.php
 *		\brief      Ensemble de fonctions de base pour la gestion des utilisaterus et groupes
 *		\version    $Id: usergroups.lib.php,v 1.24.2.1 2011/02/10 18:55:23 eldy Exp $
 */
function user_prepare_head($object)
{
	global $langs, $conf, $user;

	$langs->load("users");

	$canreadperms=true;
	if (! empty($conf->global->MAIN_USE_ADVANCED_PERMS))
	{
		$canreadperms=($user->admin || ($user->id != $object->id && $user->rights->user->user_advance->readperms) || ($user->id == $object->id && $user->rights->user->self_advance->readperms));
	}

	$h = 0;
	$head = array();

    $head[$h][0] = DOL_URL_ROOT.'/user/fiche.php?id='.$object->id;
    $head[$h][1] = $langs->trans("UserCard");
    $head[$h][2] = 'user';
    $h++;

	if ($conf->ldap->enabled && $conf->global->LDAP_SYNCHRO_ACTIVE)
	{
		$langs->load("ldap");
	    $head[$h][0] = DOL_URL_ROOT.'/user/ldap.php?id='.$object->id;
	    $head[$h][1] = $langs->trans("LDAPCard");
	    $head[$h][2] = 'ldap';
	    $h++;
	}

	if ($canreadperms)
	{
		$head[$h][0] = DOL_URL_ROOT.'/user/perms.php?id='.$object->id;
		$head[$h][1] = $langs->trans("UserRights");
		$head[$h][2] = 'rights';
		$h++;
	}

    $head[$h][0] = DOL_URL_ROOT.'/user/param_ihm.php?id='.$object->id;
    $head[$h][1] = $langs->trans("UserGUISetup");
    $head[$h][2] = 'guisetup';
    $h++;

    if ($conf->clicktodial->enabled)
    {
        $head[$h][0] = DOL_URL_ROOT.'/user/clicktodial.php?id='.$object->id;
        $head[$h][1] = $langs->trans("ClickToDial");
	    $head[$h][2] = 'clicktodial';
        $h++;
    }

    if (! $user->societe_id)
    {
    	$head[$h][0] = DOL_URL_ROOT.'/user/note.php?id='.$object->id;
    	$head[$h][1] = $langs->trans("Note");
    	$head[$h][2] = 'note';
    	$h++;

    	$head[$h][0] = DOL_URL_ROOT.'/user/info.php?id='.$object->id;
    	$head[$h][1] = $langs->trans("Info");
    	$head[$h][2] = 'info';
    	$h++;
    }

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	// $this->tabs = array('entity:MyModule:@mymodule:/mymodule/mypage.php?id=__ID__');
	if (is_array($conf->tabs_modules['user']))
	{
		$i=0;
		foreach ($conf->tabs_modules['user'] as $value)
		{
			$values=explode(':',$value);
			if ($values[2]) $langs->load($values[2]);
			$head[$h][0] = dol_buildpath(preg_replace('/__ID__/i',$object->id,$values[3]),1);
			$head[$h][1] = $langs->trans($values[1]);
			$head[$h][2] = 'tab'.$values[1];
			$h++;
		}
	}

	return $head;
}


function group_prepare_head($object)
{
	global $langs, $conf, $user;

	$canreadperms=true;
	if (! empty($conf->global->MAIN_USE_ADVANCED_PERMS))
	{
		$canreadperms=($user->admin || $user->rights->user->group_advance->readperms);
	}

	$h = 0;
	$head = array();

    $head[$h][0] = DOL_URL_ROOT.'/user/group/fiche.php?id='.$object->id;
    $head[$h][1] = $langs->trans("GroupCard");
    $head[$h][2] = 'group';
    $h++;

	if ($conf->ldap->enabled && $conf->global->LDAP_SYNCHRO_ACTIVE)
	{
		$langs->load("ldap");
	    $head[$h][0] = DOL_URL_ROOT.'/user/group/ldap.php?id='.$object->id;
	    $head[$h][1] = $langs->trans("LDAPCard");
	    $head[$h][2] = 'ldap';
	    $h++;
	}

	if ($canreadperms)
	{
		$head[$h][0] = DOL_URL_ROOT.'/user/group/perms.php?id='.$object->id;
		$head[$h][1] = $langs->trans("GroupRights");
		$head[$h][2] = 'rights';
		$h++;
	}

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	// $this->tabs = array('entity:MyModule:@mymodule:/mymodule/mypage.php?id=__ID__');
	if (is_array($conf->tabs_modules['group']))
	{
		$i=0;
		foreach ($conf->tabs_modules['group'] as $value)
		{
			$values=explode(':',$value);
			if ($values[2]) $langs->load($values[2]);
			$head[$h][0] = dol_buildpath(preg_replace('/__ID__/i',$object->id,$values[3]),1);
			$head[$h][1] = $langs->trans($values[1]);
			$head[$h][2] = 'tab'.$values[1];
			$h++;
		}
	}

	return $head;
}


/**
 * 		Show list of themes. Show all thumbs of themes
 * 		@param		fuser		User concerned or '' for global theme
 * 		@param		edit		1 to add edit form
 */
function show_theme($fuser,$edit=0,$foruserprofile=false)
{
    global $conf,$langs,$dirtheme,$bc;


    $selected_theme=$conf->global->MAIN_THEME;
    if (! empty($fuser)) $selected_theme=$fuser->conf->MAIN_THEME;

    $colspan=2;
    if ($foruserprofile) $colspan=4;

    $thumbsbyrow=6;
    print '<table class="noborder" width="100%">';

    // Title
    if ($foruserprofile)
    {
    	print '<tr class="liste_titre"><td width="25%">'.$langs->trans("Parameter").'</td><td width="25%">'.$langs->trans("DefaultValue").'</td>';
    	print '<td colspan="2">&nbsp;</td>';
	    print '</tr>';
    }
    else
    {
    	print '<tr class="liste_titre"><td>'.$langs->trans("DefaultSkin").'</td>';
    	print '<td align="right">';
    	$url='http://www.dolistore.com/lang-en/4-skins';
    	if (preg_match('/fr/i',$langs->defaultlang)) $url='http://www.dolistore.com/lang-fr/4-themes';
    	print '<a href="'.$url.'" target="_blank">';
    	print $langs->trans('DownloadMoreSkins');
    	print '</a>';
    	print '</td></tr>';
    }

    $var=false;

    if ($foruserprofile)
    {
	    print '<tr '.$bc[$var].'>';
	    print '<td>'.$langs->trans("DefaultSkin").'</td>';
	    print '<td>'.$conf->global->MAIN_THEME.'</td>';
	    print '<td '.$bc[$var].' align="left" nowrap="nowrap" width="20%"><input '.$bc[$var].' name="check_MAIN_THEME"'.($edit?'':' disabled').' type="checkbox" '.($selected_theme?" checked":"").'> '.$langs->trans("UsePersonalValue").'</td>';
	    print '<td '.$bc[$var].'>&nbsp;</td>';
	    print '</tr>';
    }

	if ($edit)
	{
		if ($subdir == $conf->global->MAIN_THEME) $title=$langs->trans("ThemeCurrentlyActive");
		else $title=$langs->trans("ShowPreview");
	}

    $var=!$var;
    print '<tr '.$bc[$var].'><td colspan="'.$colspan.'">';

    print '<table class="nobordernopadding" width="100%">';
    $handle=opendir($dirtheme);
    $i=0;
    while (($subdir = readdir($handle))!==false)
    {
        if (is_dir($dirtheme."/".$subdir) && substr($subdir, 0, 1) <> '.'
        	&& substr($subdir, 0, 3) <> 'CVS' && ! preg_match('/common|phones/i',$subdir))
        {
			// Disable not stable themes
        	if ($conf->global->MAIN_FEATURES_LEVEL < 1 && preg_match('/bureau2crea/i',$subdir)) continue;
        	
            if ($i % $thumbsbyrow == 0)
            {
                print '<tr '.$bc[$var].'>';
            }

            print '<td align="center">';
            $file=$dirtheme."/".$subdir."/thumb.png";
            if (! file_exists($file)) $file=$dirtheme."/common/nophoto.jpg";
            print '<table><tr><td>';
			print '<a href="'.$_SERVER["PHP_SELF"].($edit?'?action=edit&theme=':'?theme=').$subdir.(! empty($_GET["optioncss"])?'&optioncss='.$_GET["optioncss"]:'').($fuser?'&id='.$fuser->id:'').'" style="font-weight: normal;" alt="'.$langs->trans("Preview").'">';
			if ($subdir == $conf->global->MAIN_THEME) $title=$langs->trans("ThemeCurrentlyActive");
			else $title=$langs->trans("ShowPreview");
            print '<img src="'.$file.'" border="0" width="80" height="60" alt="'.$title.'" title="'.$title.'">';
			print '</a>';
			print '</td></tr><tr><td align="center">';
            if ($subdir == $selected_theme)
            {
                print '<input '.($edit?'':'disabled').' type="radio" '.$bc[$var].' style="border: 0px;" checked name="main_theme" value="'.$subdir.'"> <b>'.$subdir.'</b>';
            }
            else
            {
                print '<input '.($edit?'':'disabled').' type="radio" '.$bc[$var].' style="border: 0px;" name="main_theme" value="'.$subdir.'"> '.$subdir;
            }
            print '</td></tr></table></td>';

            $i++;

            if ($i % $thumbsbyrow == 0) print '</tr>';
        }
    }
    if ($i % $thumbsbyrow != 0)
    {
        while ($i % $thumbsbyrow != 0)
        {
            print '<td>&nbsp;</td>';
            $i++;
        }
        print '</tr>';
    }
    print '</table>';

    print '</td></tr>';
    print '</table>';
}

?>
