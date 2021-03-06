<?PHP
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2010-2011 Juanjo Menent 		<jmenent@2byte.es>
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
 *	\file       htdocs/compta/prelevement/fiche.php
 *	\ingroup    prelevement
 *	\brief      Fiche prelevement
 *	\version    $Id: fiche.php,v 1.55.2.1 2011/04/01 08:13:47 simnandez Exp $
 */

require('../../main.inc.php');
require_once(DOL_DOCUMENT_ROOT."/lib/prelevement.lib.php");
require_once(DOL_DOCUMENT_ROOT."/compta/prelevement/class/bon-prelevement.class.php");

if (!$user->rights->prelevement->bons->lire)
accessforbidden();

$langs->load("bills");
$langs->load("withdrawals");
$langs->load("categories");


// Security check
if ($user->societe_id > 0) accessforbidden();


/*
 * Actions
 */

if (GETPOST("action") == 'confirm_credite' && GETPOST("confirm") == 'yes')
{
	$bon = new BonPrelevement($db,"");
	$bon->id = $_GET["id"];
	$bon->set_credite();

	Header("Location: fiche.php?id=".$_GET["id"]);
	exit;
}

if (GETPOST("action") == 'infotrans' && $user->rights->prelevement->bons->send)
{
	require_once(DOL_DOCUMENT_ROOT."/lib/files.lib.php");

	$bon = new BonPrelevement($db,"");
	$bon->fetch($_GET["id"]);

	if ($_FILES['userfile']['name'] && basename($_FILES['userfile']['name'],".ps") == $bon->ref)
	{
		$dir = $conf->prelevement->dir_output.'/receipts';

		if (dol_move_uploaded_file($_FILES['userfile']['tmp_name'], $dir . "/" . $_FILES['userfile']['name'],1) > 0)
		{
			$dt = dol_mktime(12,0,0,$_POST["remonth"],$_POST["reday"],$_POST["reyear"]);

			$bon->set_infotrans($user, $dt, $_POST["methode"]);
		}

		Header("Location: fiche.php?id=".$_GET["id"]);
        exit;
	}
	else
	{
		dol_syslog("Fichier invalide",LOG_WARNING);
		$mesg='BadFile';
	}
}

if (GETPOST("action") == 'infocredit' && $user->rights->prelevement->bons->credit)
{
	$bon = new BonPrelevement($db,"");
	$bon->fetch($_GET["id"]);
	$dt = mktime(12,0,0,$_POST["remonth"],$_POST["reday"],$_POST["reyear"]);

	$error = $bon->set_infocredit($user, $dt);

	if ($error == 0)
	{
		Header("Location: fiche.php?id=".$_GET["id"]);
	}
	else
	{
		Header("Location: fiche.php?id=".$_GET["id"]."&error=$error");
	}
	exit;
}


/*
 * View
 */

llxHeader('',$langs->trans("WithdrawalReceipt"));

$html = new Form($db);

