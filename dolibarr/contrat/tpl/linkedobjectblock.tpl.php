<?php
/* Copyright (C) 2010 Regis Houssin <regis@dolibarr.fr>
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
 *
 * $Id: linkedobjectblock.tpl.php,v 1.9.2.1 2011/03/21 19:25:28 hregis Exp $
 */
?>

<!-- BEGIN PHP TEMPLATE -->

<?php

$langs = $GLOBALS['langs'];
$somethingshown = $GLOBALS['somethingshown'];
$linkedObjectBlock = $GLOBALS['object']->linkedObjectBlock;
$objectid = $GLOBALS['object']->objectid;
$num = count($objectid);

$langs->load("contracts");
if ($somethingshown) { echo '<br>'; }
print_titre($langs->trans('RelatedContracts'));
?>
<table class="noborder" width="100%">
<tr class="liste_titre">
	<td><?php echo $langs->trans("Ref"); ?></td>
	<td align="center"><?php echo $langs->trans("Date"); ?></td>
	<td align="right">&nbsp;</td>
	<td align="right"><?php echo $langs->trans("Status"); ?></td>
</tr>
<?php
$var=true;
for ($i = 0 ; $i < $num ; $i++)
{
	$linkedObjectBlock->fetch($objectid[$i]);
    $linkedObjectBlock->fetch_lines();
	$var=!$var;
?>
<tr <?php echo $bc[$var]; ?> ><td>
	<a href="<?php echo DOL_URL_ROOT.'/contrat/fiche.php?id='.$linkedObjectBlock->id ?>"><?php echo img_object($langs->trans("ShowContract"),"contract").' '.$linkedObjectBlock->ref; ?></a></td>
	<td align="center"><?php echo dol_print_date($linkedObjectBlock->date_contrat,'day'); ?></td>
	<td align="right">&nbsp;</td>
	<td align="right"><?php echo $linkedObjectBlock->getLibStatut(6); ?></td>
</tr>
<?php } ?>

</table>

<!-- END PHP TEMPLATE -->