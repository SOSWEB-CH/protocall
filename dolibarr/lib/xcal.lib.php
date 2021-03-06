<?php
/* Copyright (C) 2008 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *  \file       htdocs/lib/xcal.lib.php
 *  \brief      Function to manage calendar files (vcal/ical/...)
 *  \version    $Id: xcal.lib.php,v 1.34 2010/11/21 16:23:26 eldy Exp $
 */

/**
 *	Build a file from an array of events
 *  All input params and data must be encoded in $conf->charset_output
 *	@param		format				'vcal' or 'ical'
 *	@param		title				Title of export
 *	@param		desc				Description of export
 *	@param		events_array		Array of events ('eid','startdate','duration','enddate','title','summary','category','email','url','desc','author')
 *	@param		outputfile			Output file
 *	@return		int					<0 if ko, Nb of events in file if ok
 */
function build_calfile($format='vcal',$title,$desc,$events_array,$outputfile)
{
	global $conf,$langs;

	dol_syslog("xcal.lib.php::build_calfile Build cal file ".$outputfile." to format ".$format);

	if (empty($outputfile)) return -1;

	// Note: A cal file is an UTF8 encoded file
	$calfileh=fopen($outputfile,'w');
	if ($calfileh)
	{
		$now=mktime();

		$encoding='';
		if ($format == 'vcal') $encoding='ENCODING=QUOTED-PRINTABLE:';

		// Print header
		fwrite($calfileh,"BEGIN:VCALENDAR\n");
		fwrite($calfileh,"VERSION:2.0\n");
		fwrite($calfileh,"METHOD:PUBLISH\n");
		//fwrite($calfileh,"PRODID:-//DOLIBARR ".DOL_VERSION."//EN\n");
		fwrite($calfileh,"PRODID:-//DOLIBARR ".DOL_VERSION."\n");
		fwrite($calfileh,"CALSCALE:GREGORIAN\n");
		fwrite($calfileh,"X-WR-CALNAME:".$encoding.format_cal($format,$title)."\n");
		fwrite($calfileh,"X-WR-CALDESC:".$encoding.format_cal($format,$desc)."\n");
		//fwrite($calfileh,"X-WR-TIMEZONE:Europe/Paris\n");

		foreach ($events_array as $date => $event)
		{
			$eventqualified=true;
			if ($eventqualified)
			{
				// See http://fr.wikipedia.org/wiki/ICalendar for format
				// See http://www.ietf.org/rfc/rfc2445.txt for RFC
				$uid 		  = $event['uid'];
				$type         = $event['type'];
                $startdate    = $event['startdate'];
				$duration	  = $event['duration'];
				$enddate	  = $event['enddate'];
				$summary  	  = $event['summary'];
				$category	  = $event['category'];
                $priority     = $event['priority'];
                $fulldayevent = $event['fulldayevent'];
				$location     = $event['location'];
				$email 		  = $event['email'];
				$url		  = $event['url'];
				$transparency = $event['transparency'];		// OPAQUE (busy) or TRANSPARENT (not busy)
				$description=preg_replace('/<br[\s\/]?>/i',"\n",$event['desc']);
 				$description=dol_string_nohtmltag($description,0);	// Remove html tags
                $created      = $event['created'];
 				$modified     = $event['modified'];

				// Uncomment for tests
				//$summary="Resume";
				//$description="Description";
				//$description="MemberValidatedInDolibarr gd gdf gd gdff\nNom: tgdf g dfgdf gfd r ter\nType: gdfgfdf dfg fd gfd gd gdf gdf gfd gdfg dfg ddf\nAuteur: AD01fg dgdgdfg df gdf gd";

				// Format
				$summary=format_cal($format,$summary);
				$description=format_cal($format,$description);
				$category=format_cal($format,$category);
				$location=format_cal($format,$location);

				// Output the vCard/iCal VEVENT object
				/*
				Example from Google ical export for a 1 hour event:
                BEGIN:VEVENT
                DTSTART:20101103T120000Z
                DTEND:20101103T130000Z
                DTSTAMP:20101121T144902Z
                UID:4eilllcsq8r1p87ncg7vc8dbpk@google.com
                CREATED:20101121T144657Z
                DESCRIPTION:
                LAST-MODIFIED:20101121T144707Z
                LOCATION:
                SEQUENCE:0
                STATUS:CONFIRMED
                SUMMARY:Tache 1 heure
                TRANSP:OPAQUE
                END:VEVENT

                Example from Google ical export for a 1 day event:
                BEGIN:VEVENT
                DTSTART;VALUE=DATE:20101102
                DTEND;VALUE=DATE:20101103
                DTSTAMP:20101121T144902Z
                UID:d09t43kcf1qgapu9efsmmo1m6k@google.com
                CREATED:20101121T144607Z
                DESCRIPTION:
                LAST-MODIFIED:20101121T144607Z
                LOCATION:
                SEQUENCE:0
                STATUS:CONFIRMED
                SUMMARY:Tache 1 jour
                TRANSP:TRANSPARENT
                END:VEVENT
                */
				if ($type == 'event')
				{
					fwrite($calfileh,"BEGIN:VEVENT\n");
					fwrite($calfileh,"UID:".$uid."\n");
					if (! empty($email))
					{
						fwrite($calfileh,"ORGANIZER:MAILTO:".$email."\n");
						fwrite($calfileh,"CONTACT:MAILTO:".$email."\n");
					}
					if (! empty($url))
					{
						fwrite($calfileh,"URL:".$url."\n");
					};

                    if ($created)  fwrite($calfileh,"CREATED:".dol_print_date($created,'dayhourxcard',true)."\n");
                    if ($modified) fwrite($calfileh,"LAST-MODIFIED:".dol_print_date($modified,'dayhourxcard',true)."\n");
                    fwrite($calfileh,"SUMMARY:".$encoding.$summary."\n");
					fwrite($calfileh,"DESCRIPTION:".$encoding.$description."\n");
					/*
					// Status values for a "VEVENT"
					statvalue  = "TENTATIVE"           ;Indicates event is
				                                        ;tentative.
				                / "CONFIRMED"           ;Indicates event is
				                                        ;definite.
				                / "CANCELLED"           ;Indicates event was
				                                        ;cancelled.
				        ;

                    // Status values for "VTODO".
                    statvalue  =/ "NEEDS-ACTION"       ;Indicates to-do needs action.
				                / "COMPLETED"           ;Indicates to-do completed.
				                / "IN-PROCESS"          ;Indicates to-do in process of
				                / "CANCELLED"           ;Indicates to-do was cancelled.


                    // Status values for "VJOURNAL".
				    statvalue  =/ "DRAFT"              ;Indicates journal is draft.
				                / "FINAL"               ;Indicates journal is final.
				                / "CANCELLED"           ;Indicates journal is removed.

					*/
					if (! empty($location)) fwrite($calfileh,"LOCATION:".$encoding.$location."\n");
					//fwrite($calfileh,"CLASS:PUBLIC\n");				// PUBLIC, PRIVATE, CONFIDENTIAL

					// Date must be GMT dates
					// Current date
					fwrite($calfileh,"DTSTAMP:".dol_print_date($now,'dayhourxcard',true)."\n");
					// Start date
                    $prefix='';
                    $startdatef = dol_print_date($startdate,'dayhourxcard',true);
                    if ($fulldayevent)
					{
                        $prefix=';VALUE=DATE';
					    $startdatef = dol_print_date($startdate,'dayxcard',false);     // Local time
					}
					fwrite($calfileh,"DTSTART".$prefix.":".$startdatef."\n");
                    // End date
					if ($fulldayevent)
					{
    					if (empty($enddate)) $enddate=dol_time_plus_duree($startdate,1,'d');
					}
					else
					{
                        if (empty($enddate)) $enddate=$startdate+$duration;
					}
                    $prefix='';
					$enddatef = dol_print_date($enddate,'dayhourxcard',true);
					if ($fulldayevent)
					{
                        $prefix=';VALUE=DATE';
					    $enddatef = dol_print_date($enddate+1,'dayxcard',false);
					    //$enddatef .= dol_print_date($enddate+1,'dayhourxcard',false);   // Local time
					}
                    fwrite($calfileh,"DTEND".$prefix.":".$enddatef."\n");
					fwrite($calfileh,'STATUS:CONFIRMED'."\n");
					if (! empty($transparency)) fwrite($calfileh,"TRANSP:".$transparency."\n");
					if (! empty($category)) fwrite($calfileh,"CATEGORIES:".$encoding.$category."\n");
					fwrite($calfileh,"END:VEVENT\n");
				}

				// Output the vCard/iCal VTODO object
				// ...
				//PERCENT-COMPLETE:39

				// Output the vCard/iCal VJOURNAL object
				if ($type == 'journal')
				{
					fwrite($calfileh,"BEGIN:VJOURNAL\n");
					fwrite($calfileh,"UID:".$uid."\n");
					if (! empty($email))
					{
						fwrite($calfileh,"ORGANIZER:MAILTO:".$email."\n");
						fwrite($calfileh,"CONTACT:MAILTO:".$email."\n");
					}
					if (! empty($url))
					{
						fwrite($calfileh,"URL:".$url."\n");
					};

                    if ($created)  fwrite($calfileh,"CREATED:".dol_print_date($created,'dayhourxcard',true)."\n");
                    if ($modified) fwrite($calfileh,"LAST-MODIFIED:".dol_print_date($modified,'dayhourxcard',true)."\n");
					fwrite($calfileh,"SUMMARY:".$encoding.$summary."\n");
					fwrite($calfileh,"DESCRIPTION:".$encoding.$description."\n");
					fwrite($calfileh,'STATUS:CONFIRMED'."\n");
					fwrite($calfileh,"CATEGORIES:".$category."\n");
					fwrite($calfileh,"LOCATION:".$location."\n");
					fwrite($calfileh,"TRANSP:OPAQUE\n");
					fwrite($calfileh,"CLASS:CONFIDENTIAL\n");
					fwrite($calfileh,"DTSTAMP:".dol_print_date($startdatef,'dayhourxcard',true)."\n");

					fwrite($calfileh,"END:VJOURNAL\n");
				}


				// Put other info in comment
				/*
				$comment=array();
				$comment ['eid']			= $eid;
				$comment ['url']			= $linktoevent;
				$comment ['date']			= dol_mktime($evttime,"Ymd");
				$comment ['duration']		= $duration;
				$comment ['startdate']		= $startdate;
				$comment ['enddate']		= $enddate;
				fwrite($calfileh,"COMMENT:" . serialize ($comment) . "\n");
				*/

			}
		}

		// Footer
		fwrite($calfileh,"END:VCALENDAR");

		fclose($calfileh);
		if (! empty($conf->global->MAIN_UMASK))
			@chmod($outputfile, octdec($conf->global->MAIN_UMASK));
	}
	else
	{
		dol_syslog("xcal.lib.php::build_calfile Failed to open file ".$outputfile." for writing");
		return -2;
	}
}

