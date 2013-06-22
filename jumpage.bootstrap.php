<?php
/**
 *  jumpage Framework
 *  Copyright (C) 2012-2013 Bureau BLEEN OHG
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
if(version_compare(PHP_VERSION, '5.2.0', '<'))
{
	exit('PHP version must be 5.2.0 or higher. Your version: ' . PHP_VERSION);
}

defined('__DIR__') || define(
	'__DIR__', dirname(__FILE__)
);

defined('APPLICATION_ENV') || define(
	'APPLICATION_ENV', (getenv('APPLICATION_ENV') 
		? getenv('APPLICATION_ENV') : 'production')
);

defined('JUMPAGE_CONFIG_PATH') || define(
	'JUMPAGE_CONFIG_PATH', 'jumpage.config.php'
);

defined('JUMPAGE_PREVIEW_MODE') || define(
	'JUMPAGE_PREVIEW_MODE', false
);

defined('JUMPAGE_TEMPLATE_PATH') || define(
	'JUMPAGE_TEMPLATE_PATH', 'jumpage.phtml'
);

defined('PAGE_ACCESS_TOKEN') || define(
	'PAGE_ACCESS_TOKEN', false
);

defined('PAGE_WALL_ID') || define(
	'PAGE_WALL_ID', false
);

defined('FACEBOOK_PAGE_LOCALE') || define(
	'FACEBOOK_PAGE_LOCALE', false
);

if(!in_array('curl', get_loaded_extensions()))
{
	exit('PHP cURL not loaded');
}

$_JUMPAGE_CONFIG_INFO = false;

if(false !== PAGE_ACCESS_TOKEN && PAGE_WALL_ID)
{
	$_JUMPAGE_CONFIG_INFO = array(
		'fbAccessToken' => PAGE_ACCESS_TOKEN,
		'fbWallId' => PAGE_WALL_ID
	);
	
	if(false !== FACEBOOK_PAGE_LOCALE)
	{
		$_JUMPAGE_CONFIG_INFO['fbLocale'] = FACEBOOK_PAGE_LOCALE;
	}
}

$cache_file_name = rtrim(dirname(JUMPAGE_CONFIG_PATH), '/')
	. '/jumpage.cachefile.htm';

define('CACHE_FILE_NAME', $cache_file_name);

defined('CACHE_EXPIRE_MINUTES') || define(
	'CACHE_EXPIRE_MINUTES', 12*60
);

header('Content-Type: text/html; charset=utf-8');
header('X-UA-Compatible: IE=Edge,chrome=1');
// header('imagetoolbar: no');

$loadCacheFile = !JUMPAGE_PREVIEW_MODE;

if(!empty($_GET['cache']))
{
	if($_GET['cache'] == 'clear')
	{
		$loadCacheFile = false;
	}
}

ob_start("ob_gzhandler");

$filemtime = @filemtime(CACHE_FILE_NAME);

if($filemtime !== false)
{
	$loadCacheFile = $loadCacheFile 
		&& date('D', $filemtime) == date('D');
}

if($loadCacheFile)
{
	if($filemtime !== false && (
			time() - $filemtime < (60 * CACHE_EXPIRE_MINUTES)))
	{
		if(file_exists(CACHE_FILE_NAME))
		{
			exit(file_get_contents(CACHE_FILE_NAME));
		}
	}
}

require_once "jumpage.library.php";

$jp = new Jumpage(JUMPAGE_TEMPLATE_PATH, JUMPAGE_CONFIG_PATH, $_JUMPAGE_CONFIG_INFO);

if($jp->loadCacheFile)
{
	if(file_exists(CACHE_FILE_NAME))
	{
		exit(file_get_contents(CACHE_FILE_NAME));
	}
}

if(!$jp->loadCacheFile && file_exists(CACHE_FILE_NAME))
{
	unlink(CACHE_FILE_NAME);
}

if(!(JUMPAGE_PREVIEW_MODE || $jp->loadCacheFile))
{
	file_put_contents(CACHE_FILE_NAME, ob_get_contents());
}

if(!empty($_GET['cache']))
{
	$host = $_SERVER['HTTP_HOST'];
	
	if(isset($_ENV['SCRIPT_URI']))
	{
		$host = parse_url(
				$_ENV['SCRIPT_URI'], PHP_URL_HOST
		);
	}
	
	header('Location:http://' . rtrim($host, '/') . dirname($_SERVER['PHP_SELF']));
}
