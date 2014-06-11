<?php
/* Copyright (C) 2001-2003 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004      Laurent Destailleur  <eldy@users.sourceforge.net>
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
 */

/**
 *      \file       htdocs/product/stats/index.php
 *      \brief      Page accueil statistiques produits
 *      \version    $Id: index.php,v 1.42 2010/08/14 02:44:25 eldy Exp $
 s*/

require("../../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/comm/propal/class/propal.class.php");

// Security check
if ($user->societe_id) $socid=$user->societe_id;
$result=restrictedArea($user,'produit|service');

llxHeader();

$mesg = '';

/*
 *
 *
 */
$sql = "SELECT count(*)";
$sql.= " FROM ".MAIN_DB_PREFIX."product as p";
$sql.= " WHERE p.fk_product_type <> 1";
if (!$user->rights->produit->hidden) $sql.=' AND (p.hidden=0 OR p.fk_product_type != 0)';
if (!$user->rights->service->hidden) $sql.=' AND (p.hidden=0 OR p.fk_product_type != 1)';
$sql.= " AND p.entity = ".$conf->entity;

if ($db->query($sql))
{
  $row = $db->fetch_row(0);
  $nbproduct = $row[0];
}
$db->free();


$sql = "SELECT count(*)";
$sql.= " FROM ".MAIN_DB_PREFIX."product as p";
$sql.= " WHERE p.tosell = 0";
if (!$user->rights->produit->hidden) $sql.=' AND (p.hidden=0 OR p.fk_product_type != 0)';
if (!$user->rights->service->hidden) $sql.=' AND (p.hidden=0 OR p.fk_product_type != 1)';
$sql.= " AND p.fk_product_type <> '1'";
$sql.= " AND p.entity = ".$conf->entity;

if ($db->query($sql))
{
  $row = $db->fetch_row(0);
  $nbhv = $row[0];
}
$db->free();

if ($conf->product->enabled && $conf->service->enabled)
{
	print_fiche_titre($langs->trans("ProductsAndServicesStatistics"), $mesg);
}
elseif ($conf->product->enabled)
{
	print_fiche_titre($langs->trans("ProductsStatistics"), $mesg);
}
elseif ($conf->service->enabled)
{
	print_fiche_titre($langs->trans("ServicesStatistics"), $mesg);
}

print '<br>';
print '<table class="noborder" width="100%">';

print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Summary").'</td><td>'.$langs->trans("Value").'</td>';
print "</tr>\n";

$var=True;
print "<tr ".$bc[$var].">";
print '<td width="40%">Nb de produit dans le catalogue</td>';
print '<td>'.$nbproduct.'</td></tr>';
$var=!$var;
print "<tr ".$bc[$var].">";
print '<td width="40%">Nb de produit dans le catalogue qui ne sont pas en vente</td>';
print '<td>'.$nbhv.'</td></tr>';

if ($conf->service->enabled)
{
  $sql = "SELECT count(*)";
  $sql.= " FROM ".MAIN_DB_PREFIX."product as p";
  $sql.= " WHERE p.fk_product_type = '1'";
  if (!$user->rights->produit->hidden) $sql.=' AND (p.hidden=0 OR p.fk_product_type != 0)';
  if (!$user->rights->service->hidden) $sql.=' AND (p.hidden=0 OR p.fk_product_type != 1)';
  $sql.= " AND p.entity = ".$conf->entity;

  if ($db->query($sql))
  {
    $row = $db->fetch_row(0);
    $nbproduct = $row[0];
  }
  $db->free();

  $sql = "SELECT count(*)";
  $sql.= " FROM ".MAIN_DB_PREFIX."product as p";
  $sql.= " WHERE p.tosell = 0";
  if (!$user->rights->produit->hidden) $sql.=' AND (p.hidden=0 OR p.fk_product_type != 0)';
  if (!$user->rights->service->hidden) $sql.=' AND (p.hidden=0 OR p.fk_product_type != 1)';
  $sql.= " AND p.fk_product_type = '1'";
  $sql.= " AND p.entity = ".$conf->entity;

  if ($db->query($sql))
  {
    $row = $db->fetch_row(0);
    $nbhv = $row[0];
  }
  $db->free();

  $var=!$var;
  print "<tr ".$bc[$var].">";
  print '<td width="40%">Nb de service dans le catalogue</td>';
  print '<td>'.$nbproduct.'</td></tr>';

  $var=!$var;
  print "<tr ".$bc[$var].">";
  print '<td width="40%">Nb de service dans le catalogue qui ne sont pas en vente</td>';
  print '<td>'.$nbhv.'</td></tr>';

  print '</table>';
}


// Stats des produits en factures, propale, ...
/*
print '<br>';
print '<table class="noborder" width="100%" cellspacing="0" cellpadding="4">';
print "<tr class=\"liste_titre\">";
print "<td>Produit/Service</td>";
print "<td>Qt� en facture</td>";
print "<td>Qt� en propale</td>";
print "</tr>\n";
$sql = "SELECT p.label, sum(f.qty) as sumf, sum(pr.qty) as sumpr FROM ".MAIN_DB_PREFIX."product as p";
$sql.=" left join ".MAIN_DB_PREFIX."facturedet as f on p.rowid = f.fk_product";
$sql.=" left join ".MAIN_DB_PREFIX."propaldet as pr on p.rowid = pr.fk_product";
$sql.=" group by p.label";
$resql=$db->query($sql);
if ($resql)
{
  $num = $db->num_rows($resql);
  $i = 0;
      while ($i < $num)
	{
		$obj = $db->fetch_object($resql);
		print "<tr ".$bc[$var].">";
		print "<td>".$obj->label."</td>";
		print "<td>".$obj->sumf."</td>";
		print "<td>".$obj->sumpr."</td>";
		print '</tr>';
		$i++;
	}
}
else {
	dol_print_error($db);
}
print "</table>\n";
$db->free($resql);
*/


$db->close();

llxFooter('$Date: 2010/08/14 02:44:25 $ - $Revision: 1.42 $');
?>
