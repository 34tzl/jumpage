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
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see http://www.gnu.org/licenses.
 *  
 *  @author Ralf Langebrake
 *  @link jumpage.net/app
 *
 */

define('CACHE_FILE_NAME', 'jumpage.temp.htm');
define('CACHE_EXPIRE_MINUTES', 24*60);

header('Content-Type: text/html; charset=utf-8');
header('X-UA-Compatible: IE=Edge,chrome=1');
//header('imagetoolbar: no');

ini_set('allow_url_fopen', 'On');

if(!empty($_GET['cache']))
{
	if($_GET['cache'] == 'clear')
	{
		if(file_exists(CACHE_FILE_NAME))
		{
			unlink(CACHE_FILE_NAME);
		}
	}
}

ob_start("ob_gzhandler");

$filemtime = @filemtime(CACHE_FILE_NAME);

if($filemtime !== false && (
	time() - $filemtime < (60 * CACHE_EXPIRE_MINUTES)))
{
	exit(file_get_contents(CACHE_FILE_NAME));
}

require_once "jumpage.library.php";

$jp = new Jumpage('jumpage.phtml');

file_put_contents(
CACHE_FILE_NAME, ob_get_contents()
);


