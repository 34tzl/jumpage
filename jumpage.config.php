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
$config = array(
	'accessToken' => '', /* YOUR LONGLIVE FACEBOOK PAGE ACCESS TOKEN jumpage.net/app */
	'fbWallId' => '', /* YOUR FACEBOOK WALL ID e.g. 288129804540930 */
	'fbAlbumId' => '', /* YOUR INITIAL FACEBOOK ALBUM ID e.g. 85329 */
	'fbUserName' => '', /* THE FACEBOOK USER NAME OF THE PAGE e.g.jumpage OR WALL ID  */
	'fbLocale' => 'en_US',
	'fbDaysBack' => 30,
	'fbMaxPosts' => 9,
	'fbMinPostLen' => 0,
	'fbMaxPostLen' => 240,
	'template' => 'jumpage.phtml',
	'googlePlacesLink' => '', // e.g. http://goo.gl/maps/5Gnko
	'googleAnalyticsWebpropertyId' => '' // UA-XXXXXXXX-X
);
