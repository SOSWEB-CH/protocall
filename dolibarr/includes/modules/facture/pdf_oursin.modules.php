<?php
/* Copyright (C) 2004-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2010 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2005      Sylvain SCATTOLINI   <sylvain@s-infoservices.com>
 * Copyright (C) 2006      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2008      Raphael Bertrand     <raphael.bertrand@resultic.fr>
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
 * 		\file       htdocs/includes/modules/facture/pdf_oursin.modules.php
 * 		\ingroup    facture
 * 		\brief      Fichier de la classe permettant de generer les factures au modele oursin
 * 		\author	    Sylvain SCATTOLINI base sur un modele de Laurent Destailleur
 * 		\version    $Id: pdf_oursin.modules.php,v 1.149 2010/12/31 10:29:45 eldy Exp $
 */

require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");
require_once(DOL_DOCUMENT_ROOT."/includes/modules/facture/modules_facture.php");
require_once(DOL_DOCUMENT_ROOT."/lib/company.lib.php");
require_once(DOL_DOCUMENT_ROOT.'/lib/pdf.lib.php');


/**
 *  \class      pdf_oursin
 *  \brief      Classe permettant de generer les factures au modele oursin
 */
class pdf_oursin extends ModelePDFFactures
{
	var $emetteur;	// Objet societe qui emet
	var $marges=array("g"=>10,"h"=>5,"d"=>10,"b"=>15);

	/**
	 *	\brief  Constructeur
	 *	\param	db		Database handler
	 */
	function pdf_oursin($db)
	{
		global $conf,$langs,$mysoc;

		$langs->load("main");
		$langs->load("bills");
		$langs->load("products");

		$this->db = $db;
		$this->name = "oursin";
		$this->description = $langs->transnoentities('PDFOursinDescription');

		// Dimension page pour format A4
		$this->type = 'pdf';
		$this->page_largeur = 210;
		$this->page_hauteur = 297;
		$this->format = array($this->page_largeur,$this->page_hauteur);
		$this->marge_gauche=10;
		$this->marge_droite=10;
		$this->marge_haute=10;
		$this->marge_basse=10;

		$this->option_logo = 1;                    // Affiche logo FAC_PDF_LOGO
		$this->option_tva = 1;                     // Gere option tva FACTURE_TVAOPTION
		$this->option_modereg = 1;                 // Gere choix mode reglement FACTURE_CHQ_NUMBER, FACTURE_RIB_NUMBER
		$this->option_condreg = 1;                 // Affiche conditions reglement
		$this->option_codeproduitservice = 1;      // Affiche code produit-service
		$this->option_multilang = 1;               // Dispo en plusieurs langues
		$this->option_escompte = 0;                // Affiche si il y a eu escompte
		$this->option_credit_note = 1;             // Support credit note
		$this->option_freetext = 1;				   // Support add of a personalised text
		$this->option_draft_watermark = 1;		   // Support add of a watermark on drafts

		$this->franchise=!$mysoc->tva_assuj;

		// Recupere emmetteur
		$this->emetteur=$mysoc;
		if (! $this->emetteur->pays_code) $this->emetteur->pays_code=substr($langs->defaultlang,-2);    // Par defaut, si n'�tait pas d�fini

		// Defini position des colonnes
		$this->posxdesc=$this->marge_gauche+1;
		$this->posxtva=113;
		$this->posxup=126;
		$this->posxqty=145;
		$this->posxdiscount=162;
		$this->postotalht=174;

		$this->tva=array();
		$this->atleastoneratenotnull=0;
		$this->atleastonediscount=0;
	}