/**
 *	\brief		Build a file from an array of events
 *              All input data must be encoded in $conf->charset_output
 *	\param		format				'rss'
 *	\param		title				Title of export
 *	\param		desc				Description of export
 *	\param		events_array		Array of events ('uid','startdate','summary','url','desc','author','category')
 *	\param		outputfile			Output file
 *	\param		filter				Filter
 *	\return		int					<0 if ko, Nb of events in file if ok
 */
function build_rssfile($format='rss',$title,$desc,$events_array,$outputfile,$filter='')
{
	global $user,$conf,$langs;
	global $dolibarr_main_url_root;

	dol_syslog("xcal.lib.php::build_rssfile Build rss file ".$outputfile." to format ".$format);

	if (empty($outputfile)) return -1;

	$fichier=fopen($outputfile,'w');
	if ($fichier)
	{
		$date=date("r");

		// Print header
		$html='<?xml version="1.0" encoding="'.$langs->charset_output.'"?>';
		fwrite($fichier, $html);
		fwrite($fichier, "\n");
		$html='<rss version="2.0">';
		fwrite($fichier, $html);
		fwrite($fichier, "\n");

		$html="<channel>\n".
		"<title>".$title."</title>\n";
		fwrite($fichier, $html);

		$html='<description><![CDATA['.$desc.'.]]></description>'."\n".
//		'<language>fr</language>'."\n".
		'<copyright>Dolibarr</copyright>'."\n".
		'<lastBuildDate>'.$date.'</lastBuildDate>'."\n".
		'<generator>Dolibarr</generator>'."\n";

		$url=$dolibarr_main_url_root;
		if (! preg_match('/\/$/',$url)) $url.='/';
		$url.='public/agenda/agendaexport.php?format=rss&exportkey='.urlencode($conf->global->MAIN_AGENDA_XCAL_EXPORTKEY);
		$html.='<link><![CDATA['.$url.']]></link>'."\n";
//		'<managingEditor>editor@example.com</managingEditor>'."\n"
//    	'<webMaster>webmaster@example.com</webMaster>'."\n"
//		'<ttl>5</ttl>'."\n"
//		'<image>'."\n"
//		'<url><![CDATA[http://www.lesbonnesannonces.com/images/logo_rss.gif]]></url>'."\n"
//		'<title><![CDATA[Dolibarr events]]></title>'."\n"
//		'<link><![CDATA[http://www.lesbonnesannonces.com/]]></link>'."\n"
//		'<width>144</width>'."\n"
//		'<height>36</height>'."\n"
//		'</image>'."\n";

		#print $html;
		fwrite($fichier, $html);


		foreach ($events_array as $date => $event)
		{
			$eventqualified=true;
			if ($filter)
			{
				// \TODO Add a filter

				$eventqualified=false;
			}

			if ($eventqualified)
			{
				$uid		  = $event['uid'];
				$startdate	  = $event['startdate'];
				$summary  	  = $event['summary'];
				$url		  = $event['url'];
				$author		  = $event['author'];
				$category	  = $event['category'];
				/* No place inside a RSS
                $priority     = $event['priority'];
                $fulldayevent = $event['fulldayevent'];
                $location     = $event['location'];
                $email        = $event['email'];
                */
				$description=preg_replace('/<br[\s\/]?>/i',"\n",$event['desc']);
 				$description=dol_string_nohtmltag($description,0);	// Remove html tags

				fwrite ($fichier, "<item>\n");
				fwrite ($fichier, "<title><![CDATA[".$summary."]]></title>"."\n");
				fwrite ($fichier, "<link><![CDATA[".$url."]]></link>"."\n");
				fwrite ($fichier, "<author><![CDATA[".$author."]]></author>"."\n");
				fwrite ($fichier, "<category><![CDATA[".$category."]]></category>"."\n");
				fwrite ($fichier, "<description><![CDATA[");
				if ($description) fwrite ($fichier, $description);
				//else fwrite ($fichier, 'NoDesc');
				fwrite ($fichier, "]]></description>\n");
				fwrite ($fichier, "<pubDate>".date("r", $startdate)."</pubDate>\n");
				fwrite ($fichier, "<guid isPermaLink=\"true\"><![CDATA[".$uid."]]></guid>\n");
				fwrite ($fichier, "<source><![CDATA[Dolibarr]]></source>\n");
				fwrite ($fichier, "</item>\n");
			}
		}

		fwrite($fichier, '</channel>');
		fwrite ($fichier, "\n");
		fwrite($fichier, '</rss>');

		fclose($fichier);
		if (! empty($conf->global->MAIN_UMASK))
			@chmod($outputfile, octdec($conf->global->MAIN_UMASK));
	}
}


