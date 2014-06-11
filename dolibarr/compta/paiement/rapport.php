<?php
/* Copyright (C) 2003-2006 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *	\file       htdocs/compta/paiement/rapport.php
 *	\ingroup    facture
 *	\brief      Payment reports page
 *	\version    $Id: rapport.php,v 1.43.2.1 2011/03/07 02:13:10 eldy Exp $
 */

require("../../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/includes/modules/rapport/pdf_paiement.class.php");
require_once(DOL_DOCUMENT_ROOT."/lib/files.lib.php");

// Security check
if (! $user->rights->facture->lire)
accessforbidden();

$dir = $conf->facture->dir_output.'/payments';

$socid=0;
if ($user->societe_id > 0)
{
    $action = '';
    $socid = $user->societe_id;
    $dir = $conf->facture->dir_output.'/payments/private/'.$user->id;
}

$year = $_GET["year"];
if (! $year) { $year=date("Y"); }


/*
 * Actions
 */

if ($_POST["action"] == 'builddoc')
{
    $rap = new pdf_paiement($db);

    $outputlangs = $langs;
    if (! empty($_REQUEST['lang_id']))
    {
        $outputlangs = new Translate("",$conf);
        $outputlangs->setDefaultLang($_REQUEST['lang_id']);
    }

    // We save charset_output to restore it because write_file can change it if needed for
    // output format that does not support UTF8.
    $sav_charset_output=$outputlangs->charset_output;
    if ($rap->write_file($dir, $_POST["remonth"], $_POST["reyear"], $outputlangs) > 0)
    {
        $outputlangs->charset_output=$sav_charset_output;
    }
    else
    {
        $outputlangs->charset_output=$sav_charset_output;
        dol_syslog("Erreur dans commande_pdf_create");
        dol_print_error($db,$obj->error);
    }

    $year = $_POST["reyear"];
}


/*
 * View
 */

llxHeader();

$titre=($year?$langs->trans("PaymentsReportsForYear",$year):$langs->trans("PaymentsReports"));
print_fiche_titre($titre);

// Formulaire de generation
print '<form method="post" action="rapport.php?year='.$year.'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="builddoc">';
$cmonth = GETPOST("remonth")?GETPOST("remonth"):date("n", time());
$syear = GETPOST("reyear")?GETPOST("reyear"):date("Y", time());

print '<select name="remonth">';
for ($month = 1 ; $month < 13 ; $month++)
{
    if ($month == $cmonth)
    {
        print "<option value=\"$month\" selected=\"true\">" . dol_print_date(mktime(0,0,0,$month),"%B");
    }
    else
    {
        print "<option value=\"$month\">" . dol_print_date(mktime(0,0,0,$month),"%B");
    }
}
print "</select>";
print '<select name="reyear">';

for ($formyear = $syear - 2; $formyear < $syear +1 ; $formyear++)
{
    if ($formyear == $syear)
    {
        print "<option value=\"$formyear\" selected=\"true\">".$formyear."</option>";
    }
    else
    {
        print "<option value=\"$formyear\">".$formyear."</option>";
    }
}
print "</select>\n";
print '<input type="submit" class="button" value="'.$langs->trans("Create").'">';
print '</form>';
print '<br>';

clearstatcache();

// Show link on other years
$linkforyear=array();
$found=0;
if (is_dir($dir))
{
    $handle=opendir($dir);
    if (is_resource($handle))
    {
        while (($file = readdir($handle))!==false)
        {
            if (is_dir($dir.'/'.$file) && ! preg_match('/^\./',$file) && is_numeric($file))
            {
                $found=1;
                $linkforyear[]=$file;
            }
        }
    }
}
asort($linkforyear);
foreach($linkforyear as $cursoryear)
{
    print '<a href="rapport.php?year='.$cursoryear.'">'.$cursoryear.'</a> &nbsp;';
}

if ($year)
{
    if (is_dir($dir.'/'.$year))
    {
        $handle=opendir($dir.'/'.$year);

        if ($found) print '<br>';
        print '<br>';
        print '<table width="100%" class="noborder">';
        print '<tr class="liste_titre">';
        print '<td>'.$langs->trans("Reporting").'</td>';
        print '<td align="right">'.$langs->trans("Size").'</td>';
        print '<td align="right">'.$langs->trans("Date").'</td>';
        print '</tr>';
        $var=true;
        if (is_resource($handle))
        {
            while (($file = readdir($handle))!==false)
            {
                if (preg_match('/^payment/i',$file))
                {
                    $var=!$var;
                    $tfile = $dir . '/'.$year.'/'.$file;
                    $relativepath = $year.'/'.$file;
                    print "<tr $bc[$var]>".'<td><a href="'.DOL_URL_ROOT . '/document.php?modulepart=facture_paiement&amp;file='.urlencode($relativepath).'">'.img_pdf().' '.$file.'</a></td>';
                    print '<td align="right">'.dol_print_size(dol_filesize($tfile)).'</td>';
                    print '<td align="right">'.dol_print_date(dol_filemtime($tfile),"dayhour").'</td></tr>';
                }
            }
            closedir($handle);
        }
        print '</table>';
    }
}
$db->close();

llxFooter('$Date: 2011/03/07 02:13:10 $ - $Revision: 1.43.2.1 $');
?>
