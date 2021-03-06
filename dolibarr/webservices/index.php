<?php
/* Copyright (C) 2006-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *       \file       htdocs/webservices/server_invoice.php
 *       \brief      File that is entry point to call Dolibarr WebServices
 *       \version    $Id: index.php,v 1.2 2010/12/18 11:09:26 eldy Exp $
 */

// This is to make Dolibarr working with Plesk
set_include_path($_SERVER['DOCUMENT_ROOT'].'/htdocs');

require_once("../master.inc.php");
require_once(NUSOAP_PATH.'/nusoap.php');		// Include SOAP
require_once(DOL_DOCUMENT_ROOT."/user/class/user.class.php");

$langs->load("admin");


/*
 * View
 */

dol_syslog("Call Dolibarr webservices interfaces");

// Enable and test if module web services is enabled
if (empty($conf->global->MAIN_MODULE_WEBSERVICES))
{
	$langs->load("admin");
	dol_syslog("Call Dolibarr webservices interfaces with module webservices disabled");
	print $langs->trans("WarningModuleNotActive",'WebServices').'.<br><br>';
	print $langs->trans("ToActivateModule");
	exit;
}



// Should work with DOL_URL_ROOT='' or DOL_URL_ROOT='/dolibarr'
$urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT,'/').'$/i','',$dolibarr_main_url_root);

// WSDL
print '<u>'.$langs->trans("WSDLCanBeDownloadedHere").':</u><br>';
$url=$urlwithouturlroot.DOL_URL_ROOT.'/webservices/server_other.php?wsdl';
print img_picto('','object_globe.png').' '.'<a href="'.$url.'" target="_blank">'.$url."</a><br>\n";
if ($conf->societe->enabled)
{
	$url=$urlwithouturlroot.DOL_URL_ROOT.'/webservices/server_thirdparty.php?wsdl';
	print img_picto('','object_globe.png').' '.'<a href="'.$url.'" target="_blank">'.$url."</a><br>\n";
}
if ($conf->facture->enabled)
{
	$url=$urlwithouturlroot.DOL_URL_ROOT.'/webservices/server_invoice.php?wsdl';
	print img_picto('','object_globe.png').' '.'<a href="'.$url.'" target="_blank">'.$url."</a><br>\n";
}
print '<br>';


// Endpoint
print '<u>'.$langs->trans("EndPointIs").':</u><br>';
$url=$urlwithouturlroot.DOL_URL_ROOT.'/webservices/server_other.php';
print img_picto('','object_globe.png').' '.'<a href="'.$url.'" target="_blank">'.$url."</a><br>\n";
if ($conf->societe->enabled)
{
	$url=$urlwithouturlroot.DOL_URL_ROOT.'/webservices/server_thirdparty.php';
	print img_picto('','object_globe.png').' '.'<a href="'.$url.'" target="_blank">'.$url."</a><br>\n";
}
if ($conf->facture->enabled)
{
	$url=$urlwithouturlroot.DOL_URL_ROOT.'/webservices/server_invoice.php';
	print img_picto('','object_globe.png').' '.'<a href="'.$url.'" target="_blank">'.$url."</a><br>\n";
}
print '<br>';


$db->close();

?>