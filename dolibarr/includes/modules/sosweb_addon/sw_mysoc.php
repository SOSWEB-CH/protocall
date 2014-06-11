<?php
/*		replacement for sosweb document
 *		+ addition modification in code: (and create constant in llx_const table)
 *		replace SW_PROPALE_FREE_TEXT with SW_PROPALE_FREE_TEXT
 */
		$this->emetteur->nom="SOSWEB.CH";
		$this->emetteur->address="Av. du Rothorn 20\nCP 645";
		$this->emetteur->addresse="Av. du Rothorn 20\nCP 645";
		$this->emetteur->cp="CH - 3960";
		$this->emetteur->ville="Sierre";
		$this->emetteur->departement_id=NULL;
		$this->emetteur->pays_id="6";
		$this->emetteur->pays_code="CH";
		$this->emetteur->pays="Suisse";
		$this->emetteur->country="Suisse";
		$this->emetteur->email="virginie@sosweb.ch";
		$this->emetteur->url="http://www.sosweb.ch";
		$this->emetteur->gencod=NULL;
		$this->emetteur->siren=NULL;
		$this->emetteur->siret=NULL;
		$this->emetteur->ape=NULL;
		$this->emetteur->idprof1=NULL;
		$this->emetteur->idprof2=NULL;
		$this->emetteur->idprof3=NULL;
		$this->emetteur->idprof4=NULL;
		$this->emetteur->prefix_comm=NULL;
		$this->emetteur->tva_assuj=0;
		$this->emetteur->tva_intra=NULL;
		$this->emetteur->localtax1_assuj=0;
		$this->emetteur->localtax2_assuj=0;
		$this->emetteur->capital="CHF 25'000.- soit ~ 20'000.-";
		$this->emetteur->typent_id=0;
		$this->emetteur->typent_code=3;
		$this->emetteur->effectif_id=1;
		$this->emetteur->forme_juridique_code=54;
		$this->emetteur->forme_juridique=NULL;
		$this->emetteur->mode_reglement_id=5;
		$this->emetteur->mode_reglement="sosweb mode_reglement";
		$this->emetteur->cond_reglement="sosweb cond_reglement";
		$this->emetteur->logo="sos-logo.png";
		$this->mode_reglement_code="VAD";
		$this->paypal_url="http://protocall.fr/dolibarr/public/paypal/newpayment.php?source=invoice&ref=FA1111-0003";
		$sw_paypal_btn = 	<<<PPL
			<form action="https://www.sandbox.paypal.com/cgi-bin/webscr" method="post">
			<input type="hidden" name="cmd" value="_xclick">
			<input type="hidden" name="business" value="vente_1321544902_biz@sosweb.ch">
			<input type="hidden" name="lc" value="FR">
			<input type="hidden" name="item_name" value="1er acompte s/ WWW-Pack Site internet">
			<input type="hidden" name="item_number" value="WWW-1000">
			<input type="hidden" name="amount" value="500.00">
			<input type="hidden" name="currency_code" value="EUR">
			<input type="hidden" name="button_subtype" value="services">
			<input type="hidden" name="no_note" value="0">
			<input type="hidden" name="bn" value="PP-BuyNowBF:btn_paynowCC_LG.gif:NonHostedGuest">
			<input type="image" src="https://www.sandbox.paypal.com/fr_FR/FR/i/btn/btn_paynowCC_LG.gif" border="0" name="submit" alt="PayPal - la solution de paiement en ligne la plus simple et la plus sécurisée !">
			<img alt="" border="0" src="https://www.sandbox.paypal.com/fr_FR/i/scr/pixel.gif" width="1" height="1">
			</form>
PPL;
		$this->paypal_btn=$sw_paypal_btn;
		$this->alain1 ="blabla 1";
		$object->alain2 ="blabla 2";
		// $conf->global->FACTURE_RIB_NUMBER="SOSWEB.CH - rib number";

		// print "<pre>";
		//var_dump($this->emetteur);
		//die("debugging!!!");

function PutLink($object, $URL, $txt)
{
    // Place un hyperlien
    $object->SetTextColor(0,0,255);
    $object->SetStyle('U',true);
    $object->Write(5,$txt,$URL);
    $object->SetStyle('U',false);
    $object->SetTextColor(0);
}
?>