	/**
	 *		\brief      Fonction generant la facture sur le disque
	 *		\param	    fac				Objet invoice to build (or id if old method)
	 *		\param		outputlangs		Lang object for output language
	 *		\return	    int     		1=ok, 0=ko
	 */
	function write_file($object,$outputlangs)
	{
		global $user,$langs,$conf;

		if (! is_object($outputlangs)) $outputlangs=$langs;
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (!class_exists('TCPDF')) $outputlangs->charset_output='ISO-8859-1';

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$outputlangs->load("main");
		$outputlangs->load("dict");
		$outputlangs->load("companies");
		$outputlangs->load("bills");
		$outputlangs->load("products");

		if ($conf->facture->dir_output)
		{
			$object->fetch_thirdparty();

			$deja_regle = $object->getSommePaiement();
			$amount_credit_notes_included = $object->getSumCreditNotesUsed();
			$amount_deposits_included = $object->getSumDepositsUsed();

			// Definition of $dir and $file
			if ($object->specimen)
			{
				$dir = $conf->facture->dir_output;
				$file = $dir . "/SPECIMEN.pdf";
			}
			else
			{
				$objectref = dol_sanitizeFileName($object->ref);
				$dir = $conf->facture->dir_output . "/" . $objectref;
				$file = $dir . "/" . $objectref . ".pdf";
			}

			if (! file_exists($dir))
			{
				if (create_exdir($dir) < 0)
				{
					$this->error=$langs->transnoentities("ErrorCanNotCreateDir",$dir);
					return 0;
				}
			}

			if (file_exists($dir))
			{

				// Protection et encryption du pdf
/*				if ($conf->global->PDF_SECURITY_ENCRYPTION)
				{
					$pdf=new FPDI_Protection('P','mm',$this->format);
					$pdfrights = array('print'); // Ne permet que l'impression du document
					$pdfuserpass = ''; // Mot de passe pour l'utilisateur final
					$pdfownerpass = NULL; // Mot de passe du propri�taire, cr�� al�atoirement si pas d�fini
					$pdf->SetProtection($pdfrights,$pdfuserpass,$pdfownerpass);
				}
				else
				{
					$pdf=new FPDI('P','mm',$this->format);
				}
*/
                $pdf=pdf_getInstance($this->format);

                if (class_exists('TCPDF'))
                {
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                }
                $pdf->SetFont(pdf_getPDFFont($outputlangs));

				$pdf->Open();
				$pagenb=0;
				$pdf->SetDrawColor(128,128,128);

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("Invoice"));
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("Invoice"));
				if ($conf->global->MAIN_DISABLE_PDF_COMPRESSION) $pdf->SetCompression(false);

				$pdf->SetMargins(10, 10, 10);
				$pdf->SetAutoPageBreak(1,0);

				// New page
				$pdf->AddPage();
				$pagenb++;
				$this->_pagehead($pdf, $object, 1, $outputlangs);
				$pdf->SetFont('','', $default_font_size - 1);
				$pdf->MultiCell(0, 3, '');		// Set interline to 3
				$pdf->SetTextColor(0,0,0);

				$tab_top = $this->marges['h']+90;
				$tab_height = 110;

				$pdf->SetFillColor(220,220,220);
				$pdf->SetFont('','', $default_font_size - 1);
				$pdf->SetXY ($this->marges['g'], $tab_top + $this->marges['g'] );

				$iniY = $pdf->GetY();
				$curY = $pdf->GetY();
				$nexY = $pdf->GetY();
				$nblignes = sizeof($object->lines);

				// Loop on each lines
				for ($i = 0 ; $i < $nblignes ; $i++)
				{
					$curY = $nexY;

					// Description of product line
					//$libelleproduitservice=pdf_getlinedesc($object,$i,$outputlangs);
                    pdf_writelinedesc($pdf,$object,$i,$outputlangs,108,3,$this->posxdesc-1,$curY+1);
					//$pdf->writeHTMLCell(108, 3, $this->posxdesc-1, $curY, $outputlangs->convToOutputCharset($libelleproduitservice), 0, 1);

					$nexY = $pdf->GetY();

					// TVA
					if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT))
					{
						if ($this->franchise!=1)
						{
							$vat_rate = pdf_getlinevatrate($object, $i, $outputlangs);
							$pdf->SetXY ($this->marges['g']+118, $curY);
							$pdf->MultiCell(12, 3, $vat_rate, 0, 'R');
						}
					}

					// Prix unitaire HT avant remise
					$up_excl_tax = pdf_getlineupexcltax($object, $i, $outputlangs);
					$pdf->SetXY ($this->marges['g']+132, $curY);
					$pdf->MultiCell(16, 3, $up_excl_tax, 0, 'R', 0);

					// Quantity
					$qty = pdf_getlineqty($object, $i, $outputlangs);
					$pdf->SetXY ($this->marges['g']+150, $curY);
					$pdf->MultiCell(10, 3, $qty, 0, 'R');

					// Remise sur ligne
					$pdf->SetXY ($this->marges['g']+160, $curY);
					if ($object->lines[$i]->remise_percent) {
						$remise_percent = pdf_getlineremisepercent($object, $i, $outputlangs);
						$pdf->MultiCell(14, 3, $remise_percent, 0, 'R');
					}

					// Total HT
					$total_excl_tax = pdf_getlinetotalexcltax($object, $i, $outputlangs);
					$pdf->SetXY ($this->marges['g']+168, $curY);
					$pdf->MultiCell(21, 3, $total_excl_tax, 0, 'R', 0);