/**
 * 	\brief		Encode for cal export
 * 	\param		format		vcal or ical
 * 	\param 		string		string to encode
 * 	\return		string		string encoded
 * 	\remarks	string must be encoded in conf->file->character_set_client
 */
function format_cal($format,$string)
{
	global $conf;

	if ($conf->file->character_set_client == 'ISO-8859-1') $newstring=utf8_encode($string);
	else $newstring=$string;

	// Now newstring is always UTF8 string
	if ($format == 'vcal')
	{
		$newstring=QPEncode($newstring);
	}
	if ($format == 'ical')
	{
		// Replace new lines chars by '\n'
		$newstring=preg_replace('/'."\r\n".'/i',"\n",$newstring);
		$newstring=preg_replace('/'."\n\r".'/i',"\n",$newstring);
		$newstring=preg_replace('/'."\n".'/i','\n',$newstring);
		// Must not exceed 75 char. Cut with "\r\n"+Space
		$newstring=CalEncode($newstring);
	}

	return $newstring;
}

/**
 *	\brief		Cut string after 75 chars. Add CRLF+Space.
 *	\param		string		String to convert
 *	\return		string 		String converted
 *	\remarks	line must be encoded in UTF-8
*/
function CalEncode($line)
{
	$out = '';

	$newpara = '';

	// If mb_ functions exists, it's better to use them
	if (function_exists('mb_strlen'))
	{
		for ($j = 0; $j <= mb_strlen($line, 'UTF-8') - 1; $j++)
		{
			$char = mb_substr ( $line, $j, 1, 'UTF-8' );	// Take char at position $j

			if ( ( mb_strlen ( $newpara, 'UTF-8') + mb_strlen ( $char, 'UTF-8' ) ) >= 75 )
			{
				$out .= $newpara . "\r\n ";	// CRLF + Space for cal
				$newpara = '';
			}
			$newpara .= $char;
		}
		$out .= $newpara;
	}
	else
	{
		for ($j = 0; $j <= dol_strlen($line) - 1; $j++)
		{
			$char = substr ( $line, $j, 1 );	// Take char at position $j

			if ( ( dol_strlen ( $newpara ) + dol_strlen ( $char ) ) >= 75 )
			{
				$out .= $newpara . "\r\n ";	// CRLF + Space for cal
				$newpara = '';
			}
			$newpara .= $char;
		}
		$out .= $newpara;
	}

	return trim($out);
}


function QPEncode($str,$forcal=0)
{
	$lines = preg_split("/\r\n/", $str);
	$out = '';

	foreach ($lines as $line)
	{
		$newpara = '';

		for ($j = 0; $j <= dol_strlen($line) - 1; $j++)
		{
			$char = substr ( $line, $j, 1 );
			$ascii = ord ( $char );

			if ( $ascii < 32 || $ascii == 61 || $ascii > 126 )
			$char = '=' . strtoupper ( sprintf("%02X", $ascii ) );

			if ( ( dol_strlen ( $newpara ) + dol_strlen ( $char ) ) >= 76 )
			{
				$out .= $newpara . '=' . "\r\n";	// CRLF
				if ($forcal) $out .= " ";		// + Space for cal
				$newpara = '';
			}
			$newpara .= $char;
		}
		$out .= $newpara;
	}
	return trim ( $out );
}

function QPDecode( $str )
{
	$out = preg_replace('/=\r?\n/', '', $str);
	$out = preg_replace('/=([A-F0-9]{2})/e', chr( hexdec ('\\1' ) ), $out);

	return trim($out);
}

?>