<?php
/* Copyright (C) 2001-2004 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2002-2003 Jean-Louis Bergamo   <jlb@j1b.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *       \file       htdocs/adherents/card_subscriptions.php
 *       \ingroup    member
 *       \brief      Onglet d'ajout, edition, suppression des adhesions d'un adherent
 *       \version    $Id: card_subscriptions.php,v 1.66.2.2 2011/02/26 10:22:47 eldy Exp $
 */

require("../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/lib/member.lib.php");
require_once(DOL_DOCUMENT_ROOT."/lib/date.lib.php");
require_once(DOL_DOCUMENT_ROOT."/adherents/class/adherent.class.php");
require_once(DOL_DOCUMENT_ROOT."/adherents/class/adherent_type.class.php");
require_once(DOL_DOCUMENT_ROOT."/adherents/class/adherent_options.class.php");
require_once(DOL_DOCUMENT_ROOT."/adherents/class/cotisation.class.php");
require_once(DOL_DOCUMENT_ROOT."/compta/bank/class/account.class.php");

$langs->load("companies");
$langs->load("bills");
$langs->load("members");
$langs->load("users");
$langs->load("mails");


// Security check
if (! $user->rights->adherent->cotisation->lire) accessforbidden();

$adh = new Adherent($db);
$adho = new AdherentOptions($db);
$adht = new AdherentType($db);
$errmsg='';

$defaultdelay=1;
$defaultdelayunit='y';

$action=GETPOST('action');
$rowid=GETPOST('rowid');
$typeid=GETPOST('typeid');

if ($rowid)
{
    // Load member
    $result = $adh->fetch($rowid);

    // Define variables to know what current user can do on users
    $canadduser=($user->admin || $user->rights->user->user->creer);
    // Define variables to know what current user can do on properties of user linked to edited member
    if ($adh->user_id)
    {
        // $user est le user qui edite, $adh->user_id est l'id de l'utilisateur lies au membre edite
        $caneditfielduser=( (($user->id == $adh->user_id) && $user->rights->user->self->creer)
        || (($user->id != $adh->user_id) && $user->rights->user->user->creer) );
        $caneditpassworduser=( (($user->id == $adh->user_id) && $user->rights->user->self->password)
        || (($user->id != $adh->user_id) && $user->rights->user->user->password) );
    }
}

// Define variables to know what current user can do on members
$canaddmember=$user->rights->adherent->creer;
// Define variables to know what current user can do on properties of a member
if ($rowid)
{
    $caneditfieldmember=$user->rights->adherent->creer;
}

// Define size of logo small and mini (might be set into other pages)
$maxwidthsmall=270;$maxheightsmall=150;
$maxwidthmini=128;$maxheightmini=72;
$quality = 80;



/*
 * 	Actions
 */


if ($_POST['action'] == 'setuserid' && ($user->rights->user->self->creer || $user->rights->user->user->creer))
{
    $error=0;
    if (empty($user->rights->user->user->creer))    // If can edit only itself user, we can link to itself only
    {
        if ($_POST["userid"] != $user->id && $_POST["userid"] != $adh->user_id)
        {
            $error++;
            $mesg='<div class="error">'.$langs->trans("ErrorUserPermissionAllowsToLinksToItselfOnly").'</div>';
        }
    }

    if (! $error)
    {
        if ($_POST["userid"] != $adh->user_id)  // If link differs from currently in database
        {
            $result=$adh->setUserId($_POST["userid"]);
            if ($result < 0) dol_print_error($adh->db,$adh->error);
            $_POST['action']='';
            $action='';
        }
    }
}