					if ($nexY > 200 && $i < $nblignes - 1)
					{
						$this->_tableau($pdf, $tab_top, $tab_height, $nexY, $object, $outputlangs);
						$nexY = $iniY;

						// New page
						$pdf->AddPage();
						$pagenb++;
						$this->_pagehead($pdf, $object, 0, $outputlangs);
						$pdf->SetFont('','', $default_font_size - 1);
						$pdf->MultiCell(0, 3, '');		// Set interline to 3
						$pdf->SetTextColor(0,0,0);
					}

				}
				$posy=$this->_tableau($pdf, $tab_top, $tab_height, $nexY, $object, $outputlangs);
				$bottomlasttab=$tab_top + $tab_height + 1;

				// Affiche zone infos
				$posy=$this->_tableau_info($pdf, $object, $bottomlasttab, $outputlangs);

				// Affiche zone totaux
				$posy=$this->_tableau_tot($pdf, $object, $deja_regle, $bottomlasttab, $outputlangs);

				// Affiche zone versements
				if ($deja_regle || $amount_credit_notes_included || $amount_deposits_included)
				{
					$posy=$this->_tableau_versements($pdf, $object, $posy, $outputlangs);
				}

				// Pied de page
				$this->_pagefoot($pdf, $object, $outputlangs);
				$pdf->AliasNbPages();

				$pdf->Close();

				$pdf->Output($file,'F');
				if (! empty($conf->global->MAIN_UMASK))
				@chmod($file, octdec($conf->global->MAIN_UMASK));

				return 1;   // Pas d'erreur
			}
			else
			{
				$this->error=$langs->transnoentities("ErrorCanNotCreateDir",$dir);
				return 0;
			}
		}
		else
		{
			$this->error=$langs->transnoentities("ErrorConstantNotDefined","FAC_OUTPUTDIR");
			return 0;
		}
		$this->error=$langs->transnoentities("ErrorUnknown");
		return 0;   // Erreur par defaut
	}


	/**
	 *  \brief      Affiche tableau des versement
	 *  \param      pdf     		Objet PDF
	 *  \param      fac     		Objet facture
	 *	\param		posy			Position y in PDF
	 *	\param		outputlangs		Object langs for output
	 *	\return 	int				<0 if KO, >0 if OK
	 */
	function _tableau_versements(&$pdf, $object, $posy, $outputlangs)
	{
		$tab3_posx = $this->marges['g']+110;
		$tab3_top = $posy + 8;
		$tab3_width = 80;
		$tab3_height = 4;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->SetFont('','', $default_font_size - 2);
		$pdf->SetXY ($tab3_posx, $tab3_top - 5);
		$pdf->MultiCell(60, 5, $outputlangs->transnoentities("PaymentsAlreadyDone"), 0, 'L', 0);

		$pdf->Rect($tab3_posx, $tab3_top-1, $tab3_width, $tab3_height);

		$pdf->SetFont('','', $default_font_size - 4);
		$pdf->SetXY ($tab3_posx, $tab3_top-1 );
		$pdf->MultiCell(20, 4, $outputlangs->transnoentities("Payment"), 0, 'L', 0);
		$pdf->SetXY ($tab3_posx+21, $tab3_top-1 );
		$pdf->MultiCell(20, 4, $outputlangs->transnoentities("Amount"), 0, 'L', 0);
		$pdf->SetXY ($tab3_posx+40, $tab3_top-1 );
		$pdf->MultiCell(20, 4, $outputlangs->transnoentities("Type"), 0, 'L', 0);
		$pdf->SetXY ($tab3_posx+58, $tab3_top-1 );
		$pdf->MultiCell(20, 4, $outputlangs->transnoentities("Num"), 0, 'L', 0);

		$y=0;

		$pdf->SetFont('','', $default_font_size - 4);

		// Loop on each credit note included
		$sql = "SELECT re.rowid, re.amount_ht, re.amount_tva, re.amount_ttc,";
		$sql.= " re.description, re.fk_facture_source, re.fk_facture_source,";
		$sql.= " f.type, f.datef";
		$sql.= " FROM ".MAIN_DB_PREFIX ."societe_remise_except as re, ".MAIN_DB_PREFIX ."facture as f";
		$sql.= " WHERE re.fk_facture_source = f.rowid AND re.fk_facture = ".$object->id;
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$num = $this->db->num_rows($resql);
			$i=0;
			$invoice=new Facture($this->db);
			while ($i < $num)
			{
				$y+=3;
				$obj = $this->db->fetch_object($resql);

				if ($obj->type == 2) $text=$outputlangs->trans("CreditNote");
				elseif ($obj->type == 3) $text=$outputlangs->trans("Deposit");
				else $text=$outputlangs->trans("UnknownType");

				$invoice->fetch($obj->fk_facture_source);

				$pdf->SetXY ($tab3_posx, $tab3_top+$y );
				$pdf->MultiCell(20, 3, dol_print_date($obj->datef,'day',false,$outputlangs,true), 0, 'L', 0);
				$pdf->SetXY ($tab3_posx+21, $tab3_top+$y);
				$pdf->MultiCell(20, 3, price($obj->amount_ttc), 0, 'L', 0);
				$pdf->SetXY ($tab3_posx+40, $tab3_top+$y);
				$pdf->MultiCell(20, 3, $text, 0, 'L', 0);
				$pdf->SetXY ($tab3_posx+58, $tab3_top+$y);
				$pdf->MultiCell(20, 3, $invoice->ref, 0, 'L', 0);

				$pdf->line($tab3_posx, $tab3_top+$y+3, $tab3_posx+$tab3_width, $tab3_top+$y+3 );

				$i++;
			}
		}
		else
		{
			$this->error=$outputlangs->trans("Error")." sql=".$sql;
			dol_syslog($this->db,$this->error, LOG_ERR);
			return -1;
		}

        // Loop on each payment
        $sql = "SELECT p.datep as date, p.fk_paiement as type, p.num_paiement as num, pf.amount as amount,";
        $sql.= " cp.code";
        $sql.= " FROM ".MAIN_DB_PREFIX."paiement_facture as pf, ".MAIN_DB_PREFIX."paiement as p";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_paiement as cp ON p.fk_paiement = cp.id";
        $sql.= " WHERE pf.fk_paiement = p.rowid and pf.fk_facture = ".$object->id;
        $sql.= " ORDER BY p.datep";
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $num = $this->db->num_rows($resql);
            $i=0;
            while ($i < $num) {
                $y+=3;
                $row = $this->db->fetch_object($resql);

                $pdf->SetXY ($tab3_posx, $tab3_top+$y );
                $pdf->MultiCell(20, 3, dol_print_date($this->db->jdate($row->date),'day',false,$outputlangs,true), 0, 'L', 0);
                $pdf->SetXY ($tab3_posx+21, $tab3_top+$y);
                $pdf->MultiCell(20, 3, price($row->amount), 0, 'L', 0);
                $pdf->SetXY ($tab3_posx+40, $tab3_top+$y);
                $oper = $outputlangs->getTradFromKey("PaymentTypeShort" . $row->code);

                $pdf->MultiCell(20, 3, $oper, 0, 'L', 0);
                $pdf->SetXY ($tab3_posx+58, $tab3_top+$y);
                $pdf->MultiCell(30, 3, $row->num, 0, 'L', 0);

                $pdf->line($tab3_posx, $tab3_top+$y+3, $tab3_posx+$tab3_width, $tab3_top+$y+3 );

                $i++;
            }
        }
        else
        {
            $this->error=$this->db->lasterror();
            dol_syslog($this->db,$this->error, LOG_ERR);
            return -1;
        }
	}

	/**
	 *	\brief      Affiche infos divers
	 *	\param      pdf             Objet PDF
	 *	\param      object          Objet facture
	 *	\param		posy			Position depart
	 *	\param		outputlangs		Objet langs
	 *	\return     y               Position pour suite
	 */
	function _tableau_info(&$pdf, $object, $posy, $outputlangs)
	{
		global $conf;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->SetFont('','', $default_font_size - 1);

		// If France, show VAT mention if not applicable
		if ($this->emetteur->pays_code == 'FR' && $this->franchise == 1)
		{
			$pdf->SetFont('','B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("VATIsNotUsedForInvoice"), 0, 'L', 0);

			$posy=$pdf->GetY()+4;
		}

		// Show payments conditions
		if ($object->type != 2 && ($object->cond_reglement_code || $object->cond_reglement))
		{
			$pdf->SetFont('','B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$titre = $outputlangs->transnoentities("PaymentConditions").':';
			$pdf->MultiCell(80, 4, $titre, 0, 'L');

			$pdf->SetFont('','', $default_font_size - 2);
			$pdf->SetXY(52, $posy);
			$lib_condition_paiement=$outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code)!=('PaymentCondition'.$object->cond_reglement_code)?$outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code):$outputlangs->convToOutputCharset($object->cond_reglement_doc);
			$lib_condition_paiement=str_replace('\n',"\n",$lib_condition_paiement);
			$pdf->MultiCell(80, 4, $lib_condition_paiement,0,'L');

			$posy=$pdf->GetY()+3;
		}


		if ($object->type != 2)
		{
			// Check a payment mode is defined
			if (empty($object->mode_reglement_code)
			&& ! $conf->global->FACTURE_CHQ_NUMBER
			&& ! $conf->global->FACTURE_RIB_NUMBER)
			{
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->SetTextColor(200,0,0);
				$pdf->SetFont('','B', $default_font_size - 2);
				$pdf->MultiCell(90, 3, $outputlangs->transnoentities("ErrorNoPaiementModeConfigured"),0,'L',0);
				$pdf->SetTextColor(0,0,0);

				$posy=$pdf->GetY()+1;
			}

			// Sown payment mode
			if ($object->mode_reglement_code
			&& $object->mode_reglement_code != 'CHQ'
			&& $object->mode_reglement_code != 'VIR')
			{
				$pdf->SetFont('','B', $default_font_size - 2);
				$pdf->SetXY($this->marge_gauche, $posy);
				$titre = $outputlangs->transnoentities("PaymentMode").':';
				$pdf->MultiCell(80, 5, $titre, 0, 'L');

				$pdf->SetFont('','', $default_font_size - 2);
				$pdf->SetXY(50, $posy);
				$lib_mode_reg=$outputlangs->transnoentities("PaymentType".$object->mode_reglement_code)!=('PaymentType'.$object->mode_reglement_code)?$outputlangs->transnoentities("PaymentType".$object->mode_reglement_code):$outputlangs->convToOutputCharset($object->mode_reglement);
				$pdf->MultiCell(80, 5, $lib_mode_reg,0,'L');

				$posy=$pdf->GetY()+2;
			}

			// Show payment mode CHQ
			if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'CHQ')
			{
				// Si mode reglement non force ou si force a CHQ
				if ($conf->global->FACTURE_CHQ_NUMBER)
				{
					if ($conf->global->FACTURE_CHQ_NUMBER > 0)
					{
						$account = new Account($this->db);
						$account->fetch($conf->global->FACTURE_CHQ_NUMBER);

						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont('','B', $default_font_size - 2);
						$pdf->MultiCell(90, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo',$account->proprio).':',0,'L',0);
						$posy=$pdf->GetY()+1;

						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont('','', $default_font_size - 2);
						$pdf->MultiCell(80, 3, $outputlangs->convToOutputCharset($account->adresse_proprio), 0, 'L', 0);
						$posy=$pdf->GetY()+2;
					}
					if ($conf->global->FACTURE_CHQ_NUMBER == -1)
					{
						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont('','B', $default_font_size - 2);
						$pdf->MultiCell(90, 3, $outputlangs->transnoentities('PaymentByChequeOrderedToShort').' '.$outputlangs->convToOutputCharset($this->emetteur->nom).' '.$outputlangs->transnoentities('SendTo').':',0,'L',0);
						$posy=$pdf->GetY()+1;

						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont('','', $default_font_size - 2);
						$pdf->MultiCell(80, 3, $outputlangs->convToOutputCharset($this->emetteur->getFullAddress()), 0, 'L', 0);
						$posy=$pdf->GetY()+2;
					}
				}
			}

			// If payment mode not forced or forced to VIR, show payment with BAN
			if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'VIR')
			{
				if (! empty($conf->global->FACTURE_RIB_NUMBER))
				{
					$account = new Account($this->db);
					$account->fetch($conf->global->FACTURE_RIB_NUMBER);

					$curx=$this->marge_gauche;
					$cury=$posy;

					$posy=pdf_bank($pdf,$outputlangs,$curx,$cury,$account);

					$posy+=2;
				}
			}
		}

		return $posy;
	}


	/**
	 *	\brief      Affiche le total a payer
	 *	\param      pdf             Objet PDF
	 *	\param      object          Objet facture
	 *	\param      deja_regle      Montant deja regle
	 *	\param		posy			Position depart
	 *	\param		outputlangs		Objet langs
	 *	\return     y               Position pour suite
	 */
	function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs)
	{
		global $conf,$langs;

		$langs->load("main");
		$langs->load("bills");

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$tab2_top = $this->marges['h']+202;
		$tab2_hl = 4;
		$pdf->SetFont('','', $default_font_size - 1);

		// Tableau total
		$col1x=$this->marges['g']+110; $col2x=$this->marges['g']+164;
		$lltot = 200; $largcol2 = $lltot - $col2x;

		$pdf->SetXY ($this->marges['g'], $tab2_top + 0);

		$useborder=0;
		$index = 0;

		// Total HT
		$pdf->SetXY ($col1x, $tab2_top + 0);
		$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("TotalHT"), 0, 'L', 0);
		$pdf->SetXY ($col2x, $tab2_top + 0);
		$pdf->MultiCell($largcol2, $tab2_hl, price($object->total_ht + $object->remise), 0, 'R', 0);

		// Show VAT by rates and total
		$pdf->SetFillColor(248,248,248);

		$this->atleastoneratenotnull=0;
		if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT))
		{
			foreach( $this->tva as $tvakey => $tvaval )
			{
				if ($tvakey)    // On affiche pas taux 0
				{
					$this->atleastoneratenotnull++;

					$index++;
					$pdf->SetXY ($col1x, $tab2_top + $tab2_hl * $index);
					$tvacompl='';
					if (preg_match('/\*/',$tvakey))
					{
						$tvakey=str_replace('*','',$tvakey);
						$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
					}
					$totalvat =$outputlangs->transnoentities("TotalVAT").' ';
					$totalvat.=vatrate($tvakey,1).$tvacompl;
					$pdf->MultiCell($col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);
					$pdf->SetXY ($col2x, $tab2_top + $tab2_hl * $index);
					$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval), 0, 'R', 1);
				}
			}

			if (! $this->atleastoneratenotnull)	// If no vat at all
			{
				$index++;
				$pdf->SetXY ($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("TotalVAT"), 0, 'L', 1);
				$pdf->SetXY ($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($object->total_tva), 0, 'R', 1);
			}
		}

		// Total TTC
		if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT))
		{
			$index++;
			$pdf->SetXY ($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->SetTextColor(22,137,210);
			$pdf->SetFont('','B', $default_font_size + 1);
			$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("TotalTTC"), 0, 'L', 0);
			$pdf->SetXY ($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($object->total_ttc), 0, 'R', 0);
			$pdf->SetTextColor(0,0,0);
		}

		$creditnoteamount=$object->getSumCreditNotesUsed();
		$depositsamount=$object->getSumDepositsUsed();
		$resteapayer = price2num($object->total_ttc - $deja_regle - $creditnoteamount - $depositsamount, 'MT');
		if ($object->paye) $resteapayer=0;

		if ($deja_regle > 0 || $creditnoteamount > 0 || $depositsamount > 0)
		{
			$pdf->SetFont('','', $default_font_size);

			// Already paid + Deposits
			$index++;
			$pdf->SetXY ($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("AlreadyPaid"), 0, 'L', 0);
			$pdf->SetXY ($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($deja_regle + $depositsamount), 0, 'R', 0);

			// Credit note
			if ($creditnoteamount)
			{
				$index++;
				$pdf->SetXY ($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("CreditNotes"), 0, 'L', 0);
				$pdf->SetXY ($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($creditnoteamount), 0, 'R', 0);
			}

			// Escompte
			if ($object->close_code == 'discount_vat')
			{
				$index++;
				$pdf->SetFillColor(255,255,255);

				$pdf->SetXY ($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("EscompteOffered"), $useborder, 'L', 1);
				$pdf->SetXY ($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($object->total_ttc - $deja_regle - $creditnoteamount - $depositsamount), $useborder, 'R', 1);

				$resteapayer=0;
			}

			$index++;
			$pdf->SetTextColor(0,0,60);
			$pdf->SetXY ($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("RemainderToPay"), 0, 'L', 0);
			$pdf->SetFillColor(224,224,224);
			$pdf->SetXY ($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($resteapayer), 0, 'R', 0);

			// Fin
			$pdf->SetFont('','B', $default_font_size + 1);
			$pdf->SetTextColor(0,0,0);
		}

		$index++;
		return ($tab2_top + ($tab2_hl * $index));
	}

	/*
	 *   Affiche la grille des lignes de factures
	 *   @param      pdf     objet PDF
	 */
	function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $object, $outputlangs)
	{
		global $conf,$langs;
		$langs->load("main");
		$langs->load("bills");

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->line( $this->marges['g'], $tab_top+8, 210-$this->marges['d'], $tab_top+8 );
		$pdf->line( $this->marges['g'], $tab_top + $tab_height, 210-$this->marges['d'], $tab_top + $tab_height );

		$pdf->SetFont('','', $default_font_size - 1);

		$pdf->SetXY($this->marges['g'],$tab_top + 1);
        $pdf->MultiCell(0, 4, $outputlangs->transnoentities("Designation"), 0, 'L');

        if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT))
		{
            $pdf->SetXY($this->marges['g']+120,$tab_top + 1);
            $pdf->MultiCell(0, 4, $outputlangs->transnoentities("VAT"), 0, 'L');
		}

		$pdf->SetXY($this->marges['g']+135,$tab_top + 1);
        $pdf->MultiCell(0, 4, $outputlangs->transnoentities("PriceUHT"), 0, 'L');
        $pdf->SetXY($this->marges['g']+153,$tab_top + 1);
        $pdf->MultiCell(0, 4, $outputlangs->transnoentities("Qty"), 0, 'L');

		$nblignes = sizeof($object->lines);
		$rem=0;
		for ($i = 0 ; $i < $nblignes ; $i++)
		{
    		if ($object->lines[$i]->remise_percent)
    		{
    			$rem=1;
    		}
		}
		if ($rem==1)
		{
            $pdf->SetXY($this->marges['g']+165,$tab_top + 1);
            $pdf->MultiCell(0, 4, $outputlangs->transnoentities("%"), 0, 'L');
		}
        $pdf->SetXY($this->marges['g']+170,$tab_top + 1);
        $pdf->MultiCell(20, 4, $outputlangs->transnoentities("TotalHTShort"), 0, 'R');

		return $pdf->GetY();
	}

	/*
	 *   \brief      Affiche en-tete facture
	 *   \param      pdf     objet PDF
	 *   \param      fac     objet facture
	 */
	function _pagehead(&$pdf, $object, $showadress=0, $outputlangs)
	{
		global $langs,$conf;
		$langs->load("main");
		$langs->load("bills");
		$langs->load("propal");
		$langs->load("companies");

		pdf_pagehead($pdf,$outputlangs,$this->page_hauteur);
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		//Affiche le filigrane brouillon - Print Draft Watermark
		if($object->statut==0 && (! empty($conf->global->FACTURE_DRAFT_WATERMARK)) )
		{
            pdf_watermark($pdf,$outputlangs,$this->page_hauteur,$this->page_largeur,'mm',$conf->global->FACTURE_DRAFT_WATERMARK);
		}

		$pdf->SetTextColor(0,0,60);
		$pdf->SetFont('','B',$default_font_size + 3);

		$pdf->SetXY($this->marges['g'],6);

		// Logo
		$logo=$conf->mycompany->dir_output.'/logos/'.$this->emetteur->logo;
		if ($this->emetteur->logo)
		{
			if (is_readable($logo))
			{
				$taille=getimagesize($logo);
				$length=$taille[0]/2.835;
				$pdf->Image($logo, $this->marges['g'], $this->marges['h'], 0, 24);
			}
			else
			{
				$pdf->SetTextColor(200,0,0);
				$pdf->SetFont('','B', $default_font_size - 2);
				$pdf->MultiCell(80, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound",$logo), 0, 'L');
				$pdf->MultiCell(80, 3, $outputlangs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
			}
		}
		else
		{
			$text=$this->emetteur->nom;
			$pdf->MultiCell(80, 4, $outputlangs->convToOutputCharset($text), 0, 'L');
		}


		/*
		 * Emetteur
		 */
		$posy=$this->marges['h']+24;
		$pdf->SetTextColor(0,0,0);
		$pdf->SetFont('','', $default_font_size - 2);
		$pdf->SetXY($this->marges['g'],$posy-5);


		$pdf->SetXY($this->marges['g'],$posy);
		$pdf->SetFillColor(255,255,255);
		$pdf->MultiCell(82, 34, "", 0, 'R', 1);


		$pdf->SetXY($this->marges['g'],$posy+4);

		// Sender name
		$pdf->SetTextColor(0,0,60);
		$pdf->SetFont('','B', $default_font_size);
		$pdf->MultiCell(80, 4, $outputlangs->convToOutputCharset($this->emetteur->nom), 0, 'L');

		// Sender properties
		$carac_emetteur = pdf_build_address($outputlangs,$this->emetteur);

		$pdf->SetFont('','', $default_font_size - 1);
		$pdf->SetXY($this->marge_gauche,$posy+9);
		$pdf->MultiCell(80, 4, $carac_emetteur, 0, 'L');


		// Client destinataire
		$posy=45;
		$pdf->SetTextColor(0,0,0);
		$pdf->SetFont('','', $default_font_size - 2);
		$pdf->SetXY($this->marges['g']+100,$posy-5);
		$pdf->SetFont('','B',$default_font_size);

		// If BILLING contact defined on invoice, we use it
		$usecontact=false;
		$arrayidcontact=$object->getIdContact('external','BILLING');
		if (sizeof($arrayidcontact) > 0)
		{
			$usecontact=true;
			$result=$object->fetch_contact($arrayidcontact[0]);
		}

		// Recipient name
		if (! empty($usecontact))
		{
			// On peut utiliser le nom de la societe du contact
			if ($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT) $socname = $object->contact->socname;
			else $socname = $object->client->nom;
			$carac_client_name=$outputlangs->convToOutputCharset($socname);
		}
		else
		{
			$carac_client_name=$outputlangs->convToOutputCharset($object->client->nom);
		}

		$carac_client=pdf_build_address($outputlangs,$this->emetteur,$object->client,$object->contact,$usecontact,'target');

		// Show customer/recipient
		$pdf->SetFont('','B', $default_font_size);
		$pdf->SetXY($this->marges['g']+100,$posy+4);
		$pdf->MultiCell(86,4, $carac_client_name, 0, 'L');

		$pdf->SetFont('','B', $default_font_size - 1);
		$pdf->SetXY($this->marges['g']+100,$posy+8);
		$pdf->MultiCell(86,4, $carac_client, 0, 'L');

		/*
		 * ref facture
		 */
		$posy=70;
		$pdf->SetFont('','B', $default_font_size + 3);
		$pdf->SetXY($this->marges['g'],$posy-5);
		$pdf->SetTextColor(0,0,0);
		$title=$outputlangs->transnoentities("Invoice");
		if ($object->type == 1) $title=$outputlangs->transnoentities("InvoiceReplacement");
		if ($object->type == 2) $title=$outputlangs->transnoentities("InvoiceAvoir");
		if ($object->type == 3) $title=$outputlangs->transnoentities("InvoiceDeposit");
		if ($object->type == 4) $title=$outputlangs->transnoentities("InvoiceProForma");
		$pdf->MultiCell(100, 10, $title.' '.$outputlangs->transnoentities("Of").' '.dol_print_date($object->date,"day",false,$outputlangs,true), '' , 'L');
		$pdf->SetFont('','B', $default_font_size + 1);
		$pdf->SetXY($this->marges['g'],$posy);
		$pdf->SetTextColor(22,137,210);
		$pdf->MultiCell(100, 10, $outputlangs->transnoentities("RefBill")." : " . $outputlangs->transnoentities($object->ref), '', 'L');
		$pdf->SetTextColor(0,0,0);
		$posy+=4;

		$objectidnext=$object->getIdReplacingInvoice('validated');
		if ($object->type == 0 && $objectidnext)
		{
			$objectreplacing=new Facture($this->db);
			$objectreplacing->fetch($objectidnext);

			$posy+=4;
			$pdf->SetXY($this->marges['g'],$posy);
			$pdf->SetTextColor(0,0,60);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("ReplacementByInvoice").' : '.$outputlangs->convToOutputCharset($objectreplacing->ref), '', 'L');
		}
		if ($object->type == 1)
		{
			$objectreplaced=new Facture($this->db);
			$objectreplaced->fetch($object->fk_facture_source);

			$posy+=4;
			$pdf->SetXY($this->marges['g'],$posy);
			$pdf->SetTextColor(0,0,60);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("ReplacementInvoice").' : '.$outputlangs->convToOutputCharset($objectreplaced->ref), '', 'L');
		}
		if ($object->type == 2)
		{
			$objectreplaced=new Facture($this->db);
			$objectreplaced->fetch($object->fk_facture_source);

			$posy+=4;
			$pdf->SetXY($this->marges['g'],$posy);
			$pdf->SetTextColor(0,0,60);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("CorrectionInvoice").' : '.$outputlangs->convToOutputCharset($objectreplaced->ref), '', 'L');
		}

		if ($object->type != 2)
		{
			$posy+=5;
			$pdf->SetXY($this->marges['g'],$posy);
			$pdf->SetFont('','', $default_font_size - 1);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("DateEcheance")." : " . dol_print_date($object->date_lim_reglement,"day",false,$outputlangs,true), '', 'L');
		}

		if ($object->client->code_client)
		{
			$posy+=4;
			$pdf->SetXY($this->marges['g'],$posy);
			$pdf->SetFont('','', $default_font_size - 1);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("CustomerCode")." : " . $outputlangs->transnoentities($object->client->code_client), '', 'L');
		}

		// Add list of linked orders and proposals
	    $object->load_object_linked();

	    // TODO mutualiser
	    if ($conf->propal->enabled)
		{
			require_once(DOL_DOCUMENT_ROOT."/comm/propal/class/propal.class.php");

			$outputlangs->load('propal');
			foreach($object->linked_object as $key => $val)
			{
				if ($key == 'propal')
				{
					for ($i = 0; $i<sizeof($val);$i++)
					{
						$newobject=new Propal($this->db);
						$result=$newobject->fetch($val['linkid']);
						if ($result >= 0)
						{
							$posy+=4;
							$pdf->SetXY($this->marges['g'],$posy);
							$pdf->SetFont('','', $default_font_size - 1);
							$pdf->MultiCell(60, 3, $outputlangs->transnoentities("RefProposal")." : ".$outputlangs->transnoentities($newobject->ref));
						}
					}
				}
			}
		}

	    // TODO mutualiser
		if ($conf->commande->enabled)
		{
			require_once(DOL_DOCUMENT_ROOT."/commande/class/commande.class.php");

			$outputlangs->load('orders');
			foreach($object->linked_object as $key => $val)
			{
				if ($key == 'commande')
				{
					for ($i = 0; $i<sizeof($val);$i++)
					{
						$newobject=new Propal($this->db);
						$result=$newobject->fetch($val[$i]);
						if ($result >= 0)
						{
							$posy+=4;
							$pdf->SetXY($this->marges['g'],$posy);
							$pdf->SetFont('','', $default_font_size - 1);
							$text=$newobject->ref;
							if ($newobject->ref_client) $text.=' ('.$newobject->ref_client.')';
							$pdf->MultiCell(60, 3, $outputlangs->transnoentities("RefOrder")." : ".$outputlangs->transnoentities($text));
						}
					}
				}
			}
		}

		// Amount in (at tab_top - 1)
		$pdf->SetTextColor(0,0,0);
		$pdf->SetFont('','', $default_font_size);
		$titre = $outputlangs->transnoentities("AmountInCurrency",$outputlangs->transnoentitiesnoconv("Currency".$conf->monnaie));
        $pdf->SetXY($this->page_largeur - $this->marge_droite - ($pdf->GetStringWidth($titre) + 3), 91);
        $pdf->MultiCell(($pdf->GetStringWidth($titre) + 3), 2, $titre);
	}

	/**
	 *   	\brief      Show footer of page
	 *   	\param      pdf     		PDF factory
	 * 		\param		object			Object invoice
	 *      \param      outputlang		Object lang for output
	 * 		\remarks	Need this->emetteur object
	 */
	function _pagefoot(&$pdf, $object, $outputlangs)
	{
		return pdf_pagefoot($pdf,$outputlangs,'FACTURE_FREE_TEXT',$this->emetteur,$this->marge_basse,$this->marge_gauche,$this->page_hauteur,$object);
	}

}

?>
