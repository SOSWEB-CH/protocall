<?php
/* Copyright (C) 2004-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2010 Regis Houssin        <regis@dolibarr.fr>
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
 *		\file       htdocs/viewimage.php
 *		\brief      Wrapper permettant l'affichage de fichiers images Dolibarr
 *      \remarks    Call to wrapper is '<img src="'.DOL_URL_ROOT.'/viewimage.php?file=pathrelatifdufichier&modulepart=repfichierconcerne">'
 *		\version    $Id: viewimage.php,v 1.86.2.1 2011/03/23 17:24:26 hregis Exp $
 */

// Do not use urldecode here ($_GET and $_REQUEST are already decoded by PHP).
$action = isset($_GET["action"])?$_GET["action"]:'';
$original_file = isset($_GET["file"])?$_GET["file"]:'';
$modulepart = isset($_GET["modulepart"])?$_GET["modulepart"]:'';
$urlsource = isset($_GET["urlsource"])?$_GET["urlsource"]:'';

//if (! defined('NOREQUIREUSER'))   define('NOREQUIREUSER','1');	// Not disabled cause need to load personalized language
//if (! defined('NOREQUIREDB'))   define('NOREQUIREDB','1');		// Not disabled cause need to load personalized language
if (! defined('NOREQUIRESOC'))    define('NOREQUIRESOC','1');
if (! defined('NOREQUIRETRAN')) define('NOREQUIRETRAN','1');
if (! defined('NOCSRFCHECK'))     define('NOCSRFCHECK','1');
if (! defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL','1');
if (! defined('NOREQUIREMENU'))  define('NOREQUIREMENU','1');
if (! defined('NOREQUIREHTML'))  define('NOREQUIREHTML','1');
if (! defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX','1');
// Pour autre que companylogo, on charge environnement + info issus de logon comme le user
if (($modulepart == 'companylogo') && ! defined("NOLOGIN")) define("NOLOGIN",'1');


// C'est un wrapper, donc header vierge
function llxHeader() { }


require("./main.inc.php");
require_once(DOL_DOCUMENT_ROOT.'/lib/files.lib.php');

// Define mime type
$type = 'application/octet-stream';
if (! empty($_GET["type"])) $type=$_GET["type"];
else $type=dol_mimetype($original_file);

// Suppression de la chaine de caractere ../ dans $original_file
$original_file = str_replace("../","/", $original_file);

// Security check
if (empty($modulepart)) accessforbidden('Bad value for modulepart');
$accessallowed=0;
if ($modulepart)
{
	// Check permissions and define directory

	// Wrapping for company logo
	if ($modulepart == 'companylogo')
	{
		$accessallowed=1;
		$original_file=$conf->mycompany->dir_output.'/logos/'.$original_file;
	}

	// Wrapping for users photos
	elseif ($modulepart == 'userphoto')
	{
		$accessallowed=1;
		$original_file=$conf->user->dir_output.'/'.$original_file;
	}

	// Wrapping for members photos
	elseif ($modulepart == 'memberphoto')
	{
		$accessallowed=1;
		$original_file=$conf->adherent->dir_output.'/'.$original_file;
	}

	// Wrapping pour les apercu factures
	elseif ($modulepart == 'apercufacture')
	{
		if ($user->rights->facture->lire)
		{
			$accessallowed=1;
		}
		$original_file=$conf->facture->dir_output.'/'.$original_file;
	}

	// Wrapping pour les apercu propal
	elseif ($modulepart == 'apercupropal')
	{
		if ($user->rights->propale->lire)
		{
			$accessallowed=1;
		}
		$original_file=$conf->propale->dir_output.'/'.$original_file;
	}

	// Wrapping pour les apercu commande
	elseif ($modulepart == 'apercucommande')
	{
		if ($user->rights->commande->lire)
		{
			$accessallowed=1;
		}
		$original_file=$conf->commande->dir_output.'/'.$original_file;
	}

	// Wrapping pour les apercu intervention
	elseif ($modulepart == 'apercufichinter')
	{
		if ($user->rights->ficheinter->lire)
		{
			$accessallowed=1;
		}
		$original_file=$conf->ficheinter->dir_output.'/'.$original_file;
	}

	// Wrapping pour les images des stats propales
	elseif ($modulepart == 'propalstats')
	{
		if ($user->rights->propale->lire)
		{
			$accessallowed=1;
		}
		$original_file=$conf->propale->dir_temp.'/'.$original_file;
	}

	// Wrapping pour les images des stats commandes
	elseif ($modulepart == 'orderstats')
	{
		if ($user->rights->commande->lire)
		{
			$accessallowed=1;
		}
		$original_file=$conf->commande->dir_temp.'/'.$original_file;
	}
	elseif ($modulepart == 'orderstatssupplier')
	{
		if ($user->rights->fournisseur->commande->lire)
		{
			$accessallowed=1;
		}
		$original_file=$conf->fournisseur->dir_output.'/commande/temp/'.$original_file;
	}

	// Wrapping pour les images des stats factures
	elseif ($modulepart == 'billstats')
	{
		if ($user->rights->facture->lire)
		{
			$accessallowed=1;
		}
		$original_file=$conf->facture->dir_temp.'/'.$original_file;
	}
	elseif ($modulepart == 'billstatssupplier')
	{
		if ($user->rights->fournisseur->facture->lire)
		{
			$accessallowed=1;
		}
		$original_file=$conf->fournisseur->dir_output.'/facture/temp/'.$original_file;
	}

	// Wrapping pour les images des stats expeditions
	elseif ($modulepart == 'expeditionstats')
	{
		if ($user->rights->expedition->lire)
		{
			$accessallowed=1;
		}
		$original_file=$conf->expedition->dir_temp.'/'.$original_file;
	}

	// Wrapping pour les images des stats expeditions
	elseif ($modulepart == 'tripsexpensesstats')
	{
		if ($user->rights->deplacement->lire)
		{
			$accessallowed=1;
		}
		$original_file=$conf->deplacement->dir_temp.'/'.$original_file;
	}

	// Wrapping pour les images des stats produits
	elseif (preg_match('/^productstats_/i',$modulepart))
	{
		if ($user->rights->produit->lire || $user->rights->service->lire)
		{
			$accessallowed=1;
		}
		$original_file=(!empty($conf->product->dir_temp)?$conf->product->dir_temp:$conf->service->dir_temp).'/'.$original_file;
	}

	// Wrapping for products or services
	elseif ($modulepart == 'product')
	{
		if ($user->rights->produit->lire || $user->rights->service->lire)
		{
			$accessallowed=1;
		}
		$original_file=(!empty($conf->product->dir_output)?$conf->product->dir_output:$conf->service->dir_output).'/'.$original_file;
	}

	// Wrapping for categories
	elseif ($modulepart == 'category')
	{
		if ($user->rights->categorie->lire)
		{
			$accessallowed=1;
		}
		$original_file=$conf->categorie->dir_output.'/'.$original_file;
	}

	// Wrapping pour les prelevements
	elseif ($modulepart == 'prelevement')
	{
		if ($user->rights->prelevement->bons->lire) $accessallowed=1;

		$original_file=$conf->prelevement->dir_output.'/receipts/'.$original_file;
	}

	// Wrapping pour les graph energie
	elseif ($modulepart == 'graph_stock')
	{
		$accessallowed=1;
		$original_file=$conf->stock->dir_temp.'/'.$original_file;
	}

	// Wrapping pour les graph fournisseurs
	elseif ($modulepart == 'graph_fourn')
	{
		$accessallowed=1;
		$original_file=$conf->fournisseur->dir_temp.'/'.$original_file;
	}

	// Wrapping pour les graph des produits
	elseif ($modulepart == 'graph_product')
	{
		$accessallowed=1;
		$original_file=$conf->product->dir_temp.'/'.$original_file;
	}

	// Wrapping pour les code barre
	elseif ($modulepart == 'barcode')
	{
		$accessallowed=1;
		// If viewimage is called for barcode, we try to output an image on the fly,
		// with not build of file on disk.
		//$original_file=$conf->barcode->dir_temp.'/'.$original_file;
		$original_file='';
	}

    // Wrapping pour les icones de background des mailings
	elseif ($modulepart == 'iconmailing')
	{
		$accessallowed=1;
		$original_file=$conf->mailing->dir_temp.'/'.$original_file;
	}

    // Wrapping pour les icones de background des mailings
    elseif ($modulepart == 'scanner_user_temp')
    {
        $accessallowed=1;
        $original_file=$conf->scanner->dir_temp.'/'.$user->id.'/'.$original_file;
    }
    
	// Wrapping pour les images fckeditor
    elseif ($modulepart == 'fckeditor')
    {
        $accessallowed=1;
        $original_file=$conf->fckeditor->dir_output.'/'.$original_file;
    }

    // GENERIC Wrapping
	// If modulepart=module_user_temp	Allows any module to open a file if file is in directory called DOL_DATA_ROOT/modulepart/temp/iduser
	// If modulepart=module_temp		Allows any module to open a file if file is in directory called DOL_DATA_ROOT/modulepart/temp
	// If modulepart=module_user		Allows any module to open a file if file is in directory called DOL_DATA_ROOT/modulepart/iduser
	// If modulepart=module				Allows any module to open a file if file is in directory called DOL_DATA_ROOT/modulepart
	else
	{
		if (preg_match('/^([a-z]+)_user_temp$/i',$modulepart,$reg))
		{
			if ($user->rights->$reg[1]->lire || $user->rights->$reg[1]->read) $accessallowed=1;
		   	$original_file=$conf->$reg[1]->dir_temp.'/'.$user->id.'/'.$original_file;
    	}
		else if (preg_match('/^([a-z]+)_temp$/i',$modulepart,$reg))
		{
			if ($user->rights->$reg[1]->lire || $user->rights->$reg[1]->read) $accessallowed=1;
		   	$original_file=$conf->$reg[1]->dir_temp.'/'.$original_file;
		}
		else if (preg_match('/^([a-z]+)_user$/i',$modulepart,$reg))
		{
			if ($user->rights->$reg[1]->lire || $user->rights->$reg[1]->read) $accessallowed=1;
		   	$original_file=$conf->$reg[1]->dir_output.'/'.$user->id.'/'.$original_file;
    	}
    	else
		{
			$perm=GETPOST('perm');
			$subperm=GETPOST('subperm');
			if ($perm || $subperm)
			{
				if (($perm && $user->rights->$modulepart->$perm) || ($perm && $subperm && $user->rights->$modulepart->$perm->$subperm)) $accessallowed=1;
				$original_file=$conf->$modulepart->dir_output.'/'.$original_file;
			}
			else
			{
				if ($user->rights->$modulepart->lire || $user->rights->$modulepart->read) $accessallowed=1;
				$original_file=$conf->$modulepart->dir_output.'/'.$original_file;
			}
		}
	}
}

// Security:
// Limit access if permissions are wrong
if (! $accessallowed)
{
	accessforbidden();
}

// Security:
// On interdit les remontees de repertoire ainsi que les pipe dans
// les noms de fichiers.
if (preg_match('/\.\./',$original_file) || preg_match('/[<>|]/',$original_file))
{
	dol_syslog("Refused to deliver file ".$original_file, LOG_WARNING);
	// Do no show plain path in shown error message
	dol_print_error(0,'Error: File '.$_GET["file"].' does not exists');
	exit;
}



if ($modulepart == 'barcode')
{
	$generator=$_GET["generator"];
	$code=$_GET["code"];
	$encoding=$_GET["encoding"];
	$readable=$_GET["readable"]?$_GET["readable"]:"Y";

	// Output files with barcode generators
	foreach ($conf->file->dol_document_root as $dirroot)
	{
		$dir=$dirroot . "/includes/modules/barcode/";
		$result=@include_once($dir.$generator.".modules.php");
		if ($result) break;
	}

	// Chargement de la classe de codage
	$classname = "mod".ucfirst($generator);
	$module = new $classname($db);
	if ($module->encodingIsSupported($encoding))
	{
		$result=$module->buildBarCode($code,$encoding,$readable);
	}
}
else					// Open and return file
{
	clearstatcache();

	// Output files on browser
	dol_syslog("viewimage.php return file $original_file content-type=$type");
	$original_file_osencoded=dol_osencode($original_file);

	// This test if file exists should be useless. We keep it to find bug more easily
	if (! file_exists($original_file_osencoded))
	{
		dol_print_error(0,'Error: File '.$_GET["file"].' does not exists');
		exit;
	}

	// Les drois sont ok et fichier trouve
	if ($type)
	{
		header('Content-type: '.$type);
	}
	else
	{
		header('Content-type: image/png');
	}

	readfile($original_file_osencoded);
}

?>