if ($_POST['action'] == 'setsocid')
{
    $error=0;
    if (! $error)
    {
        if (GETPOST("socid") != $adh->fk_soc)    // If link differs from currently in database
        {
            $sql ="SELECT rowid FROM ".MAIN_DB_PREFIX."adherent";
            $sql.=" WHERE fk_soc = '".GETPOST("socid")."'";
            $resql = $db->query($sql);
            if ($resql)
            {
                $obj = $db->fetch_object($resql);
                if ($obj && $obj->rowid > 0)
                {
                    $othermember=new Adherent($db);
                    $othermember->fetch($obj->rowid);
                    $thirdparty=new Societe($db);
                    $thirdparty->fetch(GETPOST("socid"));
                    $error++;
                    $mesg='<div class="error">'.$langs->trans("ErrorMemberIsAlreadyLinkedToThisThirdParty",$othermember->getFullName($langs),$othermember->login,$thirdparty->nom).'</div>';
                }
            }

            if (! $error)
            {
                $result=$adh->setThirdPartyId(GETPOST("socid"));
                if ($result < 0) dol_print_error($adh->db,$adh->error);
                $_POST['action']='';
                $action='';
            }
        }
    }
}

if ($user->rights->adherent->cotisation->creer && $_POST["action"] == 'cotisation' && ! $_POST["cancel"])
{
    $error=0;

    $langs->load("banks");

    $result=$adh->fetch($rowid);
    $result=$adht->fetch($adh->typeid);

    // Subscription informations
    $datecotisation=0;
    $datesubend=0;
    if ($_POST["reyear"] && $_POST["remonth"] && $_POST["reday"])
    {
        $datecotisation=dol_mktime(0, 0, 0, $_POST["remonth"], $_POST["reday"], $_POST["reyear"]);
    }
    if ($_POST["endyear"] && $_POST["endmonth"] && $_POST["endday"])
    {
        $datesubend=dol_mktime(0, 0, 0, $_POST["endmonth"], $_POST["endday"], $_POST["endyear"]);
    }
    $cotisation=$_POST["cotisation"];	// Amount of subscription
    $label=$_POST["label"];

    if (! $datecotisation)
    {
        $error++;
        $errmsg=$langs->trans("BadDateFormat");
        $action='addsubscription';
    }
    if (! $datesubend)
    {
        $datesubend=dol_time_plus_duree(dol_time_plus_duree($datecotisation,$defaultdelay,$defaultdelayunit),-1,'d');
    }

    // Payment informations
    $accountid=$_POST["accountid"];
    $operation=$_POST["operation"];	// Payment mode
    $num_chq=$_POST["num_chq"];
    $emetteur_nom=$_POST["chqemetteur"];
    $emetteur_banque=$_POST["chqbank"];
    $option=$_POST["paymentsave"];
    if (empty($option)) $option='none';

    // Check if a payment is mandatory or not
    if (! $error && $adht->cotisation)	// Type adherent soumis a cotisation
    {
        if (! is_numeric($_POST["cotisation"]))
        {
            // If field is '' or not a numeric value
            $errmsg=$langs->trans("ErrorFieldRequired",$langs->transnoentities("Amount"));
            $error++;
            $action='addsubscription';
        }
        else
        {
            if ($conf->banque->enabled && $_POST["paymentsave"] != 'none')
            {
                if ($_POST["cotisation"])
                {
                    if (! $_POST["label"])     $errmsg=$langs->trans("ErrorFieldRequired",$langs->transnoentities("Label"));
                    if ($_POST["paymentsave"] != 'invoiceonly' && ! $_POST["operation"]) $errmsg=$langs->trans("ErrorFieldRequired",$langs->transnoentities("PaymentMode"));
                    if ($_POST["paymentsave"] != 'invoiceonly' && ! $_POST["accountid"]) $errmsg=$langs->trans("ErrorFieldRequired",$langs->transnoentities("FinancialAccount"));
                }
                else
                {
                    if ($_POST["accountid"])   $errmsg=$langs->trans("ErrorDoNotProvideAccountsIfNullAmount");
                }
                if ($errmsg) $action='addsubscription';
            }
        }
    }

    if (! $error && $action=='cotisation')
    {
        $db->begin();

        // Create subscription
        $crowid=$adh->cotisation($datecotisation, $cotisation, $accountid, $operation, $label, $num_chq, $emetteur_nom, $emetteur_banque, $datesubend, $option);
        if ($crowid <= 0)
        {
            $error++;
            $errmsg=$adh->error;
        }

        if (! $error)
        {
            // Insert into bank account directlty (if option choosed for) + link to llx_cotisation if option is 'bankdirect'
            if ($option == 'bankdirect' && $accountid)
            {
                require_once(DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php');

                $acct=new Account($db);
                $result=$acct->fetch($accountid);

                $dateop=dol_now();

                $insertid=$acct->addline($dateop, $operation, $label, $cotisation, $num_chq, '', $user, $emetteur_nom, $emetteur_banque);
                if ($insertid > 0)
                {
                    $inserturlid=$acct->add_url_line($insertid, $adh->id, DOL_URL_ROOT.'/adherents/fiche.php?rowid=', $adh->getFullname($langs), 'member');
                    if ($inserturlid > 0)
                    {
                        // Met a jour la table cotisation
                        $sql ="UPDATE ".MAIN_DB_PREFIX."cotisation SET fk_bank=".$insertid;
                        $sql.=" WHERE rowid=".$crowid;

                        dol_syslog("Adherent::cotisation sql=".$sql);
                        $resql = $db->query($sql);
                        if (! $resql)
                        {
                            $error=$db->lasterror();
                        }
                    }
                    else
                    {
                        $error=$acct->error;
                    }
                }
                else
                {
                    $error=$acct->error;
                }
            }

            // If option choosed, we create invoice
            if (($option == 'bankviainvoice' && $accountid) || $option == 'invoiceonly')
            {
                require_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php");
                require_once(DOL_DOCUMENT_ROOT."/compta/facture/class/paymentterm.class.php");

                $invoice=new Facture($db);
                $customer=new Societe($db);
                $result=$customer->fetch($adh->fk_soc);
                if ($result <= 0)
                {
                    $errmsg=$customer->error;
                    $error++;
                }

                // Create draft invoice
                $invoice->type=0;
                $invoice->cond_reglement_id=$customer->cond_reglement_id;
                if (empty($invoice->cond_reglement_id))
                {
                    $paymenttermstatic=new PaymentTerm($db);
                    $invoice->cond_reglement_id=$paymenttermstatic->getDefaultId();
                    if (empty($invoice->cond_reglement_id))
                    {
                        $error++;
                        $errmsg='ErrorNoPaymentTermRECEPFound';
                    }
                }
                $invoice->socid=$adh->fk_soc;
                $invoice->date=$datecotisation;

                $result=$invoice->create($user);
                if ($result <= 0)
                {
                    $errmsg=$invoice->error;
                    $error++;
                }

                // Add line to draft invoice
                $idprodsubscription=0;
                $vattouse=get_default_tva($mysoc, $customer, $idprodsubscription);
                //print xx".$vattouse." - ".$mysoc." - ".$customer;exit;
                $result=$invoice->addline($invoice->id,$label,0,1,$vattouse,0,0,$idprodsubscription,0,$datecotisation,$datesubend,0,0,'','TTC',$cotisation,1);
                if ($result <= 0)
                {
                    $errmsg=$invoice->error;
                    $error++;
                }

                // Validate invoice
                $result=$invoice->validate($user);

                // Add payment on invoice
                if ($option == 'bankviainvoice' && $accountid)
                {
                    require_once(DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php');
                    require_once(DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php');

                    // Creation de la ligne paiement
                    $amounts[$invoice->id] = price2num($cotisation);
                    $paiement = new Paiement($db);
                    $paiement->datepaye     = $datecotisation;
                    $paiement->amounts      = $amounts;
                    $paiement->paiementid   = $operation;
                    $paiement->num_paiement = $num_chq;
                    $paiement->note         = $label;

                    if (! $error)
                    {
                        $paiement_id = $paiement->create($user);
                        if (! $paiement_id > 0)
                        {
                            $errmsg=$paiement->error;
                            $error++;
                        }
                    }

                    if (! $error)
                    {
                        $bank_line_id=$paiement->addPaymentToBank($user,'payment','(SubscriptionPayment)',$accountid,$emetteur_nom,$emetteur_banque);
                        if (! $bank_line_id > 0)
                        {
                            $errmsg=$paiement->error;
                            $error++;
                        }

                        // Update fk_bank for subscriptions
                        $sql = 'UPDATE llx_cotisation set fk_bank='.$bank_line_id;
                        $sql.= ' WHERE rowid='.$crowid;
                        dol_syslog('sql='.$sql);
                        $result = $db->query($sql);
                        if (! $result)
                        {
                            $error++;
                        }
                    }
                }
            }
        }

        if (! $error)
        {
            $db->commit();
        }
        else
        {
            $db->rollback();
            $action = 'addsubscription';
        }

        // Send email
        if (! $error)
        {
            // Send confirmation Email
            if ($adh->email && $_POST["sendmail"])
            {
                $subjecttosend=$adh->makeSubstitution($conf->global->ADHERENT_MAIL_COTIS_SUBJECT);
                $texttosend=$adh->makeSubstitution($adht->getMailOnSubscription());

                $result=$adh->send_an_email($texttosend,$subjecttosend,array(),array(),array(),"","",0,-1);
                if ($result < 0) $errmsg=$adh->error;
            }

            $_POST["cotisation"]='';
            $_POST["accountid"]='';
            $_POST["operation"]='';
            $_POST["label"]='';
            $_POST["num_chq"]='';
        }
    }
}



/*
 * View
 */

$html = new Form($db);

$now=dol_now();

llxHeader('',$langs->trans("Subscriptions"),'EN:Module_Foundations|FR:Module_Adh&eacute;rents|ES:M&oacute;dulo_Miembros');

if ($rowid)
{
    $result=$adh->fetch($rowid);
    $result=$adh->fetch_optionals($rowid);

    $adht->fetch($adh->typeid);

    // fetch optionals attributes and labels
    $adho->fetch_optionals();


    $head = member_prepare_head($adh);

    dol_fiche_head($head, 'subscription', $langs->trans("Member"), 0, 'user');

    print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="rowid" value="'.$adh->id.'">';
    print '<table class="border" width="100%">';

    // Ref
    print '<tr><td width="20%">'.$langs->trans("Ref").'</td>';
    print '<td class="valeur">';
    print $html->showrefnav($adh,'rowid');
    print '</td></tr>';

    // Nom
    print '<tr><td>'.$langs->trans("Lastname").'</td><td class="valeur">'.$adh->nom.'&nbsp;</td>';
    print '</tr>';

    // Prenom
    print '<tr><td>'.$langs->trans("Firstname").'</td><td class="valeur">'.$adh->prenom.'&nbsp;</td>';
    print '</tr>';

    // Login
    print '<tr><td>'.$langs->trans("Login").'</td><td class="valeur">'.$adh->login.'&nbsp;</td></tr>';

    // Type
    print '<tr><td>'.$langs->trans("Type").'</td><td class="valeur">'.$adht->getNomUrl(1)."</td></tr>\n";

    // Status
    print '<tr><td>'.$langs->trans("Status").'</td><td class="valeur">'.$adh->getLibStatut(4).'</td></tr>';

    // Date end subscription
    print '<tr><td>'.$langs->trans("SubscriptionEndDate").'</td><td class="valeur">';
    if ($adh->datefin)
    {
        print dol_print_date($adh->datefin,'day');
        if ($adh->datefin < ($now -  $conf->adherent->cotisation->warning_delay) && $adh->statut > 0) print " ".img_warning($langs->trans("Late")); // Affiche picto retard uniquement si non brouillon et non resilie
    }
    else
    {
        print $langs->trans("SubscriptionNotReceived");
        if ($adh->statut > 0) print " ".img_warning($langs->trans("Late")); // Affiche picto retard uniquement si non brouillon et non resilie
    }
    print '</td></tr>';

    // Third party Dolibarr
    if ($conf->societe->enabled)
    {
        print '<tr><td>';
        print '<table class="nobordernopadding" width="100%"><tr><td>';
        print $langs->trans("LinkedToDolibarrThirdParty");
        print '</td>';
        if ($_GET['action'] != 'editthirdparty' && $user->rights->adherent->creer) print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editthirdparty&amp;rowid='.$adh->id.'">'.img_edit($langs->trans('SetLinkToThirdParty'),1).'</a></td>';
        print '</tr></table>';
        print '</td><td class="valeur">';
        if ($_GET['action'] == 'editthirdparty')
        {
            $htmlname='socid';
            print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" name="form'.$htmlname.'">';
            print '<input type="hidden" name="rowid" value="'.$adh->id.'">';
            print '<input type="hidden" name="action" value="set'.$htmlname.'">';
            print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
            print '<table class="nobordernopadding" cellpadding="0" cellspacing="0">';
            print '<tr><td>';
            print $html->select_societes($adh->fk_soc,'socid','',1);
            print '</td>';
            print '<td align="left"><input type="submit" class="button" value="'.$langs->trans("Modify").'"></td>';
            print '</tr></table></form>';
        }
        else
        {
            if ($adh->fk_soc)
            {
                $company=new Societe($db);
                $result=$company->fetch($adh->fk_soc);
                print $company->getNomUrl(1);
            }
            else
            {
                print $langs->trans("NoThirdPartyAssociatedToMember");
            }
        }
        print '</td></tr>';
    }

    // Login Dolibarr
    print '<tr><td>';
    print '<table class="nobordernopadding" width="100%"><tr><td>';
    print $langs->trans("LinkedToDolibarrUser");
    print '</td>';
    if ($_GET['action'] != 'editlogin' && $user->rights->adherent->creer) print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editlogin&amp;rowid='.$adh->id.'">'.img_edit($langs->trans('SetLinkToUser'),1).'</a></td>';
    print '</tr></table>';
    print '</td><td class="valeur">';
    if ($_GET['action'] == 'editlogin')
    {
        /*$include=array();
         if (empty($user->rights->user->user->creer))    // If can edit only itself user, we can link to itself only
         {
         $include=array($adh->user_id,$user->id);
         }*/
        print $html->form_users($_SERVER['PHP_SELF'].'?rowid='.$adh->id,$adh->user_id,'userid','');
    }
    else
    {
        if ($adh->user_id)
        {
            print $html->form_users($_SERVER['PHP_SELF'].'?rowid='.$adh->id,$adh->user_id,'none');
        }
        else print $langs->trans("NoDolibarrAccess");
    }
    print '</td></tr>';

    print "</table>\n";
    print '</form>';

    dol_fiche_end();


    if ($errmsg)
    {
        if (preg_match('/^Error/i',$errmsg))
        {
            $langs->load("errors");
            $errmsg=$langs->trans($errmsg);
        }
        print '<div class="error">'.$errmsg.'</div>'."\n";
    }


    /*
     * Barre d'actions
     */

    // Lien nouvelle cotisation si non brouillon et non resilie
    if ($user->rights->adherent->cotisation->creer)
    {
        if ($action != 'addsubscription')
        {
            print '<div class="tabsAction">';

            if ($adh->statut > 0) print "<a class=\"butAction\" href=\"card_subscriptions.php?rowid=$rowid&action=addsubscription\">".$langs->trans("AddSubscription")."</a>";
            else print '<a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("ValidateBefore")).'">'.$langs->trans("AddSubscription").'</a>';

            print "<br>\n";

            print '</div>';
            print '<br>';
        }
    }


    /*
     * List of subscriptions
     */
    if ($action != 'addsubscription')
    {
        $sql = "SELECT d.rowid, d.prenom, d.nom, d.societe,";
        $sql.= " c.rowid as crowid, c.cotisation,";
        $sql.= " c.dateadh,";
        $sql.= " c.datef,";
        $sql.= " c.fk_bank,";
        $sql.= " b.rowid as bid,";
        $sql.= " ba.rowid as baid, ba.label, ba.bank";
        $sql.= " FROM ".MAIN_DB_PREFIX."adherent as d, ".MAIN_DB_PREFIX."cotisation as c";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."bank as b ON c.fk_bank = b.rowid";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."bank_account as ba ON b.fk_account = ba.rowid";
        $sql.= " WHERE d.rowid = c.fk_adherent AND d.rowid=".$rowid;

        $result = $db->query($sql);
        if ($result)
        {
            $cotisationstatic=new Cotisation($db);
            $accountstatic=new Account($db);

            $num = $db->num_rows($result);
            $i = 0;

            print "<table class=\"noborder\" width=\"100%\">\n";

            print '<tr class="liste_titre">';
            print '<td>'.$langs->trans("Ref").'</td>';
            print '<td align="center">'.$langs->trans("DateSubscription").'</td>';
            print '<td align="center">'.$langs->trans("DateEnd").'</td>';
            print '<td align="right">'.$langs->trans("Amount").'</td>';
            if ($conf->banque->enabled)
            {
                print '<td align="right">'.$langs->trans("Account").'</td>';
            }
            print "</tr>\n";

            $var=True;
            while ($i < $num)
            {
                $objp = $db->fetch_object($result);
                $var=!$var;
                print "<tr $bc[$var]>";
                $cotisationstatic->ref=$objp->crowid;
                $cotisationstatic->id=$objp->crowid;
                print '<td>'.$cotisationstatic->getNomUrl(1).'</td>';
                print '<td align="center">'.dol_print_date($db->jdate($objp->dateadh),'day')."</td>\n";
                print '<td align="center">'.dol_print_date($db->jdate($objp->datef),'day')."</td>\n";
                print '<td align="right">'.price($objp->cotisation).'</td>';
                if ($conf->banque->enabled)
                {
                    print '<td align="right">';
                    if ($objp->bid)
                    {
                        $accountstatic->label=$objp->label;
                        $accountstatic->id=$objp->baid;
                        print $accountstatic->getNomUrl(1);
                    }
                    else
                    {
                        print '&nbsp;';
                    }
                    print '</td>';
                }
                print "</tr>";
                $i++;
            }
            print "</table>";
        }
        else
        {
            dol_print_error($db);
        }
    }



    /*
     * Add new subscription form
     */
    if ($action == 'addsubscription' && $user->rights->adherent->cotisation->creer)
    {
        print '<br>';

        print_fiche_titre($langs->trans("NewCotisation"));

        $bankdirect=0;        // Option to write to bank is on by default
        $bankviainvoice=0;    // Option to write via invoice is on by default
        $invoiceonly=0;
        if ($conf->banque->enabled && $conf->global->ADHERENT_BANK_USE && (empty($_POST['paymentsave']) || $_POST["paymentsave"] == 'bankdirect')) $bankdirect=1;
        if ($conf->banque->enabled && $conf->societe->enabled && $conf->facture->enabled && $adh->fk_soc) $bankviainvoice=1;

        print "\n\n<!-- Form add subscription -->\n";

        if ($conf->use_javascript_ajax)
        {
            print "\n".'<script type="text/javascript" language="javascript">';
            print 'jQuery(document).ready(function () {
                        jQuery(".bankswitchclass").'.($bankdirect||$bankviainvoice?'show()':'hide()').';
                        jQuery("#none").click(function() {
                            jQuery(".bankswitchclass").hide();
                            jQuery(".bankswitchclass2").hide();
                        });
                        jQuery("#bankdirect").click(function() {
                            jQuery(".bankswitchclass").show();
                            jQuery(".bankswitchclass2").show();
                        });
                        jQuery("#bankviainvoice").click(function() {
                            jQuery(".bankswitchclass").show();
                            jQuery(".bankswitchclass2").show();
                        });
    	                jQuery("#invoiceonly").click(function() {
                            jQuery(".bankswitchclass").hide();
                            jQuery(".bankswitchclass2").hide();
                        });
                        ';
            if (GETPOST('paymentsave')) print 'jQuery("#'.GETPOST('paymentsave').'").attr(\'checked\',true);';
    	    print '});';
            print '</script>'."\n";
        }

        print '<form name="cotisation" method="post" action="'.$_SERVER["PHP_SELF"].'">';
        print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
        print '<input type="hidden" name="action" value="cotisation">';
        print '<input type="hidden" name="rowid" value="'.$rowid.'">';
        print "<table class=\"border\" width=\"100%\">\n";

        $today=mktime();
        $datefrom=0;
        $dateto=0;

        // Date start subscription
        print '<tr><td width="30%" class="fieldrequired">'.$langs->trans("DateSubscription").'</td><td>';
        if ($_POST["reday"])
        {
            $datefrom=dol_mktime(0,0,0,$_POST["remonth"],$_POST["reday"],$_POST["reyear"]);
        }
        if (! $datefrom)
        {
            if ($adh->datefin > 0)
            {
                $datefrom=dol_time_plus_duree($adh->datefin,1,'d');
            }
            else
            {
                $datefrom=mktime();
            }
        }
        $html->select_date($datefrom,'','','','',"cotisation");
        print "</td></tr>";

        // Date end subscription
        if ($_POST["endday"])
        {
            $dateto=dol_mktime(0,0,0,$_POST["endmonth"],$_POST["endday"],$_POST["endyear"]);
        }
        if (! $dateto)
        {
            $dateto=-1;		// By default, no date is suggested
        }
        print '<tr><td>'.$langs->trans("DateEndSubscription").'</td><td>';
        $html->select_date($dateto,'end','','','',"cotisation");
        print "</td></tr>";

        if ($adht->cotisation)
        {
            // Amount
            print '<tr><td class="fieldrequired">'.$langs->trans("Amount").'</td><td><input type="text" name="cotisation" size="6" value="'.$_POST["cotisation"].'"> '.$langs->trans("Currency".$conf->monnaie).'</td></tr>';

            // Label
            print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td>';
            print '<td><input name="label" type="text" size="32" value="'.$langs->trans("Subscription").' ';
            print dol_print_date(($datefrom?$datefrom:time()),"%Y").'" ></td></tr>';

            // Complementary action
            if ($conf->banque->enabled || $conf->facture->enabled)
            {
                $company=new Societe($db);
                if ($adh->fk_soc)
                {
                    $result=$company->fetch($adh->fk_soc);
                }

                // Title payments
                //print '<tr><td colspan="2"><b>'.$langs->trans("Payment").'</b></td></tr>';

                // Define a way to write payment
                print '<tr><td valign="top" class="fieldrequired">'.$langs->trans('MoreActions');
                print '</td>';
                print '<td>';
                print '<input type="radio" class="moreaction" id="none" name="paymentsave" value="none"'.(!$bankdirect&&!$bankviainvoice?' checked="true"':'').'> '.$langs->trans("None").'<br>';
                if ($conf->banque->enabled)
                {
                    print '<input type="radio" class="moreaction" id="bankdirect" name="paymentsave" value="bankdirect"'.($bankdirect?' checked="true"':'');
                    print '> '.$langs->trans("MoreActionBankDirect").'<br>';
                }
                if ($conf->banque->enabled && $conf->societe->enabled && $conf->facture->enabled)
                {
                    print '<input type="radio" class="moreaction" id="bankviainvoice" name="paymentsave" value="bankviainvoice"'.($bankviainvoice?' checked="true"':'');
                    if (empty($adh->fk_soc) || empty($bankviainvoice)) print ' disabled="true"';
                    print '> '.$langs->trans("MoreActionBankViaInvoice");
                    if ($adh->fk_soc) print ' ('.$langs->trans("ThirdParty").': '.$company->getNomUrl(1).')';
                    else print ' ('.$langs->trans("NoThirdPartyAssociatedToMember").')';
                    print '<br>';
                }
                if ($conf->societe->enabled && $conf->facture->enabled)
                {
                    print '<input type="radio" class="moreaction" id="invoiceonly" name="paymentsave" value="invoiceonly"'.($invoiceonly?' checked="true"':'');
                    if (empty($adh->fk_soc) || empty($bankviainvoice)) print ' disabled="true"';
                    print '> '.$langs->trans("MoreActionInvoiceOnly");
                    if ($adh->fk_soc) print ' ('.$langs->trans("ThirdParty").': '.$company->getNomUrl(1).')';
                    else print ' ('.$langs->trans("NoThirdPartyAssociatedToMember").')';
                    print '<br>';
                }
                print '</td></tr>';

                // Bank account
                print '<tr class="bankswitchclass"><td class="fieldrequired">'.$langs->trans("FinancialAccount").'</td><td>';
                $html->select_comptes($_POST["accountid"],'accountid',0,'',1);
                print "</td></tr>\n";

                // Payment mode
                print '<tr class="bankswitchclass"><td class="fieldrequired">'.$langs->trans("PaymentMode").'</td><td>';
                $html->select_types_paiements($_POST["operation"],'operation');
                print "</td></tr>\n";

                print '<tr class="bankswitchclass2"><td>'.$langs->trans('Numero');
                print ' <em>('.$langs->trans("ChequeOrTransferNumber").')</em>';
                print '</td>';
                print '<td><input id="fieldnum_chq" name="num_chq" type="text" size="8" value="'.(empty($_POST['num_chq'])?'':$_POST['num_chq']).'"></td></tr>';

                print '<tr class="bankswitchclass2"><td>'.$langs->trans('CheckTransmitter');
                print ' <em>('.$langs->trans("ChequeMaker").')</em>';
                print '</td>';
                print '<td><input id="fieldchqemetteur" name="chqemetteur" size="32" type="text" value="'.(empty($_POST['chqemetteur'])?$facture->client->nom:$_POST['chqemetteur']).'"></td></tr>';

                print '<tr class="bankswitchclass2"><td>'.$langs->trans('Bank');
                print ' <em>('.$langs->trans("ChequeBank").')</em>';
                print '</td>';
                print '<td><input id="chqbank" name="chqbank" size="32" type="text" value="'.(empty($_POST['chqbank'])?'':$_POST['chqbank']).'"></td></tr>';
            }
        }

        print '<tr><td colspan="2">&nbsp;</td>';

        print '<tr><td width="30%">'.$langs->trans("SendAcknowledgementByMail").'</td>';
        print '<td>';
        if (! $adh->email)
        {
            print $langs->trans("NoEMail");
        }
        else
        {
            $adht = new AdherentType($db);
            $adht->fetch($adh->typeid);

            $subjecttosend=$adh->makeSubstitution($conf->global->ADHERENT_MAIL_COTIS_SUBJECT);
            $texttosend=$adh->makeSubstitution($adht->getMailOnSubscription());

            $tmp='<input name="sendmail" type="checkbox"'.($conf->global->ADHERENT_DEFAULT_SENDINFOBYMAIL?' checked="true"':'').'>';
            $helpcontent='';
            $helpcontent.='<b>'.$langs->trans("MailFrom").'</b>: '.$conf->global->ADHERENT_MAIL_FROM.'<br>'."\n";
            $helpcontent.='<b>'.$langs->trans("MailRecipient").'</b>: '.$adh->email.'<br>'."\n";
            $helpcontent.='<b>'.$langs->trans("Subject").'</b>:<br>'."\n";
            $helpcontent.=$subjecttosend."\n";
            $helpcontent.="<br>";
            $helpcontent.='<b>'.$langs->trans("Content").'</b>:<br>';
            $helpcontent.=dol_htmlentitiesbr($texttosend)."\n";

            print $html->textwithpicto($tmp,$helpcontent,1,'help');
        }
        print '</td></tr>';
        print '</table>';
        print '<br>';

        print '<center>';
        print '<input type="submit" class="button" name="add" value="'.$langs->trans("AddSubscription").'">';
        print ' &nbsp; &nbsp; ';
        print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'">';
        print '</center>';

        print '</form>';

        print "\n<!-- End form subscription -->\n\n";
    }

    //print '</td></tr>';
    //print '</table>';
}
else
{
    $langs->load("errors");
    print $langs->trans("ErrorRecordNotFound");
}

$db->close();

llxFooter('$Date: 2011/02/26 10:22:47 $ - $Revision: 1.66.2.2 $');
?>