if ($_GET["id"])
{
	$bon = new BonPrelevement($db,"");

	if ($bon->fetch($_GET["id"]) == 0)
	{
		$head = prelevement_prepare_head($bon);
		dol_fiche_head($head, 'prelevement', $langs->trans("WithdrawalReceipt"), '', 'payment');

		if (isset($_GET["error"]))
		{
			print '<div class="error">'.$bon->ReadError($_GET["error"]).'</div>';
		}

		if ($_GET["action"] == 'credite')
		{
			$ret=$html->form_confirm("fiche.php?id=".$bon->id,$langs->trans("ClassCredited"),$langs->trans("ClassCreditedConfirm"),"confirm_credite",'',1,1);
			if ($ret == 'html') print '<br>';
		}

		print '<table class="border" width="100%">';

		print '<tr><td width="20%">'.$langs->trans("Ref").'</td><td>'.$bon->getNomUrl(1).'</td></tr>';
		print '<tr><td width="20%">'.$langs->trans("Date").'</td><td>'.dol_print_date($bon->datec,'day').'</td></tr>';
		print '<tr><td width="20%">'.$langs->trans("Amount").'</td><td>'.price($bon->amount).'</td></tr>';
		print '<tr><td width="20%">'.$langs->trans("File").'</td><td>';

		$relativepath = 'receipts/'.$bon->ref;

		print '<a href="'.DOL_URL_ROOT.'/document.php?type=text/plain&amp;modulepart=prelevement&amp;file='.urlencode($relativepath).'">'.$relativepath.'</a>';

		print '</td></tr>';

		// Status
		print '<tr><td width="20%">'.$langs->trans('Status').'</td>';
		print '<td>'.$bon->getLibStatut(1).'</td>';
		print '</tr>';
		
		if($bon->date_trans <> 0)
		{
			$muser = new User($db);
			$muser->fetch($bon->user_trans);

			print '<tr><td width="20%">'.$langs->trans("TransData").'</td><td>';
			print dol_print_date($bon->date_trans,'day');
			print ' '.$langs->trans("By").' '.$muser->getFullName($langs).'</td></tr>';
			print '<tr><td width="20%">'.$langs->trans("TransMetod").'</td><td>';
			print $bon->methodes_trans[$bon->method_trans];
			print '</td></tr>';
		}
		if($bon->date_credit <> 0)
		{
			print '<tr><td width="20%">'.$langs->trans('CreditDate').'</td><td>';
			print dol_print_date($bon->date_credit,'day');
			print '</td></tr>';
		}

		print '</table>';
		dol_fiche_end();

		if($bon->date_trans == 0  && $user->rights->prelevement->bons->send)
		{
			print '<form method="post" name="userfile" action="fiche.php?id='.$bon->id.'" enctype="multipart/form-data">';
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
			print '<input type="hidden" name="action" value="infotrans">';
			print '<table class="border" width="100%">';
			print '<tr class="liste_titre">';
			print '<td colspan="3">'.$langs->trans("NotifyTransmision").'</td></tr>';
			print '<tr><td width="20%">'.$langs->trans("TransData").'</td><td>';
			print $html->select_date('','','','','',"userfile");
			print '</td></tr>';
			print '<tr><td width="20%">'.$langs->trans("TransMetod").'</td><td>';
			print $html->selectarray("methode",$bon->methodes_trans);
			print '</td></tr>';
			print '<tr><td width="20%">'.$langs->trans("File").'</td><td>';
			print '<input type="hidden" name="max_file_size" value="'.$conf->maxfilesize.'">';
			print '<input class="flat" type="file" name="userfile"><br>';
			print '</td></tr>';
			print '</table><br>';
			print '<center><input type="submit" class="button" value="'.$langs->trans("Send").'">';
			print '</form>';
		}

		if($bon->date_trans <> 0 && $bon->date_credit == 0 && $user->rights->prelevement->bons->credit)
		{
			print '<form name="infocredit" method="post" action="fiche.php?id='.$bon->id.'">';
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
			print '<input type="hidden" name="action" value="infocredit">';
			print '<table class="border" width="100%">';
			print '<tr class="liste_titre">';
			print '<td colspan="3">'.$langs->trans("NotifyCredit").'</td></tr>';
			print '<tr><td width="20%">'.$langs->trans('CreditDate').'</td><td>';
			print $html->select_date('','','','','',"infocredit");
			print '</td></tr>';
			print '</table><br>';
			print '<center><input type="submit" class="button" value="'.$langs->trans("ClassCredited").'">';
			print '</form>';
		}
	}
	else
	{
		dol_print_error($db);
	}
}

/* ************************************************************************** */
/*                                                                            */
/* Barre d'action                                                             */
/*                                                                            */
/* ************************************************************************** */

/*
print "\n</div>\n<div class=\"tabsAction\">\n";

if ($bon->statut == 0)
{
	print "<a class=\"butAction\" href=\"fiche.php?action=credite&amp;id=$bon->id\">".$langs->trans("ClassCredited")."</a>";
}
*/
print "</div>";

$db->close();

llxFooter('$Date: 2011/04/01 08:13:47 $ - $Revision: 1.55.2.1 $');
?>
