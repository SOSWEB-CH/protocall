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
 *       \file       htdocs/webservices/demo_thirdparty_other.php
 *       \brief      Demo page to make a client call to Dolibarr WebServices "server_other"
 *       \version    $Id: demo_wsclient_thirdparty.php,v 1.2.2.2 2011/03/18 09:54:01 eldy Exp $
 */

// This is to make Dolibarr working with Plesk
set_include_path($_SERVER['DOCUMENT_ROOT'].'/htdocs');

require_once("../master.inc.php");
require_once(NUSOAP_PATH.'/nusoap.php');		// Include SOAP

$WS_DOL_URL = $dolibarr_main_url_root.'/webservices/server_thirdparty.php';
$WS_METHOD  = 'getThirdParty';
$ns='http://www.dolibarr.org/ns/';


// Set the WebService URL
dol_syslog("Create nusoap_client for URL=".$WS_DOL_URL);
$soapclient = new nusoap_client($WS_DOL_URL);
if ($soapclient)
{
	$soapclient->soap_defencoding='UTF-8';
	$soapclient->decodeUTF8(false);
}

// Call the WebService method and store its result in $result.
$authentication=array(
    'dolibarrkey'=>$conf->global->WEBSERVICES_KEY,
    'sourceapplication'=>'DEMO',
    'login'=>'admin',
    'password'=>'changeme',
    'entity'=>'');
$parameters = array('authentication'=>$authentication,'id'=>0,'name'=>'aaa');
dol_syslog("Call method ".$WS_METHOD);
$result = $soapclient->call($WS_METHOD,$parameters,$ns,'');
if (! $result)
{
	print $soapclient->error_str;
	exit;
}

/*
 * View
 */

header("Content-type: text/html; charset=utf8");
print '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">'."\n";
echo '<html>'."\n";
echo '<head>';
echo '<title>WebService Test: '.$WS_METHOD.'</title>';
echo '</head>'."\n";

echo '<body>'."\n";

echo "<h2>Request:</h2>";
echo '<h4>Function</h4>';
echo $WS_METHOD;
echo '<h4>SOAP Message</h4>';
echo '<pre>' . htmlspecialchars($soapclient->request, ENT_QUOTES) . '</pre>';

echo '<hr>';

echo "<h2>Response:</h2>";
echo '<h4>Result</h4>';
echo '<pre>';
print_r($result);
echo '</pre>';
echo '<h4>SOAP Message</h4>';
echo '<pre>' . htmlspecialchars($soapclient->response, ENT_QUOTES) . '</pre>';

echo '</body>'."\n";;
echo '</html>'."\n";;
?>
