<?php
/**
 *  jumpage Your web concept Framework
 *  Copyright (C) 2012 Bureau BLEEN Design Development
 *  
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *  
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 *  
 *  You should have received a copy of the GNU General Public License
 *  along with this program. If not, see http://www.gnu.org/licenses.
 *  
 *  @author Ralf Langebrake
 *  @link jumpage.net
 *
 *  Install the jumpage Facebook App and get your strong Page Access
 *  Token on jumpage.net/app
 *  
 */
?><?php

function val($key)
{
	$value = '';
	
	if(isset($_POST[$key]))
	{
		$value = $_POST[$key];
	}
	
	return addslashes(htmlspecialchars(mysql_escape_string(
		stripslashes(strip_tags($value))
	)));
}

$validRequest = true;

if(isset($_ENV['SCRIPT_URI']))
{
	$bits = parse_url($_ENV['SCRIPT_URI']);
	$url = $bits['host'];
}
else
{
	$url = $_SERVER['HTTP_HOST'];
}

$referer = parse_url(
	$_SERVER['HTTP_REFERER'], PHP_URL_HOST
);

$validRequest = $validRequest && $url == $referer && val('validated') == 'true';

if(!$validRequest)
{
	exit(json_encode(array(
		'success' => false,
		'message' => 'Invalid request'
	)));
}

$toName = val('toname');
$toMail = val('tomail');

$fromName = $toName; //val('fullname');
$fromMail = $toMail; //val('email');

$subject = val('subject');
$textBody = val('message');

$salutation = trim(val('salutation'));
$senderName = trim(val('fullname'));
$senderMail = trim(val('email'));

$replyToMail = $senderMail;
$replyToName = stripslashes($senderName);

$textBody = "\n" . stripslashes($textBody) . "\n\n\n"
	. $salutation . " " . stripslashes($senderName) . "\n"
	. $senderMail . "\n\n\n"
	. "---\njumpage Your web concept\nwww.jumpage.net";


$header = 'From: ' . utf8_decode($fromName) . ' <' . $fromMail . '>' . "\r\n" .
		'Reply-To: ' . $replyToName . ' <' . $replyToMail . '>' . "\r\n" .
		'Content-type: text/plain; charset=UTF-8' . "\r\n";
		'X-Mailer: jumpage Framework PHP/' . phpversion();

if(mail(utf8_decode($toName) . ' <' . $toMail . '>', $subject, $textBody, $header))
{
	exit(json_encode(array(
		'success' => true,
		'message' => val('success')
	)));
}

exit(json_encode(array(
	'success' => false,
	'message' => 'Mail send error'
)));



