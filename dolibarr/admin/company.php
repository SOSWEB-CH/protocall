<?php
/* Copyright (C) 2001-2007 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *	\file       htdocs/admin/company.php
 *	\ingroup    company
 *	\brief      Setup page to configure company/foundation
 *	\version    $Id: company.php,v 1.84.2.1 2011/02/09 12:03:49 eldy Exp $
 */

require("../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/lib/admin.lib.php");
require_once(DOL_DOCUMENT_ROOT."/lib/company.lib.php");
require_once(DOL_DOCUMENT_ROOT."/lib/images.lib.php");
require_once(DOL_DOCUMENT_ROOT."/lib/functions2.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formcompany.class.php");

$langs->load("admin");
$langs->load("companies");

if (!$user->admin)
accessforbidden();

// Define size of logo small and mini (might be set into other pages)
$maxwidthsmall=270;$maxheightsmall=150;
$maxwidthmini=128;$maxheightmini=72;
$quality = 80;


/*
 * Actions
 */

if ( (isset($_POST["action"]) && $_POST["action"] == 'update' && empty($_POST["cancel"]))
|| (isset($_POST["action"]) && $_POST["action"] == 'updateedit') )
{
    require_once(DOL_DOCUMENT_ROOT."/lib/files.lib.php");

    $new_pays_id=$_POST["pays_id"];
    $new_pays_code=getCountry($new_pays_id,2);
    $new_pays_label=getCountry($new_pays_id,0);

    dolibarr_set_const($db, "MAIN_INFO_SOCIETE_PAYS", $new_pays_id.':'.$new_pays_code.':'.$new_pays_label,'chaine',0,'',$conf->entity);

    dolibarr_set_const($db, "MAIN_INFO_SOCIETE_NOM",$_POST["nom"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_SOCIETE_ADRESSE",$_POST["address"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_SOCIETE_VILLE",$_POST["ville"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_SOCIETE_CP",$_POST["cp"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_SOCIETE_DEPARTEMENT",$_POST["departement_id"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_MONNAIE",$_POST["currency"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_SOCIETE_TEL",$_POST["tel"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_SOCIETE_FAX",$_POST["fax"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_SOCIETE_MAIL",$_POST["mail"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_SOCIETE_WEB",$_POST["web"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_SOCIETE_NOTE",$_POST["note"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_SOCIETE_GENCOD",$_POST["gencod"],'chaine',0,'',$conf->entity);
    if ($_FILES["logo"]["tmp_name"])
    {
        if (preg_match('/([^\\/:]+)$/i',$_FILES["logo"]["name"],$reg))
        {
            $original_file=$reg[1];

            $isimage=image_format_supported($original_file);
            if ($isimage >= 0)
            {
                dol_syslog("Move file ".$_FILES["logo"]["tmp_name"]." to ".$conf->mycompany->dir_output.'/logos/'.$original_file);
                if (! is_dir($conf->mycompany->dir_output.'/logos/'))
                {
                    create_exdir($conf->mycompany->dir_output.'/logos/');
                }
                $result=dol_move_uploaded_file($_FILES["logo"]["tmp_name"],$conf->mycompany->dir_output.'/logos/'.$original_file,1,0,$_FILES['logo']['error']);
                if ($result > 0)
                {
                    dolibarr_set_const($db, "MAIN_INFO_SOCIETE_LOGO",$original_file,'chaine',0,'',$conf->entity);

                    // Create thumbs of logo (Note that PDF use original file and not thumbs)
                    if ($isimage > 0)
                    {
                        // Create small thumbs for company (Ratio is near 16/9)
                        // Used on logon for example
                        $imgThumbSmall = vignette($conf->mycompany->dir_output.'/logos/'.$original_file, $maxwidthsmall, $maxheightsmall, '_small', $quality);
                        if (preg_match('/([^\\/:]+)$/i',$imgThumbSmall,$reg))
                        {
                            $imgThumbSmall = $reg[1];
                            dolibarr_set_const($db, "MAIN_INFO_SOCIETE_LOGO_SMALL",$imgThumbSmall,'chaine',0,'',$conf->entity);
                        }
                        else dol_syslog($imgThumbSmall);

                        // Create mini thumbs for company (Ratio is near 16/9)
                        // Used on menu or for setup page for example
                        $imgThumbMini = vignette($conf->mycompany->dir_output.'/logos/'.$original_file, $maxwidthmini, $maxheightmini, '_mini', $quality);
                        if (preg_match('/([^\\/:]+)$/i',$imgThumbMini,$reg))
                        {
                            $imgThumbMini = $reg[1];
                            dolibarr_set_const($db, "MAIN_INFO_SOCIETE_LOGO_MINI",$imgThumbMini,'chaine',0,'',$conf->entity);
                        }
                        else dol_syslog($imgThumbMini);
                    }
                    else dol_syslog($langs->trans("ErrorImageFormatNotSupported"),LOG_WARNING);
                }
                else if (preg_match('/^ErrorFileIsInfectedWithAVirus/',$result))
                {
                    $langs->load("errors");
                    $tmparray=explode(':',$result);
                    $message .= '<div class="error">'.$langs->trans('ErrorFileIsInfectedWithAVirus',$tmparray[1]).'</div>';
                }
                else
                {
                    $message .= '<div class="error">'.$langs->trans("ErrorFailedToSaveFile").'</div>';
                }
            }
            else
            {
                $message .= '<div class="error">'.$langs->trans("ErrorOnlyPngJpgSupported").'</div>';
            }
        }
    }

    dolibarr_set_const($db, "MAIN_INFO_CAPITAL",$_POST["capital"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_SOCIETE_FORME_JURIDIQUE",$_POST["forme_juridique_code"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_SIREN",$_POST["siren"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_SIRET",$_POST["siret"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_APE",$_POST["ape"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_RCS",$_POST["rcs"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "MAIN_INFO_TVAINTRA",$_POST["tva"],'chaine',0,'',$conf->entity);

    dolibarr_set_const($db, "SOCIETE_FISCAL_MONTH_START",$_POST["fiscalmonthstart"],'chaine',0,'',$conf->entity);

    dolibarr_set_const($db, "FACTURE_TVAOPTION",$_POST["optiontva"],'chaine',0,'',$conf->entity);

    // Local taxes
    dolibarr_set_const($db, "FACTURE_LOCAL_TAX1_OPTION",$_POST["optionlocaltax1"],'chaine',0,'',$conf->entity);
    dolibarr_set_const($db, "FACTURE_LOCAL_TAX2_OPTION",$_POST["optionlocaltax2"],'chaine',0,'',$conf->entity);

    if ($_POST['action'] != 'updateedit' && ! $message)
    {
        Header("Location: ".$_SERVER["PHP_SELF"]);
        exit;
    }
}

if ($_GET["action"] == 'addthumb')
{
    if (file_exists($conf->societe->dir_output.'/logos/'.$_GET["file"]))
    {
        $isimage=image_format_supported($_GET["file"]);

        // Create thumbs of logo
        if ($isimage > 0)
        {
            // Create small thumbs for company (Ratio is near 16/9)
            // Used on logon for example
            $imgThumbSmall = vignette($conf->mycompany->dir_output.'/logos/'.$_GET["file"], $maxwidthsmall, $maxheightsmall, '_small',$quality);
            if (image_format_supported($imgThumbSmall) >= 0 && preg_match('/([^\\/:]+)$/i',$imgThumbSmall,$reg))
            {
                $imgThumbSmall = $reg[1];
                dolibarr_set_const($db, "MAIN_INFO_SOCIETE_LOGO_SMALL",$imgThumbSmall,'chaine',0,'',$conf->entity);
            }
            else dol_syslog($imgThumbSmall);

            // Create mini thumbs for company (Ratio is near 16/9)
            // Used on menu or for setup page for example
            $imgThumbMini = vignette($conf->mycompany->dir_output.'/logos/'.$_GET["file"], $maxwidthmini, $maxheightmini, '_mini',$quality);
            if (image_format_supported($imgThumbSmall) >= 0 && preg_match('/([^\\/:]+)$/i',$imgThumbMini,$reg))
            {
                $imgThumbMini = $reg[1];
                dolibarr_set_const($db, "MAIN_INFO_SOCIETE_LOGO_MINI",$imgThumbMini,'chaine',0,'',$conf->entity);
            }
            else dol_syslog($imgThumbMini);

            Header("Location: ".$_SERVER["PHP_SELF"]);
            exit;
        }
        else
        {
            $message .= '<div class="error">'.$langs->trans("ErrorImageFormatNotSupported").'</div>';
            dol_syslog($langs->transnoentities("ErrorImageFormatNotSupported"),LOG_WARNING);
        }
    }
    else
    {
        $message .= '<div class="error">'.$langs->trans("ErrorFileDoesNotExists",$_GET["file"]).'</div>';
        dol_syslog($langs->transnoentities("ErrorFileDoesNotExists",$_GET["file"]),LOG_WARNING);
    }
}

if ($_GET["action"] == 'removelogo')
{
    $logofile=$conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
    dol_delete_file($logofile);
    dolibarr_del_const($db, "MAIN_INFO_SOCIETE_LOGO",$conf->entity);
    $mysoc->logo='';

    $logosmallfile=$conf->mycompany->dir_output.'/logos/thumbs/'.$mysoc->logo_small;
    dol_delete_file($logosmallfile);
    dolibarr_del_const($db, "MAIN_INFO_SOCIETE_LOGO_SMALL",$conf->entity);
    $mysoc->logo_small='';

    $logominifile=$conf->mycompany->dir_output.'/logos/thumbs/'.$mysoc->logo_mini;
    dol_delete_file($logominifile);
    dolibarr_del_const($db, "MAIN_INFO_SOCIETE_LOGO_MINI",$conf->entity);
    $mysoc->logo_mini='';
}


/*
 * View
 */

$wikihelp='EN:First_setup|FR:Premiers_paramétrages|ES:Primeras_configuraciones';
llxHeader('',$langs->trans("Setup"),$wikihelp);

$form = new Form($db);
$formcompany = new FormCompany($db);

$countrynotdefined='<font class="error">'.$langs->trans("ErrorSetACountryFirst").' ('.$langs->trans("SeeAbove").')</font>';

// We define pays_id, pays_code and pays_label
if (! empty($conf->global->MAIN_INFO_SOCIETE_PAYS))
{
    $tmp=explode(':',$conf->global->MAIN_INFO_SOCIETE_PAYS);
    $pays_id=$tmp[0];
    if (! empty($tmp[1]))   // If $conf->global->MAIN_INFO_SOCIETE_PAYS is "id:code:label"
    {
        $pays_code=$tmp[1];
        $pays_label=$tmp[2];
    }
    else
    {
        $pays_code=getCountry($pays_id,2);
        $pays_label=getCountry($pays_id,0);
    }
}
else
{
    $pays_id=0;
    $pays_code='';
    $pays_label='';
}


print_fiche_titre($langs->trans("CompanyFoundation"),'','setup');

print $langs->trans("CompanyFundationDesc")."<br>\n";
print "<br>\n";

if ((isset($_GET["action"]) && $_GET["action"] == 'edit')
|| (isset($_POST["action"]) && $_POST["action"] == 'updateedit') )
{
    /**
     * Edition des parametres
     */
    print "\n".'<script type="text/javascript" language="javascript">';
    print 'jQuery(document).ready(function () {
              jQuery("#selectpays_id").change(function() {
                document.form_index.action.value="updateedit";
                document.form_index.submit();
              });
          });';
    print '</script>'."\n";

    print '<form enctype="multipart/form-data" method="post" action="'.$_SERVER["PHP_SELF"].'" name="form_index">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="update">';
    $var=true;

    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre"><td width="35%">'.$langs->trans("CompanyInfo").'</td><td>'.$langs->trans("Value").'</td></tr>'."\n";

    $var=!$var;
    print '<tr '.$bc[$var].'><td class="fieldrequired">'.$langs->trans("CompanyName").'</td><td>';
    print '<input name="nom" size="30" value="'. ($conf->global->MAIN_INFO_SOCIETE_NOM?$conf->global->MAIN_INFO_SOCIETE_NOM:$_POST["nom"]) . '"></td></tr>'."\n";

    $var=!$var;
    print '<tr '.$bc[$var].'><td>'.$langs->trans("CompanyAddress").'</td><td>';
    print '<textarea name="address" cols="80" rows="'.ROWS_3.'">'. ($conf->global->MAIN_INFO_SOCIETE_ADRESSE?$conf->global->MAIN_INFO_SOCIETE_ADRESSE:$_POST["address"]) . '</textarea></td></tr>'."\n";

    $var=!$var;
    print '<tr '.$bc[$var].'><td>'.$langs->trans("CompanyZip").'</td><td>';
    print '<input name="cp" value="'. ($conf->global->MAIN_INFO_SOCIETE_CP?$conf->global->MAIN_INFO_SOCIETE_CP:$_POST["cp"]) . '" size="10"></td></tr>'."\n";

    $var=!$var;
    print '<tr '.$bc[$var].'><td>'.$langs->trans("CompanyTown").'</td><td>';
    print '<input name="ville" size="30" value="'. ($conf->global->MAIN_INFO_SOCIETE_VILLE?$conf->global->MAIN_INFO_SOCIETE_VILLE:$_POST["ville"]) . '"></td></tr>'."\n";

    // Country
    $var=!$var;
    print '<tr '.$bc[$var].'><td class="fieldrequired">'.$langs->trans("Country").'</td><td>';
    $pays_selected=$pays_id;
    //if (empty($pays_selected)) $pays_selected=substr($langs->defaultlang,-2);    // Par defaut, pays de la localisation
    $form->select_pays($pays_selected,'pays_id');
    if ($user->admin) print info_admin($langs->trans("YouCanChangeValuesForThisListFromDictionnarySetup"),1);
    print '</td></tr>'."\n";

    $var=!$var;
    print '<tr '.$bc[$var].'><td>'.$langs->trans("State").'</td><td>';
    $formcompany->select_departement($conf->global->MAIN_INFO_SOCIETE_DEPARTEMENT,$pays_code,'departement_id');
    print '</td></tr>'."\n";

    $var=!$var;
    print '<tr '.$bc[$var].'><td>'.$langs->trans("CompanyCurrency").'</td><td>';
    $form->select_currency($conf->global->MAIN_MONNAIE,"currency");
    print '</td></tr>'."\n";

    $var=!$var;
    print '<tr '.$bc[$var].'><td>'.$langs->trans("Tel").'</td><td>';
    print '<input name="tel" value="'. $conf->global->MAIN_INFO_SOCIETE_TEL . '"></td></tr>';
    print '</td></tr>'."\n";

    $var=!$var;
    print '<tr '.$bc[$var].'><td>'.$langs->trans("Fax").'</td><td>';
    print '<input name="fax" value="'. $conf->global->MAIN_INFO_SOCIETE_FAX . '"></td></tr>';
    print '</td></tr>'."\n";

    $var=!$var;
    print '<tr '.$bc[$var].'><td>'.$langs->trans("Mail").'</td><td>';
    print '<input name="mail" size="60" value="'. $conf->global->MAIN_INFO_SOCIETE_MAIL . '"></td></tr>';
    print '</td></tr>'."\n";

    // Web
    $var=!$var;
    print '<tr '.$bc[$var].'><td>'.$langs->trans("Web").'</td><td>';
    print '<input name="web" size="60" value="'. $conf->global->MAIN_INFO_SOCIETE_WEB . '"></td></tr>';
    print '</td></tr>'."\n";

    // Barcode
    if ($conf->barcode->enabled)
    {
        $var=!$var;
        print '<tr '.$bc[$var].'><td>'.$langs->trans("Gencod").'</td><td>';
        print '<input name="gencod" size="40" value="'. $conf->global->MAIN_INFO_SOCIETE_GENCOD . '"></td></tr>';
        print '</td></tr>';
    }

    // Logo
    $var=!$var;
    print '<tr '.$bc[$var].'><td>'.$langs->trans("Logo").' (png,jpg)</td><td>';
    print '<table width="100%" class="nocellnopadd"><tr><td valign="center">';
    print '<input type="file" class="flat" name="logo" size="50">';
    print '</td><td valign="middle" align="right">';
    if ($mysoc->logo_mini)
    {
        print '<a href="'.$_SERVER["PHP_SELF"].'?action=removelogo">'.img_delete($langs->trans("Delete")).'</a>';
        if (file_exists($conf->mycompany->dir_output.'/logos/thumbs/'.$mysoc->logo_mini))
        {
            print ' &nbsp; ';
            print '<img src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=companylogo&amp;file='.urlencode('/thumbs/'.$mysoc->logo_mini).'">';
        }
    }
    else
    {
        print '<img height="30" src="'.DOL_URL_ROOT.'/theme/common/nophoto.jpg">';
    }
    print '</td></tr></table>';
    print '</td></tr>';

    // Note
    $var=!$var;
    print '<tr '.$bc[$var].'><td valign="top">'.$langs->trans("Note").'</td><td>';
    print '<textarea class="flat" name="note" cols="80" rows="'.ROWS_5.'">'.$conf->global->MAIN_INFO_SOCIETE_NOTE.'</textarea></td></tr>';
    print '</td></tr>';

    print '</table>';

    print '<br>';

    // Identifiants de la societe (propre au pays)
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre"><td>'.$langs->trans("CompanyIds").'</td><td>'.$langs->trans("Value").'</td></tr>';
    $var=true;

    $langs->load("companies");

    // Capital
    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("Capital").'</td><td>';
    print '<input name="capital" size="20" value="' . $conf->global->MAIN_INFO_CAPITAL . '"></td></tr>';

    // Forme juridique
    $var=!$var;
    print '<tr '.$bc[$var].'><td>'.$langs->trans("JuridicalStatus").'</td><td>';
    if ($pays_code)
    {
        $formcompany->select_forme_juridique($conf->global->MAIN_INFO_SOCIETE_FORME_JURIDIQUE,$pays_code);
    }
    else
    {
        print $countrynotdefined;
    }
    print '</td></tr>';

    // ProfID1
    if ($langs->transcountry("ProfId1",$pays_code) != '-')
    {
        $var=!$var;
        print '<tr '.$bc[$var].'><td width="35%">'.$langs->transcountry("ProfId1",$pays_code).'</td><td>';
        if ($pays_code)
        {
            print '<input name="siren" size="20" value="' . $conf->global->MAIN_INFO_SIREN . '">';
        }
        else
        {
            print $countrynotdefined;
        }
        print '</td></tr>';
    }

    // ProfId2
    if ($langs->transcountry("ProfId2",$pays_code) != '-')
    {
        $var=!$var;
        print '<tr '.$bc[$var].'><td width="35%">'.$langs->transcountry("ProfId2",$pays_code).'</td><td>';
        if ($pays_code)
        {
            print '<input name="siret" size="20" value="' . $conf->global->MAIN_INFO_SIRET . '">';
        }
        else
        {
            print $countrynotdefined;
        }
        print '</td></tr>';
    }

    // ProfId3
    if ($langs->transcountry("ProfId3",$pays_code) != '-')
    {
        $var=!$var;
        print '<tr '.$bc[$var].'><td width="35%">'.$langs->transcountry("ProfId3",$pays_code).'</td><td>';
        if ($pays_code)
        {
            print '<input name="ape" size="20" value="' . $conf->global->MAIN_INFO_APE . '">';
        }
        else
        {
            print $countrynotdefined;
        }
        print '</td></tr>';
    }

    // ProfId4
    if ($langs->transcountry("ProfId4",$pays_code) != '-')
    {
        $var=!$var;
        print '<tr '.$bc[$var].'><td width="35%">'.$langs->transcountry("ProfId4",$pays_code).'</td><td>';
        if ($pays_code)
        {
            print '<input name="rcs" size="20" value="' . $conf->global->MAIN_INFO_RCS . '">';
        }
        else
        {
            print $countrynotdefined;
        }
        print '</td></tr>';
    }

    // TVA Intra
    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("VATIntra").'</td><td>';
    print '<input name="tva" size="20" value="' . $conf->global->MAIN_INFO_TVAINTRA . '">';
    print '</td></tr>';

    print '</table>';


    /*
     *  Debut d'annee fiscale
     */
    print '<br>';
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("FiscalYearInformation").'</td><td>'.$langs->trans("Value").'</td>';
    print "</tr>\n";
    $var=true;

    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("FiscalMonthStart").'</td><td>';
    print $form->select_month($conf->global->SOCIETE_FISCAL_MONTH_START,'fiscalmonthstart',1) . '</td></tr>';

    print "</table>";


    /*
     *  Options fiscale
     */
    print '<br>';
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("VATManagement").'</td><td>'.$langs->trans("Description").'</td>';
    print '<td align="right">&nbsp;</td>';
    print "</tr>\n";
    $var=true;

    $var=!$var;
    print "<tr ".$bc[$var]."><td width=\"140\"><label><input type=\"radio\" name=\"optiontva\" value=\"reel\"".($conf->global->FACTURE_TVAOPTION != "franchise"?" checked":"")."> ".$langs->trans("VATIsUsed")."</label></td>";
    print '<td colspan="2">';
    print "<table>";
    print "<tr><td>".$langs->trans("VATIsUsedDesc")."</td></tr>";
    print "<tr><td><i>".$langs->trans("Example").': '.$langs->trans("VATIsUsedExampleFR")."</i></td></tr>\n";
    print "</table>";
    print "</td></tr>\n";

    $var=!$var;
    print "<tr ".$bc[$var]."><td width=\"140\"><label><input type=\"radio\" name=\"optiontva\" value=\"franchise\"".($conf->global->FACTURE_TVAOPTION == "franchise"?" checked":"")."> ".$langs->trans("VATIsNotUsed")."</label></td>";
    print '<td colspan="2">';
    print "<table>";
    print "<tr><td>".$langs->trans("VATIsNotUsedDesc")."</td></tr>";
    print "<tr><td><i>".$langs->trans("Example").': '.$langs->trans("VATIsNotUsedExampleFR")."</i></td></tr>\n";
    print "</table>";
    print "</td></tr>\n";

    print "</table>";

    /*
     *  Local Taxes
     */
    if ($pays_code=='ES')
    {
        // Local Tax 1
        print '<br>';
        print '<table class="noborder" width="100%">';
        print '<tr class="liste_titre">';
        print '<td>'.$langs->transcountry("LocalTax1Management",$pays_code).'</td><td>'.$langs->trans("Description").'</td>';
        print '<td align="right">&nbsp;</td>';
        print "</tr>\n";
        $var=true;

        $var=!$var;
        print "<tr ".$bc[$var]."><td width=\"140\"><label><input type=\"radio\" name=\"optionlocaltax1\" value=\"localtax1on\"".($conf->global->FACTURE_LOCAL_TAX1_OPTION != "localtax1off"?" checked":"")."> ".$langs->transcountry("LocalTax1IsUsed",$pays_code)."</label></td>";
        print '<td colspan="2">';
        print "<table>";
        print "<tr><td>".$langs->transcountry("LocalTax1IsUsedDesc",$pays_code)."</td></tr>";
        print "<tr><td><i>".$langs->trans("Example").': '.$langs->transcountry("LocalTax1IsUsedExample",$pays_code)."</i></td></tr>\n";
        print "</table>";
        print "</td></tr>\n";

        $var=!$var;
        print "<tr ".$bc[$var]."><td width=\"140\"><label><input type=\"radio\" name=\"optionlocaltax1\" value=\"localtax1off\"".($conf->global->FACTURE_LOCAL_TAX1_OPTION == "localtax1off"?" checked":"")."> ".$langs->transcountry("LocalTax1IsNotUsed",$pays_code)."</label></td>";
        print '<td colspan="2">';
        print "<table>";
        print "<tr><td>".$langs->transcountry("LocalTax1IsNotUsedDesc",$pays_code)."</td></tr>";
        print "<tr><td><i>".$langs->trans("Example").': '.$langs->transcountry("LocalTax1IsNotUsedExample",$pays_code)."</i></td></tr>\n";
        print "</table>";
        print "</td></tr>\n";
        print "</table>";

        // Local Tax 2
        print '<br>';
        print '<table class="noborder" width="100%">';
        print '<tr class="liste_titre">';
        print '<td>'.$langs->transcountry("LocalTax2Management",$pays_code).'</td><td>'.$langs->trans("Description").'</td>';
        print '<td align="right">&nbsp;</td>';
        print "</tr>\n";
        $var=true;

        $var=!$var;
        print "<tr ".$bc[$var]."><td width=\"140\"><label><input type=\"radio\" name=\"optionlocaltax2\" value=\"localtax2on\"".($conf->global->FACTURE_LOCAL_TAX2_OPTION != "localtax2off"?" checked":"")."> ".$langs->transcountry("LocalTax2IsUsed",$pays_code)."</label></td>";
        print '<td colspan="2">';
        print "<table>";
        print "<tr><td>".$langs->transcountry("LocalTax2IsUsedDesc",$pays_code)."</td></tr>";
        print "<tr><td><i>".$langs->trans("Example").': '.$langs->transcountry("LocalTax2IsUsedExample",$pays_code)."</i></td></tr>\n";
        print "</table>";
        print "</td></tr>\n";

        $var=!$var;
        print "<tr ".$bc[$var]."><td width=\"140\"><label><input type=\"radio\" name=\"optionlocaltax2\" value=\"localtax2off\"".($conf->global->FACTURE_LOCAL_TAX2_OPTION == "localtax2off"?" checked":"")."> ".$langs->transcountry("LocalTax2IsNotUsed",$pays_code)."</label></td>";
        print '<td colspan="2">';
        print "<table>";
        print "<tr><td>".$langs->transcountry("LocalTax2IsNotUsedDesc",$pays_code)."</td></tr>";
        print "<tr><td><i>".$langs->trans("Example").': '.$langs->transcountry("LocalTax2IsNotUsedExample",$pays_code)."</i></td></tr>\n";
        print "</table>";
        print "</td></tr>\n";
        print "</table>";
    }


    print '<br><center>';
    print '<input type="submit" class="button" name="save" value="'.$langs->trans("Save").'">';
    print ' &nbsp; &nbsp; ';
    print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'">';
    print '</center>';
    print '<br>';

    print '</form>';
}
else
{
    /*
     * Show parameters
     */

    if ($message) print $message.'<br>';

    // Actions buttons
    //print '<div class="tabsAction">';
    //print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit">'.$langs->trans("Modify").'</a>';
    //print '</div><br>';

    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre"><td>'.$langs->trans("CompanyInfo").'</td><td>'.$langs->trans("Value").'</td></tr>';
    $var=true;

    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("CompanyName").'</td><td>';
    if (! empty($conf->global->MAIN_INFO_SOCIETE_NOM)) print $conf->global->MAIN_INFO_SOCIETE_NOM;
    else print img_warning().' <font class="error">'.$langs->trans("ErrorFieldRequired",$langs->transnoentitiesnoconv("CompanyName")).'</font>';
    print '</td></tr>';

    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("CompanyAddress").'</td><td>' . nl2br($conf->global->MAIN_INFO_SOCIETE_ADRESSE) . '</td></tr>';

    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("CompanyZip").'</td><td>' . $conf->global->MAIN_INFO_SOCIETE_CP . '</td></tr>';

    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("CompanyTown").'</td><td>' . $conf->global->MAIN_INFO_SOCIETE_VILLE . '</td></tr>';

    $var=!$var;
    print '<tr '.$bc[$var].'><td>'.$langs->trans("CompanyCountry").'</td><td>';
    if ($pays_code)
    {
        $img=picto_from_langcode($pays_code);
        print $img?$img.' ':'';
        print getCountry($pays_code,1);
    }
    else print img_warning().' <font class="error">'.$langs->trans("ErrorFieldRequired",$langs->transnoentitiesnoconv("CompanyCountry")).'</font>';
    print '</td></tr>';

    $var=!$var;
    print '<tr '.$bc[$var].'><td>'.$langs->trans("State").'</td><td>';
    if ($conf->global->MAIN_INFO_SOCIETE_DEPARTEMENT)
    {
        $sql = "SELECT code_departement as code, nom as label from ".MAIN_DB_PREFIX."c_departements where rowid = '".$conf->global->MAIN_INFO_SOCIETE_DEPARTEMENT."'";
        $resql=$db->query($sql);
        if ($resql)
        {
            $obj = $db->fetch_object($resql);
        }
        else
        {
            dol_print_error($db);
        }
        $state=$obj->label;
        print $state;
    }
    print '</td></tr>';

    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("CompanyCurrency").'</td><td>';
    print currency_name($conf->global->MAIN_MONNAIE,1);
    print ' ('.$conf->global->MAIN_MONNAIE.')';
    print '</td></tr>';

    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("Tel").'</td><td>' . dol_print_phone($conf->global->MAIN_INFO_SOCIETE_TEL,$mysoc->pays_code) . '</td></tr>';

    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("Fax").'</td><td>' . dol_print_phone($conf->global->MAIN_INFO_SOCIETE_FAX,$mysoc->pays_code) . '</td></tr>';

    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("Mail").'</td><td>' . dol_print_email($conf->global->MAIN_INFO_SOCIETE_MAIL,0,0,0,80) . '</td></tr>';

    // Web
    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("Web").'</td><td>' . dol_print_url($conf->global->MAIN_INFO_SOCIETE_WEB,'_blank',80) . '</td></tr>';

    // Barcode
    if ($conf->barcode->enabled)
    {
        $var=!$var;
        print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("Gencod").'</td><td>' . $conf->global->MAIN_INFO_SOCIETE_GENCOD . '</td></tr>';
    }

    // Logo
    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("Logo").'</td><td>';

    print '<table width="100%" class="nocellnopadd"><tr><td valign="center">';
    print $mysoc->logo;
    print '</td><td valign="center" align="right">';

    // On propose la generation de la vignette si elle n'existe pas
    if (!is_file($conf->mycompany->dir_output.'/logos/thumbs/'.$mysoc->logo_mini) && preg_match('/(\.jpg|\.jpeg|\.png)$/i',$mysoc->logo))
    {
        print '<a href="'.$_SERVER["PHP_SELF"].'?action=addthumb&amp;file='.urlencode($mysoc->logo).'">'.img_refresh($langs->trans('GenerateThumb')).'&nbsp;&nbsp;</a>';
    }
    else if ($mysoc->logo_mini && is_file($conf->mycompany->dir_output.'/logos/thumbs/'.$mysoc->logo_mini))
    {
        print '<img src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=companylogo&amp;file='.urlencode('/thumbs/'.$mysoc->logo_mini).'">';
    }
    else
    {
        print '<img height="30" src="'.DOL_URL_ROOT.'/theme/common/nophoto.jpg">';
    }
    print '</td></tr></table>';

    print '</td></tr>';

    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%" valign="top">'.$langs->trans("Note").'</td><td>' . nl2br($conf->global->MAIN_INFO_SOCIETE_NOTE) . '</td></tr>';

    print '</table>';


    print '<br>';


    // Identifiants de la societe (propre au pays)
    print '<form name="formsoc" method="post">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre"><td>'.$langs->trans("CompanyIds").'</td><td>'.$langs->trans("Value").'</td></tr>';
    $var=true;

    // Capital
    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("Capital").'</td><td>';
    print $conf->global->MAIN_INFO_CAPITAL . '</td></tr>';

    // Forme juridique
    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("JuridicalStatus").'</td><td>';
    print getFormeJuridiqueLabel($conf->global->MAIN_INFO_SOCIETE_FORME_JURIDIQUE,1);
    print '</td></tr>';

    // ProfId1
    if ($langs->transcountry("ProfId1",$pays_code) != '-')
    {
        $var=!$var;
        print '<tr '.$bc[$var].'><td width="35%">'.$langs->transcountry("ProfId1",$pays_code).'</td><td>';
        if ($langs->transcountry("ProfId1",$pays_code) != '-')
        {
            print $conf->global->MAIN_INFO_SIREN;
            if ($conf->global->MAIN_INFO_SIREN && $pays_code == 'FR') print ' &nbsp; <a href="http://avis-situation-sirene.insee.fr/avisitu/jsp/avis.jsp" target="_blank">'.$langs->trans("Check").'</a>';
        }
        print '</td></tr>';
    }

    // ProfId2
    if ($langs->transcountry("ProfId2",$pays_code) != '-')
    {
        $var=!$var;
        print '<tr '.$bc[$var].'><td width="35%">'.$langs->transcountry("ProfId2",$pays_code).'</td><td>';
        if ($langs->transcountry("ProfId2",$pays_code) != '-')
        {
            print $conf->global->MAIN_INFO_SIRET;
        }
        print '</td></tr>';
    }

    // ProfId3
    if ($langs->transcountry("ProfId3",$pays_code) != '-')
    {
        $var=!$var;
        print '<tr '.$bc[$var].'><td width="35%">'.$langs->transcountry("ProfId3",$pays_code).'</td><td>';
        if ($langs->transcountry("ProfId3",$pays_code) != '-')
        {
            print $conf->global->MAIN_INFO_APE;
        }
        print '</td></tr>';
    }

    // ProfId4
    if ($langs->transcountry("ProfId4",$pays_code) != '-')
    {
        $var=!$var;
        print '<tr '.$bc[$var].'><td width="35%">'.$langs->transcountry("ProfId4",$pays_code).'</td><td>';
        if ($langs->transcountry("ProfId4",$pays_code) != '-')
        {
            print $conf->global->MAIN_INFO_RCS;
        }
        print '</td></tr>';
    }

    // TVA
    $var=!$var;
    print '<tr '.$bc[$var].'><td>'.$langs->trans("VATIntra").'</td>';
    print '<td>';
    if ($conf->global->MAIN_INFO_TVAINTRA)
    {
        $s='';
        $s.=$conf->global->MAIN_INFO_TVAINTRA;
        $s.='<input type="hidden" name="tva_intra" size="12" maxlength="20" value="'.$conf->global->MAIN_INFO_TVAINTRA.'">';
        $s.=' &nbsp; ';
        if ($conf->use_javascript_ajax)
        {
            print "\n";
            print '<script language="JavaScript" type="text/javascript">';
            print "function CheckVAT(a) {\n";
            print "newpopup('".DOL_URL_ROOT."/societe/checkvat/checkVatPopup.php?vatNumber='+a,'".dol_escape_js($langs->trans("VATIntraCheckableOnEUSite"))."',500,285);\n";
            print "}\n";
            print '</script>';
            print "\n";
            $s.='<a href="#" onClick="javascript: CheckVAT(document.formsoc.tva_intra.value);">'.$langs->trans("VATIntraCheck").'</a>';
            print $form->textwithpicto($s,$langs->trans("VATIntraCheckDesc",$langs->trans("VATIntraCheck")),1);
        }
        else
        {
            print $s.'<a href="'.$langs->transcountry("VATIntraCheckURL",$soc->id_pays).'" target="_blank">'.img_picto($langs->trans("VATIntraCheckableOnEUSite"),'help').'</a>';
        }
    }
    else
    {
        print '&nbsp;';
    }
    print '</td>';
    print '</tr>';

    print '</table>';
    print '</form>';

    /*
     *  Debut d'annee fiscale
     */
    print '<br>';
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("FiscalYearInformation").'</td><td>'.$langs->trans("Value").'</td>';
    print "</tr>\n";
    $var=true;

    $var=!$var;
    print '<tr '.$bc[$var].'><td width="35%">'.$langs->trans("FiscalMonthStart").'</td><td>';
    $monthstart=(! empty($conf->global->SOCIETE_FISCAL_MONTH_START)) ? $conf->global->SOCIETE_FISCAL_MONTH_START : 1;
    print monthArrayOrSelected($monthstart) . '</td></tr>';

    print "</table>";

    /*
     *  Options fiscale
     */
    print '<br>';
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("VATManagement").'</td><td>'.$langs->trans("Description").'</td>';
    print '<td align="right">&nbsp;</td>';
    print "</tr>\n";
    $var=true;

    $var=!$var;
    print "<tr ".$bc[$var]."><td width=\"140\"><label><input ".$bc[$var]." type=\"radio\" name=\"optiontva\" disabled value=\"reel\"".($conf->global->FACTURE_TVAOPTION != "franchise"?" checked":"")."> ".$langs->trans("VATIsUsed")."</label></td>";
    print '<td colspan="2">';
    print "<table>";
    print "<tr><td>".$langs->trans("VATIsUsedDesc")."</td></tr>";
    print "<tr><td><i>".$langs->trans("Example").': '.$langs->trans("VATIsUsedExampleFR")."</i></td></tr>\n";
    print "</table>";
    print "</td></tr>\n";

    $var=!$var;
    print "<tr ".$bc[$var]."><td width=\"140\"><label><input ".$bc[$var]." type=\"radio\" name=\"optiontva\" disabled value=\"franchise\"".($conf->global->FACTURE_TVAOPTION == "franchise"?" checked":"")."> ".$langs->trans("VATIsNotUsed")."</label></td>";
    print '<td colspan="2">';
    print "<table>";
    print "<tr><td>".$langs->trans("VATIsNotUsedDesc")."</td></tr>";
    print "<tr><td><i>".$langs->trans("Example").': '.$langs->trans("VATIsNotUsedExampleFR")."</i></td></tr>\n";
    print "</table>";
    print "</td></tr>\n";

    print "</table>";


    /*
     *  Local Taxes
     */
    if ($pays_code=='ES')
    {
        // Local Tax 1
        print '<br>';
        print '<table class="noborder" width="100%">';
        print '<tr class="liste_titre">';
        print '<td>'.$langs->transcountry("LocalTax1Management",$pays_code).'</td><td>'.$langs->trans("Description").'</td>';
        print '<td align="right">&nbsp;</td>';
        print "</tr>\n";
        $var=true;

        $var=!$var;
        print "<tr ".$bc[$var]."><td width=\"140\"><label><input ".$bc[$var]." type=\"radio\" name=\"optionlocaltax1\" disabled value=\"localtax1on\"".($conf->global->FACTURE_LOCAL_TAX1_OPTION != "localtax1off"?" checked":"")."> ".$langs->transcountry("LocalTax1IsUsed",$pays_code)."</label></td>";
        print '<td colspan="2">';
        print "<table>";
        print "<tr><td>".$langs->transcountry("LocalTax1IsUsedDesc",$pays_code)."</td></tr>";
        print "<tr><td><i>".$langs->trans("Example",$pays_code).': '.$langs->transcountry("LocalTax1IsUsedExample",$pays_code)."</i></td></tr>\n";
        print "</table>";
        print "</td></tr>\n";

        $var=!$var;
        print "<tr ".$bc[$var]."><td width=\"140\"><label><input ".$bc[$var]." type=\"radio\" name=\"optionlocaltax1\" disabled value=\"localtax1off\"".($conf->global->FACTURE_LOCAL_TAX1_OPTION == "localtax1off"?" checked":"")."> ".$langs->transcountry("LocalTax1IsNotUsed",$pays_code)."</label></td>";
        print '<td colspan="2">';
        print "<table>";
        print "<tr><td>".$langs->transcountry("LocalTax1IsNotUsedDesc",$pays_code)."</td></tr>";
        print "<tr><td><i>".$langs->trans("Example",$pays_code).': '.$langs->transcountry("LocalTax1IsNotUsedExample",$pays_code)."</i></td></tr>\n";
        print "</table>";
        print "</td></tr>\n";

        print "</table>";

        // Local Tax 2
        print '<br>';
        print '<table class="noborder" width="100%">';
        print '<tr class="liste_titre">';
        print '<td>'.$langs->transcountry("LocalTax2Management",$pays_code).'</td><td>'.$langs->trans("Description").'</td>';
        print '<td align="right">&nbsp;</td>';
        print "</tr>\n";
        $var=true;

        $var=!$var;
        print "<tr ".$bc[$var]."><td width=\"140\"><label><input ".$bc[$var]." type=\"radio\" name=\"optionlocaltax2\" disabled value=\"localtax2on\"".($conf->global->FACTURE_LOCAL_TAX2_OPTION != "localtax2off"?" checked":"")."> ".$langs->transcountry("LocalTax2IsUsed",$pays_code)."</label></td>";
        print '<td colspan="2">';
        print "<table>";
        print "<tr><td>".$langs->transcountry("LocalTax2IsUsedDesc",$pays_code)."</td></tr>";
        print "<tr><td><i>".$langs->trans("Example").': '.$langs->transcountry("LocalTax2IsUsedExample",$pays_code)."</i></td></tr>\n";
        print "</table>";
        print "</td></tr>\n";

        $var=!$var;
        print "<tr ".$bc[$var]."><td width=\"140\"><label><input ".$bc[$var]." type=\"radio\" name=\"optionlocaltax2\" disabled value=\"localtax2off\"".($conf->global->FACTURE_LOCAL_TAX2_OPTION == "localtax2off"?" checked":"")."> ".$langs->transcountry("LocalTax2IsNotUsed",$pays_code)."</label></td>";
        print '<td colspan="2">';
        print "<table>";
        print "<tr><td>".$langs->transcountry("LocalTax2IsNotUsedDesc",$pays_code)."</td></tr>";
        print "<tr><td><i>".$langs->trans("Example").': '.$langs->transcountry("LocalTax2IsNotUsedExample",$pays_code)."</i></td></tr>\n";
        print "</table>";
        print "</td></tr>\n";

        print "</table>";
    }


    // Actions buttons
    print '<div class="tabsAction">';
    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit">'.$langs->trans("Modify").'</a>';
    print '</div>';

    print '<br>';
}

$db->close();

llxFooter('$Date: 2011/02/09 12:03:49 $ - $Revision: 1.84.2.1 $');

?>