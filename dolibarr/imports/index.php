<?php
/* Copyright (C) 2005-2009 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *       \file       htdocs/imports/index.php
 *       \ingroup    import
 *       \brief      Page accueil de la zone import
 *       \version    $Id: index.php,v 1.12 2010/07/21 12:35:58 eldy Exp $
 */

require_once("../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/imports/class/import.class.php");

$langs->load("exports");

if (! $user->societe_id == 0)
  accessforbidden();

$import=new Import($db);
$import->load_arrays($user);


/*
 * View
 */

$html=new Form($db);

llxHeader('',$langs->trans("ImportArea"),'EN:Module_Imports_En|FR:Module_Imports|ES:M&oacute;dulo_Importaciones');

print_fiche_titre($langs->trans("ImportArea"));

print $langs->trans("FormatedImportDesc1").'<br>';
print $langs->trans("FormatedImportDesc2").'<br>';
print '<br>';

print '<table class="notopnoleftnoright" width="100%">';

print '<tr><td valign="top" width="40%" class="notopnoleft">';


// Liste des formats d'imports disponibles
$var=true;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td colspan="2">'.$langs->trans("AvailableFormats").'</td>';
print '<td>'.$langs->trans("LibraryShort").'</td>';
print '<td align="right">'.$langs->trans("LibraryVersion").'</td>';
print '</tr>';

include_once(DOL_DOCUMENT_ROOT.'/includes/modules/import/modules_import.php');
$model=new ModeleImports();
$liste=$model->liste_modeles($db);

foreach($liste as $key)
{
    $var=!$var;
    print '<tr '.$bc[$var].'>';
    print '<td width="16">'.img_picto_common($model->getDriverLabel($key),$model->getPicto($key)).'</td>';
    $text=$model->getDriverDesc($key);
    print '<td>'.$html->textwithpicto($model->getDriverLabel($key),$text).'</td>';
    print '<td>'.$model->getLibLabel($key).'</td>';
    print '<td nowrap="nowrap" align="right">'.$model->getLibVersion($key).'</td>';
    print '</tr>';
}

print '</table>';


print '</td><td valign="top" width="60%" class="notopnoleftnoright">';


// Affiche les modules d'imports
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Module").'</td>';
print '<td>'.$langs->trans("ImportableDatas").'</td>';
//print '<td>&nbsp;</td>';
print '</tr>';
$val=true;
if (sizeof($import->array_import_code))
{
    foreach ($import->array_import_code as $key => $value)
    {
        $val=!$val;
        print '<tr '.$bc[$val].'><td>';
        print img_object($import->array_import_module[$key]->getName(),$import->array_import_module[$key]->picto).' ';
        print $import->array_import_module[$key]->getName();
        print '</td><td>';
        $string=$langs->trans($import->array_import_label[$key]);
        print ($string!=$import->array_import_label[$key]?$string:$import->array_import_label[$key]);
        print '</td>';
//        print '<td width="24">';
//        print '<a href="'.DOL_URL_ROOT.'/imports/import.php?step=2&amp;datatoimport='.$import->array_import_code[$key].'&amp;action=cleanselect">'.img_picto($langs->trans("NewImport"),'filenew').'</a>';
//        print '</td>';
        print '</tr>';

    }
}
else
{
    print '<tr><td '.$bc[false].' colspan="2">'.$langs->trans("NoImportableData").'</td></tr>';
}
print '</table>';
print '<br>';

print '<center>';
if (sizeof($import->array_import_code))
{
	//if ($user->rights->import->run)
	//{
		print '<a class="butAction" href="'.DOL_URL_ROOT.'/imports/import.php?leftmenu=import">'.$langs->trans("NewImport").'</a>';
	//}
	//else
	//{
	//	print '<a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("NotEnoughPermissions")).'">'.$langs->trans("NewImport").'</a>';
	//}
}
print '</center>';

print '</td></tr>';
print '</table>';

$db->close();


llxFooter('$Date: 2010/07/21 12:35:58 $ - $Revision: 1.12 $');

?>
