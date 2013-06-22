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
ini_set('precision', 20);

class Jumpage
{
	protected $_cfg;
	
	public $baseurl;
	
	public $loadCacheFile = false;
	
	public $profile = array();
	public $images = array();
	public $posts = array();
	
	public $locale = 'en_US';
	public $lc = array();
	
	public $version = 'jumpage Framework 0.9';
	
	private $_notes = false;
	
	public function __construct($template='', $cfgfile='jumpage.config.php', $cfg=false)
	{
		if(!file_exists($cfgfile))
		{
			exit('CONFIG FILE NOT FOUND! ' . $cfgfile);
		}
		
		$config = include($cfgfile);
		
		if(isset($config['template']))
		{
			if(file_exists($config['template']))
			{
				$template = $config['template'];
			}
		}
		
		if(false !== $cfg)
		{
			if(!empty($cfg['fbAccessToken']))
			{
				$config['fbAccessToken'] = $cfg['fbAccessToken'];
			}
			
			if(!empty($cfg['fbWallId']))
			{
				$config['fbWallId'] = $cfg['fbWallId'];
			}
			
			if(!empty($cfg['fbLocale']))
			{
				$config['fbLocale'] = $cfg['fbLocale'];
			}
		}
		
		if(isset($config['fbAccessToken']))
		{
			if(strlen(trim($config['fbAccessToken'])) < 9)
			{
				exit('PAGE ACCESS TOKEN required! Get yours on <a href="http://jumpage.net/app">jumpage.net/app</a>');
			}
		}
		
		if(!empty($config['fbLocale']))
		{
			$this->locale = $config['fbLocale'];
		}
		
		$lang = strtolower(substr($this->locale, 0, 2));
		$lcfile = rtrim(__DIR__, '/') . '/locale/jumpage.' . $lang . '.php';
		
		if(!file_exists($lcfile))
		{
			$this->lc = include 'locale/jumpage.en.php';
		}
		else
		{
			$this->lc = include $lcfile;
		}
		
		$this->_cfg = (object) $this->_initConfig($config);
		$this->baseurl = $this->_url();
		
		$item = $this->url_get_contents($this->_cfg->secureGraphUrl
			. $this->_cfg->fbUserName . '/notes?limit=1&access_token='
			. $this->_cfg->fbAccessToken
		);
		
		if(isset($item->error))
		{
			if(empty($_GET['cache']) && file_exists(CACHE_FILE_NAME))
			{
				$this->loadCacheFile = true;
				
				return false;
			}
			elseif($item->error->type == 'OAuthException')
			{
				exit('PAGE ACCESS TOKEN invalid! Get yours on <a href="http://jumpage.net/app">jumpage.net/app</a>'); // (' . $item->error->message . ')');
			}
			else
			{
				exit('AN ERROR OCCURRED (' . $item->error->message . ')');
			}
		}
		
		$this->_initProfile();
		$this->_initPosts();
		$this->_initImages();
		
		if(!JUMPAGE_PREVIEW_MODE && APPLICATION_ENV == 'production')
		{
			if(!empty($this->_cfg->createLegalNote))
			{
				$legalNoteId = $this->_initLegalNote($cfgfile, $this->profile['is_published']);
				
				if(!empty($this->_cfg->notes['legal']))
				{
					$this->_cfg->notes['legal'] = $legalNoteId;
				}
			}
			
			if(isset($this->_cfg->createIcons))
			{
				if($this->_cfg->createIcons)
				{
					try
					{
						$this->_initGraphTouchFavIcons();
					}
					catch(Exception $e){}
				}
			}
		}
		
		if($template != '')
		{
			if(file_exists($template))
			{
				$this->run($template);
			}
		}
	}
	
	private function _initConfig($config)
	{
		$config['defaultFacebookUrl'] = 'http://www.facebook.com/';
		$config['secureFacebookUrl'] = 'https://www.facebook.com/';
		$config['defaultGraphUrl'] = 'http://graph.facebook.com/';
		$config['secureGraphUrl'] = 'https://graph.facebook.com/';
		
		$config['fbUserName'] = $config['fbWallId'];
		
		if(empty($config['fbAccessToken']))
		{
			if(!empty($config['accessToken']))
			{
				$config['fbAccessToken'] = $config['accessToken'];
			}
		}
		
		return $config;
	}
	
	private function _initLegalNote($cfgfile, $published)
	{
		$fbLegalNoteId = trim(str_replace(
			'[LEGAL_NOTE_ID_PLACEHOLDER]', '', 
			$this->_cfg->notes['legal']
		));
		
		if(is_numeric($fbLegalNoteId))
		{
			return $fbLegalNoteId;
		}
		
		$fbWallId = $this->_cfg->fbWallId;
		$legalNoteTitle = 'Impressum';
		$legalNoteMsg = '';
		
		$fql = "SELECT note_id FROM note WHERE uid='" . $fbWallId 
			. "' AND strpos(title, '" . $legalNoteTitle . "') >= 0";
		
		$item = $this->getByFqlQuery($fql);
		
		if(isset($item[0]->note_id))
		{
			$fbLegalNoteId = $item[0]->note_id;
		}
		else
		{
			$fql = "SELECT attachment.href FROM stream WHERE source_id='"
				. $fbWallId . "' AND actor_id='"
				. $fbWallId . "' AND type=66 "
				. "AND app_id=330339440353654"; // jumpage
			
			if($item = $this->getByFqlQuery($fql))
			{
				if(!empty($item[0]->attachment->href))
				{
					$fbLegalNoteId = strval(ltrim(
						strrchr($item[0]->attachment->href, '/'),
					'/'));
				}
			}
		}
		
		if($fbLegalNoteId == '' && $published)
		{
			$legalNoteTitle = $this->localize('WelcomeToJumpageNoteTitle', 'Welcome to jumpage');
			$legalNoteMsg = $this->localize('WelcomeToJumpageNoteContent', 
				'jumpage is easy: You publish on Facebook. jumpage publishes your website.');
				
			$url = $this->_cfg->secureGraphUrl . $this->_cfg->fbUserName . '/notes';
			
			$fields = array(
				'access_token' => urlencode($this->_cfg->fbAccessToken),
				'subject' => urlencode($legalNoteTitle),
				'message' => urlencode($legalNoteMsg)
			);
			
			$fields_string = '';
			
			foreach($fields as $key=>$value)
			{
				if($fields_string != '')
				{
					$fields_string .= '&';
				}
				$fields_string .= $key . '=' . $value;
			}
			
			$ch = curl_init();
			
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, count($fields));
			curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
			
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			
			$result = curl_exec($ch);
			$json = @json_decode($result);
			
			curl_close($ch);
			
			if(isset($json->id))
			{
				$fbLegalNoteId = $json->id;
			}
				
		}
		
		$fbLegalNoteId = trim($fbLegalNoteId);
		
		if(is_numeric($fbLegalNoteId))
		{
			$config = file_get_contents($cfgfile);
			
			$config = str_replace(
					'[LEGAL_NOTE_ID_PLACEHOLDER]',
					$fbLegalNoteId,
					$config
			);
			
			file_put_contents($cfgfile, $config);
		}
		
		return $fbLegalNoteId;
	}
	
	private function _initProfile()
	{
		$this->profile = (array) $this->url_get_contents($this->_cfg->secureGraphUrl 
			. $this->_cfg->fbUserName . '?access_token=' . $this->_cfg->fbAccessToken
		);
		
		if(isset($this->profile['location']))
		{
			if(!empty($this->profile['location']->street))
			{
				$this->profile['street'] = $this->profile['location']->street;
			}
			
			if(!empty($this->profile['location']->zip))
			{
				$this->profile['zip'] = $this->profile['location']->zip;
			}
			
			if(!empty($this->profile['location']->city))
			{
				$this->profile['city'] = $this->profile['location']->city;
			}
			
			if(!empty($this->profile['location']->latitude))
			{
				$this->profile['latitude'] = $this->profile['location']->latitude;
			}
			
			if(!empty($this->profile['location']->longitude))
			{
				$this->profile['longitude'] = $this->profile['location']->longitude;
			}
			
		}
		
		$basePageFields = 'name, type, website, about, description, phone, categories, hours, is_published';
		
		if(!empty($this->_cfg->fbInitPageFields))
		{
			$basePageFields = trim($this->_cfg->fbInitPageFields);
		}
		
		$fql = 'SELECT ' . $basePageFields . ' FROM page WHERE page_id=' 
			. $this->_cfg->fbWallId;
			
		$items = $this->getByFqlQuery($fql);
		
		foreach($items[0] as $key => $value)
		{
			$this->profile[$key] = $value;
		}
		
		$this->profile['type'] = mb_strtolower($this->profile['type'], 'UTF-8');
		$this->profile['type'] = ucwords($this->profile['type']);
		
		
		if(isset($this->profile['categories']))
		{
			$helper = '';
			
// 			if(!empty($this->profile['category']))
// 			{
// 				$helper = $this->profile['category'];
// 			}
			
			foreach ($this->profile['categories'] as $category)
			{
				if($helper != '')
				{
					$helper .= ', ';
				}
				
				$helper .= $category->name;
			}
			
			$this->profile['categories'] = $helper;
		}
		
		$this->profile['categories'] = ''; // TBD
		
			
		// Map fields for description
		if(empty($this->profile['description']))
		{
			if(!empty($this->profile['bio']))
			{
				$this->profile['description'] = trim($this->profile['bio']);
			}
		}
		
		$this->profile['about'] = htmlspecialchars($this->profile['about']);
	}
	
	private function _initImages()
	{
		if(!empty($this->_cfg->fbAlbumId))
		{
			$aid = $this->_cfg->fbWallId . '_' . $this->_cfg->fbAlbumId;
			$this->images = $this->getImages($aid, 'position', 24);
		}
		
// 		$this->images = $this->url_get_contents($this->_cfg->defaultGraphUrl 
// 			. $this->_cfg->fbAlbumId . '/photos' /* . '?access_token=' 
// 			. $this->_cfg->fbAccessToken*/);
	}
	
	private function _initGraphTouchFavIcons()
	{
		$url = $this->_cfg->defaultGraphUrl
			. $this->_cfg->fbUserName 
			. '/picture?type=large';
		
		$imgtmp = './fbpicture.temp.jpg';
	
		$ch = curl_init($url);
		$fp = fopen($imgtmp, 'wb');
	
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		
		curl_exec($ch);
		
		curl_close($ch);
		
		fclose($fp);
		
		$imgnfo = getimagesize($imgtmp);
		
		$img_w = $crop_w = $imgnfo[0]; // width
		$img_h = $crop_h = $imgnfo[1]; // height
		
		$max_w = $max_h = 200;
		$x = $y = intval(0);
		
		$t = $imgnfo[2]; // type
		$s = $img_w/$img_h; // scale
		
		
		if($max_w > 0 && $max_w < $img_w)
		{
			$img_w = $max_w;
			$img_h = $img_w / $s;
		}
		
		if($max_h > 0 && $max_h < $img_h)
		{
			$img_h = $max_h;
			$img_w = $img_h * $s;
		}
		
		if($img_h < $max_h)
		{
			$img_h = $max_h;
			$img_w = $img_h * $s;
		}
	
		if($img_w < $max_w)
		{
			$img_w = $max_w;
			$img_h = $img_w / $s;
		}
		
		switch($t)
		{
			case IMAGETYPE_JPEG:
				$imgbin = imagecreatefromjpeg($imgtmp);
				break;
				
			case IMAGETYPE_GIF:
				$imgbin = imagecreatefromgif($imgtmp);
				break;
				
			case IMAGETYPE_PNG:
				$imgbin = imagecreatefrompng($imgtmp);
				break;
		}
		
		$image = imagecreatetruecolor($img_w, $img_h);
		
		imagecopyresampled(
			$image,
			$imgbin,
			0, 0, $x, $y,
			$img_w,
			$img_h,
			$crop_w,
			$crop_h
		);
		
		$x = intval(($img_w-$max_w) / 2);
		$y = intval(($img_h-$max_h) / 2);
	
		$x = $x > 0 ? $x-1 : 0;
		$y = $y > 0 ? $y-1 : 0;
	
		$cropimg = imagecreatetruecolor($max_w, $max_h);
	
		imagecopyresampled(
			$cropimg,
			$image,
			0, 0, $x, $y,
			$max_w,
			$max_h,
			$max_w,
			$max_h
		);
		
		$icon = imagecreatetruecolor(16, 16);
		
		imagecopyresampled(
			$icon, $cropimg,
			0, 0, 0, 0, 16, 16,
			$max_w, $max_h
		);
		
		imagejpeg($cropimg, './open-graph-icon.jpg', 100);
		imagepng($cropimg, './apple-touch-icon.png');
		
		imagedestroy($cropimg);
		imagedestroy($image);
		imagedestroy($imgbin);
		
		unlink($imgtmp);
		
		$p = array();
		$w = $h = 16;
		
		for($y=$h-1; $y>=0; $y--)
		{
			for($x=0; $x<$w; $x++)
			{
				$c = imagecolorat($icon, $x, $y);
				
				$a = ($c & 0x7F000000) >> 24;
				$a = (1 - ($a / 127)) * 255;
				
				$c &= 0xFFFFFF;
				$c |= 0xFF000000 & ($a << 24);
				
				$p[] = $c;
			}
		}
		
		$size = ($w * $h * 4) + ((ceil($w / 32) * 4) * $h) + 40;
		
		$data = pack('vvv', 0, 1, 1) . pack('CCCCvvVV', $w, $h, 0, 0, 1, 32, $size, 22) . 
			pack('VVVvvVVVVVV', 40, $w, ($h * 2), 1, 32, 0, 0, 0, 0, 0, 0);
		
		foreach($p as $c)
		{
			$data .= pack('V', $c);
		}
		
		while($w-- > 0)
		{
			$data .= pack('N', 0);
		}
		
		file_put_contents('./favicon.ico', $data);
		
		imagedestroy($icon);
	}
	
	private function _getPostImage($attachment)
	{
		$image = false;
		$where = '';
		
		if(isset($attachment->media))
		{
			foreach($attachment->media as $media)
			{
				if($media->type == 'photo')
				{
// 					if($media->photo->width > 600)
// 					{
// 						if(isset($media->photo->images))
// 						{
								
// 							$helper = $media->photo->images[count($media->photo->images)-1];
// 							$image['src'] = $helper->src;
// 							$image['width'] = $helper->width;
// 							$image['height'] = $helper->height;
							
// 							$fql = 'SELECT photo_id, src, width, height FROM photo_src '
// 									. 'WHERE width<960 AND photo_id='
// 									. $media->photo->fbid . ' LIMIT 1';
							
// 							$item = $this->getByFqlQuery($fql);
							
							
							if($pic = $this->url_get_contents(
								$this->_cfg->secureGraphUrl . '/' 
									. $media->photo->fbid . '?access_token='
										. $this->_cfg->fbAccessToken
							)){
								
								foreach($pic->images as $img)
								{
									if($img->width < 960)
									{
										$item = $img;
										break;
									}
								}
								
								$image = array();
								
								$image['pid'] = $media->photo->fbid;
								$image['src'] = $item->source; //$item[0]->src;
								$image['width'] = $item->width; // $media->photo->width; //$item[0]->width;
								$image['height'] = $item->height; //$media->photo->height; //$item[0]->height;
								$image['alt'] = $media->alt;
								$image['href'] = $media->href;
								$image['type'] = 'image';
								$image['origin'] = 'facebook';
								
								break;
							}
// 						}
						
// 					}
				} 
// 				elseif($media->type == 'link')
// 				{
// 					parse_str(parse_url($media->src, PHP_URL_QUERY), $query);
// 					$src = urldecode($query['url']);
					
// 					$image = array();
// 					$w = $h = 0;
					
// 					$image['src'] = $src; //$item[0]->src;
// 					$image['width'] = $w;;
// 					$image['height'] = $h;
// 					$image['alt'] = $media->alt;
// 					$image['href'] = $media->href;
					
// 					break;
// 				} 
				elseif($media->type == 'video')
				{
					$href = $media->href;
					
					if(strpos($href, 'youtube') !== false)
					{
						parse_str(parse_url($media->href, PHP_URL_QUERY), $query);
						$src = urldecode(@$query['v']);
						
						if($src != '') // youtube
						{
							$image = array();
							$w = $h = 300;
							
							$url = 'http://img.youtube.com/vi/' . $src . '/0.jpg';
	// 						$imginfo = getimagesize($url);
							
							$image['src'] = $url;
							$image['width'] = $w; // $imginfo[0];
							$image['height'] = $h; // $imginfo[0];
							$image['alt'] = $media->alt;
							$image['href'] = $media->href;
							$image['type'] = 'video';
							$image['origin'] = 'youtube';
							
							
	// 						$image['html'] = '<iframe class="ytplayer" width="800" height="600" src="http://www.youtube.com/embed/'
	// 							. $src . '?autoplay=0&amp;controls=0&amp;autohide=2&amp;showinfo=0"></iframe>';
	// 						$image['width'] = $w;;
	// 						$image['height'] = $h;
	// 						$image['alt'] = $media->alt;
	// 						$image['href'] = $media->href;
	// 						$image['type'] = 'video';
						
						}
					}
					elseif(strpos($href, 'facebook') !== false)
					{
						$image = array();
						$w = $h = 300;
						
						$url = 'https://fbexternal-a.akamaihd.net/safe_image.php?url=';
						$url .= urlencode(str_replace('_t.jpg', '_b.jpg', $media->src));
						
						$image['src'] = $url;
						$image['width'] = $w; // $imginfo[0];
						$image['height'] = $h; // $imginfo[0];
						$image['alt'] = $media->alt;
						$image['href'] = $media->href;
						$image['type'] = 'video';
						$image['origin'] = 'facebook';
						
						
					}
					
// 					elseif(strpos($href, 'vimeo') !== false)
// 					{
// 						$bits = explode('/', $href);
// 						$id = $bits[count($bits)-1];
						
// 						$image = array();
// 						$w = $h = 200;
							
// 						$image['html'] = '<iframe src="http://player.vimeo.com/video/' 
// 							. $id . '" width="800" height="600"></iframe>';
// 						$image['width'] = $w;;
// 						$image['height'] = $h;
// 						$image['alt'] = $media->alt;
// 						$image['href'] = $media->href;
// 						$image['type'] = 'video';
// 					}
					
					break;
				}
			}
		}
		
		return $image;
	}
	
	private function _initPosts()
	{
		/* Stream Types
			 11 - Group created
			 12 - Event created
			 46 - Status update
			 56 - Post on wall from another user
			 66 - Note created
			 80 - Link posted
			128 - Video posted
			247 - Photos posted
			237 - App story
			257 - Comment created
			272 - App story
			285 - Checkin to a place
			308 - Post in Group
		*/
		
		$timeLimit = time() - ($this->_cfg->fbDaysBack * 24 * 60 * 60);
		$itemLimit = intval($this->_cfg->fbMaxPosts);
		
		$fql = "SELECT type, created_time, message, description, attachment, permalink FROM stream "
		. "WHERE is_published AND post_id IN(SELECT post_id FROM stream WHERE type IN(46,80,247) AND NOT is_hidden "
		. "AND source_id='"
		. $this->_cfg->fbWallId . "' AND actor_id='"
		. $this->_cfg->fbWallId . "' AND created_time > "
		. $timeLimit;
		
		if(isset($this->_cfg->fbMinPostLen))
		{
			if(intval($this->_cfg->fbMinPostLen) > 0)
			{
				$fql .= " AND strlen(message) >= " . intval($this->_cfg->fbMinPostLen);
			}
		}
		
		$fql .= ") ORDER BY created_time DESC";
		
		if($itemLimit > 0)
		{
			$fql .= " LIMIT " . $itemLimit;
		}
		
		$items = $this->getByFqlQuery($fql);
		
		$colheight = array(0,0);
		
		$maxPostLen = 0;
		
		if(isset($this->_cfg->fbMaxPostLen))
		{
			if(intval($this->_cfg->fbMaxPostLen) > 0)
			{
				$maxPostLen = intval($this->_cfg->fbMaxPostLen);
			}
		}
		
		foreach($items as $item)
		{
			$message = trim(preg_replace('/(http.+)$/i', '[...]', $item->message));
			$image = $this->_getPostImage($item->attachment);
			
// 			if($message == '')
// 			{
// 				$message = $item->description;
// 			}
			
			$href = $item->permalink;
			
			$height = 0;
			
			if($image !== false)
			{
				$image = (object) $image;
// 				$href = $image->href;
				
				$height += $image->height;
				
				if($message == '' || $message == '[...]')
				{
					$message = $image->alt;
				}
				
			}
			
			if($maxPostLen > 0)
			{
				if(strlen($message) > $maxPostLen)
				{
					$words = explode(' ', $message);
					$message = '';
				
					foreach($words as $word)
					{
						if(strlen($message) > $maxPostLen)
						{
							break;
						}
							
						$message .= ' ' . $word;
					}
				
					$message = rtrim($message, '.') . '...';
				}
			}
			
			if(!(($message == '' || $message == '[...]') && $image === false))
			{
				$message = $this->_tidyFacebookMessage($message);
				
				if($message != '')
				{
// 					$height += $this->_getTextHeight($message);
					$height += 80;
				}
				
				$colnum = intval(0);
				
				if($colheight[0] > $colheight[1])
				{
					$colnum = intval(1);
				}
				
				$colheight[$colnum] += $height;
				
				$this->posts[] = (object) array(
					'message' => trim($message),
					'href' => $href,
					'type' => $item->type,
					'image' => $image,
					'height' => $height,
					'colnum' => $colnum
				);
				
// 				if($itemLimit > 0)
// 				{
// 					if(count($this->posts) >= $itemLimit)
// 					{
// 						break;
// 					}
// 				}
			}
		}
		
	}
	
	

	public function getAlbums()
	{
		$albums = $this->url_get_contents(
			$this->_cfg->secureGraphUrl
				. $this->_cfg->fbUserName . '/albums?access_token=' 
				. $this->_cfg->fbAccessToken 
		);
	}
	
	public function getAlbumsByType($type='normal', $aid='')
	{
		/* 
			The type of photo album. Can be one of
			
			profile: The album containing profile pictures
			mobile: The album containing mobile upload photos
			wall The album containing photos posted to a user's Wall
			normal: For all other albums.
		*/
		
		$where = "type='" . $type . "'";
		
		if($aid != '')
		{
			$where = "aid='" . $aid . "'";
		}
		
		$fql = array(
			"albums" => "SELECT aid, cover_pid, photo_count, name, description, link FROM album "
				. "WHERE visible='everyone' AND photo_count>0 AND owner='" . $this->_cfg->fbWallId . "' "
				. "AND " . $where . "",
			"covers" => "SELECT aid, src_big, src_big_width, src_big_height, caption "
				. "FROM photo WHERE pid IN(SELECT cover_pid FROM #albums)",
			"images" => "SELECT aid, src_big, src_big_width, src_big_height, caption "
				. "FROM photo WHERE aid IN(SELECT aid FROM #albums) "
				. "AND NOT (pid IN (SELECT cover_pid FROM #albums)) "
				. "ORDER BY created"
		);
		
		$json = json_encode($fql);
		
		$items = $this->getByFqlQuery($json);
		
		$albums = array();
		
		foreach ($items as $item)
		{
			if($item->name == 'albums')
			{
				foreach($item->fql_result_set as $album)
				{
					$albums[$album->aid] = $album;
				}
			}
			
			if($item->name == 'covers')
			{
				foreach($item->fql_result_set as $cover)
				{
					$albums[$cover->aid]->cover_pic = $cover;
					$albums[$cover->aid]->freshest_pic = $cover;
				}
			}
			
			if($item->name == 'images')
			{
				foreach($item->fql_result_set as $image)
				{
					$albums[$image->aid]->freshest_pic = $image;
				}
			}
				
		}
		
		return $albums;
	}
	
	public function getEvents(
			$shortdesc=2, 
			$sortdesc=true, 
			$sincenow=true, 
			$limit=10, 
			$fields='id,start_time,end_time') // id,name,description,start_time,location,venue,picture.type(large)
	{
		$events = array();
		$etimes = array();
		
		if($this->_cfg->fbAccessToken != '')
		{
			$url = $this->_cfg->secureGraphUrl
				. $this->_cfg->fbUserName . '/events?fields=' 
				. $fields . '&access_token='
				. $this->_cfg->fbAccessToken;
			
// 			if($sincenow)
// 			{
// 				$url .= '&since=now';
// 			}
			
// 			if($limit > 0)
// 			{
// 				$url .= '&limit=' . intval($limit);
// 			}
			
			$items = $this->url_get_contents($url);
			
			$where = '';
			
			foreach($items->data as $event)
			{
				if($where != '')
				{
					$where .= ',';
				}
				$where .= $event->id;
				
				$etimes['E' . $event->id] = array(
					'start_time' => $event->start_time,
					'end_time' => $event->end_time
				);
			}
			
			$fql = "SELECT eid, name, description, location, venue, start_time, end_time, pic_big "
				. "FROM event WHERE eid IN(" . $where . ")";
			
			if($sincenow)
			{
				$fql .= ' AND start_time > now()';
			}
			
			$fql .= ' ORDER BY start_time';
			
			if($limit > 0)
			{
				$fql .= ' LIMIT ' . $limit;
			}
			
			
			$items = $this->getByFqlQuery($fql);
			
			foreach($items as $event)
			{
// 				$datetime = strtotime($event->start_time);
// 				$imgsrc = $event->picture->data->url;

				$start_time = strtotime($etimes['E' . $event->eid]['start_time']);
				$end_time = strtotime($etimes['E' . $event->eid]['end_time']);
				
				$imgsrc = $event->pic_big;
				$imginfo = getimagesize($imgsrc);
				
				$picture = (object) array(
					'src' => $imgsrc,
					'width' => $imginfo[0],
					'height' => $imginfo[1]
				);
				
				$description = $event->description;
				
				if($shortdesc > 0)
				{
					$bits = explode(PHP_EOL, $description);
					
					$description = '';
					
					for($i=0; $i<count($bits); $i++)
					{
						$description .= $bits[$i];
						if($i >= $shortdesc)
						{
							break;
						}
					}
					
					$description = $this->_tidyFacebookMessage($description);
				}
				
				$daysdiff = round(abs($end_time-$start_time)/60/60/24);
				
				$events[] = (object) array(
					'id' => $event->eid,
					'name' => $event->name,
					'description' => $description,
					'location' => $event->location,
					'venue' => $event->venue,
					'datetime' => $start_time,
					'start_time' => $start_time,
					'end_time' => $end_time,
					'daysdiff' => $daysdiff,
					'picture' => $picture,
					'link' => 'http://www.facebook.com/' . $event->eid,
					'origin' => $event
				);
			}
			
		}
		
		return $events;
	}
	
// 	public function getPosts()
// 	{
	
// 	}
	
	public function getAlbumIdByName($fbname)
	{
		$fql = "SELECT aid FROM album WHERE type='normal' AND owner='"
			. $this->_cfg->fbWallId . "' AND name='" . $fbname . "'";
			
		$item = $this->getByFqlQuery($fql, 'en_US');
		
		return $item[0]->aid;
	}
	
	public function getImages($aid='', $order='created DESC', $limit=9, $width=850, $equal_w=false)
	{
		if($aid == '')
		{
			if(empty($this->_cfg->fbAlbumId))
			{
				return array();
			}
			
			$aid = $this->_cfg->fbWallId 
				. '_' . $this->_cfg->fbAlbumId;
		}
		
// 		$fql = "SELECT src, width, height FROM photo_src WHERE width > 960 "
// 			. "AND photo_id IN(SELECT object_id FROM photo WHERE aid='" . $aid . "')";
		
		$iswall = $aid == 'wall';
		
		$albumtypes = array(
			'profile',
			'mobile',
			'wall'
		);
		
		if(in_array($aid, $albumtypes))
		{
			$fql = "SELECT aid FROM album WHERE type='" . $aid . "' AND owner='" 
				. $this->_cfg->fbWallId . "'";
			
			$item = $this->getByFqlQuery($fql);
			$aid = $item[0]->aid;
		}
		
		if($aid == 'cover')
		{
			$aid = $this->getAlbumIdByName('Cover Photos');
		}
		
		$albums = explode(',', $aid);
		
		$fql = "SELECT aid, pid, object_id, link, caption FROM photo WHERE ";
		
		for($i=0; $i<count($albums); $i++)
		{
			if($i > 0)
			{
				$fql .= " OR ";
			}
			
			$fql .= "aid='" . $albums[$i] . "'";
		}
		
		if($iswall)
		{
			$timeLimit = time() - ($this->_cfg->fbDaysBack * 24 * 60 * 60);
			$fql .= " AND created > " . $timeLimit;
		}
		
		 $fql .= " ORDER BY " . $order; // created, position
		
		if($limit > 0)
		{
			$fql .= ' LIMIT ' . $limit;
		}
		
		$items = $this->getByFqlQuery($fql);
		$helper = array();
		
		$where = '';
		
		foreach($items as $item)
		{
			$helper[$item->object_id] = (object) array(
				'aid' => $item->aid,
				'pid' => $item->pid,
				'link' => $item->link,
				'caption' => $item->caption,
				'src' => '',
				'width' => 0,
				'height' => 0
			);
			
			if($where != '')
			{
				$where .= ',';
			}
			
			$where .= strval($item->object_id);
		}
		
		$operator = $equal_w ? '=' : '>';
		
		$fql = 'SELECT photo_id, src, width, height FROM photo_src '
			. 'WHERE width >= ' . $width . ' AND photo_id IN(' . $where . ') ORDER BY width';
		
		$items = $this->getByFqlQuery($fql);
// 		$images = array();
		
		foreach($items as $item)
		{
			$helper[$item->photo_id]->src = $item->src;
			$helper[$item->photo_id]->width = $item->width;
			$helper[$item->photo_id]->height = $item->height;
			
// 			$images[$item->photo_id] = $helper[$item->photo_id];
		}
		
		return $helper;
	}

	public function getNotes()
	{
		$notes = false;
		
		if($this->_cfg->fbAccessToken != '')
		{
			$fql = "SELECT note_id, title, content FROM note WHERE uid='"
				. $this->_cfg->fbUserName . "'";
			
			$notes = $this->getByFqlQuery($fql);
			
// 			$notes = $this->url_get_contents(
// 				$this->_cfg->secureGraphUrl
// 				. $this->_cfg->fbUserName . '/notes?access_token='
// 				. $this->_cfg->fbAccessToken
// 			);
		}
		
		return $notes;
	}

	public function getQuestions()
	{
		
	}
	
	public function getPost($fbPostId)
	{
	
	}
	
	public function getImage($fbImageId, $minImageWidth=850)
	{
		if($fbImageId == '') return false;
		
		if($item = $this->url_get_contents($this->_cfg->secureGraphUrl
			. $fbImageId . '?access_token=' . $this->_cfg->fbAccessToken
		)){
			$name = strip_tags(@$item->name);
			$name = preg_replace('/\s+/', ' ', $name);
			$name = str_replace('"', '', $name);
			
			if(isset($item->images))
			{
				foreach($item->images as $image)
				{
					if($image->width > $minImageWidth)
					{
						return (object) array(
							'href' => $item->link,
							'src' => $image->source,
							'width' => $image->width,
							'height' => $image->height,
							'alt' => trim($name)
						);
					}
				}
			}
		}
		return false;
	}
	
	public function getNote($fbNoteId, $stripTags=false, $lineBreaks=false, $className='')
	{
		if($this->_notes === false)
		{
			$this->_notes = $this->getNotes();
		}
		
		foreach($this->_notes as $note)
		{
			if($note->note_id == $fbNoteId)
			{
				$note->content = $this->_replaceSpecialCharacters($note->content);
				
				if($stripTags==true)
				{
					$note->content = preg_replace('/<\/p>?.<p>/i', "\n", $note->content);
					$note->content = strip_tags($note->content);
				}
				else
				{
// 					$note->content = str_replace('</p><p> </p><p>', '<br /><br />', $note->content);
// 					$note->content = str_replace(array('<div><p>','</p></div>', '<p>'), '', $note->content);
// 					$note->content = str_replace('</p>', '<br />', $note->content);
					
// 					if($className == '')
// 					{
// 						$note->content = '<p>' . $note->content . '</p>';
// 					}
// 					else
// 					{
// 						$note->content = '<p class="' . $className . '">' . $note->content . '</p>';
// 					}

// 					$note->content = '<p>' . str_replace(array(
// 							'<div><p>','</p></div>', '<p> </p>', '<p></p>'
// 					), '', $note->content) . '</p>';
				}
				
				if($lineBreaks)
				{
					$note->content = nl2br($note->content);
				}
				
				$note->content = preg_replace('/\s+/', ' ', $note->content);
				
				return (object) array(
					'id' => strval($fbNoteId),
					'subject' => $note->title,
					'message' => $note->content
				);
				
				break;
			}
		}
		
		return (object) array(
			'id' => strval($fbNoteId),
			'subject' => 'Note not found',
			'message' => 'The note ' . $fbNoteId . ' could not be found.'
		);
	}
	
	public function getQuestion($fbQuestionId)
	{
	
	}
	
	public function getOpeningTimes()
	{
		/*
		 "hours": {
		    "mon_1_open": "10:00",
		    "mon_1_close": "19:00",
		    "tue_1_open": "10:00",
		    "tue_1_close": "19:00",
		    "wed_1_open": "10:00",
		    "wed_1_close": "19:00",
		    "thu_1_open": "10:00",
		    "thu_1_close": "19:00",
		    "fri_1_open": "10:00",
		    "fri_1_close": "19:00",
		    "sat_1_open": "10:00",
		    "sat_1_close": "18:00"
		  }
		*/
		
		$open = array(
			'timedays' => array(),
			'day' => array(),
			'time' => array(),
			'val' => array(),
			'html' => ''
		);
		
		if(!empty($this->profile['hours']))
		{
			$weekdays = array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun');
			
			$times = array();
			$days = array();
			
			$timedays = array();
			$html = '';
			
			$comparer = '';
			$counter = 0;
			
			foreach($weekdays as $weekday)
			{
				$helper1 = $helper2 = array();
				
				foreach($this->profile['hours'] as $key => $value)
				{
					$bits = explode('_', $key);
					
					if($bits[0] == $weekday)
					{
						if($bits[1] == '1')
						{
							$helper1[] = $value;
						}
						elseif($bits[1] == '2')
						{
							$helper2[] = $value;
						}
					}
				}
				
				$helper = implode(' - ', $helper1);
				
				if(count($helper2) > 0)
				{
					$helper .= ', ' . implode(' - ', $helper2);
				}
				
				if(strlen($helper) > 5)
				{
					if($comparer == '')
					{
						$comparer = $helper;
					}
					
					if($comparer != $helper)
					{
						$comparer = $helper;
						$counter++;
					}
					
					$valkey = md5($helper . $counter);
					
					if(array_key_exists($valkey, $timedays))
					{
						$timedays[$valkey]['days'][] = $weekday;
					}
					else
					{
						$timedays[$valkey] = array(
							'time' => $helper,
							'days' => array($weekday)
						);
					}
				}
			}
			
			$open['timedays'] = $timedays;
			
			foreach($timedays as $timeday)
			{
				if($html != '')
				{
					$html .= '<br />';
				}
				
				if(count($timeday['days']) > 1)
				{
					$day = array_shift($timeday['days']);

					$html .= $this->localize($day) . ' - ';
					
					$day = array_pop($timeday['days']);
					
					$html .= $this->localize($day) . ' &nbsp; ';
					
					$html .= str_replace(', ', ' &nbsp; ', $timeday['time']);
				}
				else
				{
					$day = array_shift($timeday['days']);
					
					$html .= $this->localize($day) . ' &nbsp; ';
					
					$html .= str_replace(', ', ' &nbsp; ', $timeday['time']);;
				}
			}
			
			$open['html'] = $html;
			
			foreach($this->profile['hours'] as $key => $value)
			{
				$bits = explode('_', $key);
				if($bits[1] == '1')
				{
					$days[$bits[0]][] = $value;
				}
			}
			
			foreach($days as $key => $value)
			{
				$open['day'][$key] = implode(' - ', $value);
			}
			
			foreach($open['day'] as $key => $value)
			{
				$valkey = md5($value);
				$times[$valkey][] = $key;
				$open['val'][$valkey] = $value;
			}
			
			foreach($times as $key => $value)
			{
				$open['time'][$key] = implode(', ', $value) . '|' . $open['val'][$key];
			}
		}
		
		return $open;
	}
	
	public function getField($fbFieldName, $default='', $prefix='', $suffix='', $nlbr=false)
	{
		$data = array(
			$fbFieldName => ''
		);
		
// 		if(in_array($fbFieldName, $this->profile))
		if(isset($this->profile[$fbFieldName]))
		{
			$data[$fbFieldName] = $this->profile[$fbFieldName];
		}
		else
		{
			$data = (array) $this->url_get_contents(
				$this->_cfg->secureGraphUrl
					. $this->_cfg->fbUserName . '?fields='
					. $fbFieldName . '&access_token='
					. $this->_cfg->fbAccessToken
			);
		}
		
		if(empty($data[$fbFieldName]))
		{
			if($default != '')
			{
				return $prefix . $default . $suffix;
			}
			else
			{
				return '';
			}
		}
		
		$value = trim($data[$fbFieldName]);
		
		if(empty($value))
		{
			if($default != '')
			{
				return $prefix . $default . $suffix;
			}
			
			return '';
		}
		
		if($nlbr)
		{
			$value = nl2br($value);
		}

		$value = str_replace('<br>', '<br />', $value);
		$value = preg_replace('/\s+/', ' ', $value);

// 		return $prefix . htmlentities(trim($value), ENT_QUOTES, "UTF-8") . $suffix;
		return $prefix . trim($value) . $suffix;
	}
	
	public function getByFqlQuery($fql, $lc='')
	{
		if($this->_cfg->fbAccessToken != '')
		{
			$url = $this->_cfg->secureGraphUrl . '/fql?access_token='
				. $this->_cfg->fbAccessToken . '&q='
				. urlencode($fql);
			
			if($result = $this->url_get_contents($url, false, null, false, $lc))
			{
				return $result->data;
			}
			
		}
		
// 		if($this->_cfg->fqlProxyUrl != '')
// 		{
// 			$referer = $_SERVER['SERVER_NAME'];
// 			$options = array('http' => array(
// 				'header'=>array("Referer: $referer\r\n")
// 			));
			
// 			$context = stream_context_create($options);
// 			$url = $this->_cfg->fqlProxyUrl . '?q=' 
// 				. urlencode($fql);
			
// 			if($result = @$this->url_get_contents($url, false, $context))
// 			{
// 				return $result->data;
// 			}
// 		}
		
		return array();
	}
	
// 	private function _getAccessToken()
// 	{
// 		return @$this->url_get_contents($this->_cfg->secureGraphUrl
// 			. 'oauth/access_token?client_id='
// 			. $this->_cfg->fbAppId . '&client_secret='
// 			. $this->_cfg->fbAppSecret 
// 			. '&grant_type=client_credentials');
// 	}
	
	public static function loadCache($error='')
	{
// 		try
// 		{
			if(file_exists(CACHE_FILE_NAME))
			{
				exit(file_get_contents(CACHE_FILE_NAME));
			}
// 		}
// 		catch (Exception $e)
// 		{
// 			exit('Unable to load cached file ' . $error);
// 		}
		
		exit('Unable to load cached file ' . $error);
	}
	
	public function run($template)
	{
		require_once $template;
	}
	
	private function _bool($value)
	{
		$value = strtolower(strval($value));
		
		if(strlen($value) > 3)
		{
			$value = ini_get($value);
		}
		
		return in_array(trim($value), array(
			'yes', 'true', 'on', '1'
		));
	}
	
	public function url_get_contents($url, $use_include_path=false, $context=null, $debug=false, $lc='')
	{
		if(strpos($url, '?') === false)
		{
			$url .= '?format=json-strings';
		}
		else
		{
			$url .= '&format=json-strings';
		}
		
		if($lc == '')
		{
			$url .= '&locale=' .  $this->locale;
		}
		else
		{
			$url .= '&locale=' .  $lc;
		}
		
		$contents = '';
		
		try {
		
 	    if(function_exists('file_get_contents'))
 	    {
 	    	if($this->_bool('allow_url_fopen'))
 	    	{
 	    		$contents = @file_get_contents($url, $use_include_path, $context);
 	    	}
 	    }
		
	    if($contents == '')
	    {
	    	if(isset($this->_cfg->fbNoneSecureUrlOnly))
	    	{
	    		if($this->_cfg->fbNoneSecureUrlOnly)
	    		{
	    			$url = str_replace('https:', 'http', $url); // Some hosting provider do not support secure curl
	    		}
	    	}
	    	
	    	$c = curl_init();
	    	 
	    	curl_setopt ($c, CURLOPT_CONNECTTIMEOUT, 10);
	    	curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
	    	curl_setopt($c, CURLOPT_URL, $url);
	    	 
	    	$contents = curl_exec($c);
	    	$err = curl_getinfo($c, CURLINFO_HTTP_CODE);
	    	
	    	
	    	curl_close($c);
	    }
	    
	    } catch (Exception $e) {
	    	$error = (object) array('error' => (object) array(
	    		'message' => $e->message,
	    		'type' => 'LoadFromUrlException',
	    		'code' => 312
	    	));
	    	
	    	$contents = json_encode($error);
	    }
	    
	    if($contents != '')
	    {
	    
	    	if($contents = json_decode($contents))
	    	{
	    		return $contents;
	    		
	    		if(isset($contents->error))
	    		{
	    			$err_type = $contents->error->type; // Mostly OAuthException
	    			$err_msg = $contents->error->message;
	    			
// 	    			$this->loadCache(
// 	    				$err_type . ' (' . $err_msg . ')'
// 	    			);
	    			
	    			return false;
	    		}
	    		else
	    		{
	    			return $contents;
	    		}
	    	}
	    }
		
	    return false;
	}
	
	public function linkify($str)
	{
// 		$str = str_replace(array(
// 			'https://', 'http://'
// 		), '', $str);
		
		return preg_replace(
			'%\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))%s',
			'<a href="$1">$1</a>',
			$str
		);
	}
	
	public function localize($lckey, $default='', $prefix='', $suffix='', $linkify=false)
	{
		if(empty($this->lc[$lckey]))
		{
			if($default != '')
			{
				return $prefix . $default . $suffix;
			}
			else
			{
				return $default;
			}
		}
		
		$value = $this->lc[$lckey];
		
		if($linkify)
		{
			$value = $this->linkify($value);
		}
		
		return $prefix . $value . $suffix;
	}
	
	private function _replaceSpecialCharacters($str)
	{
// 		$str = str_replace(array(
// 			"\xe2\x80\x98", "\xe2\x80\x99", "\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x93", "\xe2\x80\x94", "\xe2\x80\xa6"
// 		), array("'", "'", '"', '"', '-', '--', '...'), $str);
		
// 		$str = str_replace(array(
// 			chr(145), chr(146), chr(147), chr(148), chr(150), chr(151), chr(133)), array(
// 			"'", "'", '"', '"', '-', '--', '...'), $str);
		
// 		return htmlentities($str, ENT_QUOTES, 'utf-8');
		
// 		$str = iconv('UTF-8', 'UTF-8//IGNORE//TRANSLIT', $str);
		
// 		$str = htmlentities($str, 0, 'UTF-8');
		
		$search  = array('&acirc;€“','&acirc;€œ','&acirc;€˜','&acirc;€™','&Acirc;&pound;','&Acirc;&not;','&acirc;„&cent;');
		$replace = array('-','&ldquo;','&lsquo;','&rsquo;','&pound;','&not;','&#8482;');
		
		$str = str_replace($search, $replace, $str);
		
		$search = array("&#39;", "\xc3\xa2\xc2\x80\xc2\x99", "\xc3\xa2\xc2\x80\xc2\x93", "\xc3\xa2\xc2\x80\xc2\x9d", "\xc3\xa2\x3f\x3f");
		$resplace = array("'", "'", ' - ', '"', "'");
		
		$str = str_replace($search, $replace, $str);
		
		$quotes = array(
				"\xC2\xAB"     => '"',
				"\xC2\xBB"     => '"',
				"\xE2\x80\x98" => "'",
				"\xE2\x80\x99" => "'",
				"\xE2\x80\x9A" => "'",
				"\xE2\x80\x9B" => "'",
				"\xE2\x80\x9C" => '"',
				"\xE2\x80\x9D" => '"',
				"\xE2\x80\x9E" => '"',
				"\xE2\x80\x9F" => '"',
				"\xE2\x80\xB9" => "'",
				"\xE2\x80\xBA" => "'",
				"\xe2\x80\x93" => "-",
				"\xc2\xb0"	   => "°",
				"\xc2\xba"     => "°",
// 				"\xc3\xb1"	   => "&#241;",
// 				"\x96"		   => "&#241;",
// 				"\xe2\x81\x83" => '&bull;',
				"\xd5" => "'",
				"\xe2\x80\xa6" => "..."
		);
		
		$str = strtr($str, $quotes);
		
// 		$str = utf8_encode(
// 			str_replace("\xA0", " ", utf8_decode($str)
// 		));
		
		$str = preg_replace('/\xC2\xA0/','',$str);
		
		return trim($str);
	}
	
	private function _tidyFacebookMessage($message)
	{
		$message = $this->_replaceSpecialCharacters($message);
		$message = preg_replace('/\s+/', ' ', $message);
		$message = strip_tags($message);
		
		$message = str_replace(array(
			'&', '> <'
		), array(
			'&amp;', '><'
		), $message);
		
		return trim($message);
		
		$bits = explode(' ', $message);
		
		$message = '<strong class="postbold">';
	
		for($i=0; $i<count($bits); $i++)
		{
			$message .= $bits[$i];
				
			if($i == 2)
			{
				$message .= '</strong>';
			}
				
			$message .= ' ';
				
			if(strlen($message) > 120)
			{
				break;
			}
		}
	
		if(count($bits) < 3)
		{
			$message .= '</strong>';
		}
	
		return trim($message);
	}
	
	private function _getTextHeight($txt, $maxLineWidth=600)
	{
		$height = 0;
		
		return $height;
	}
	
	private function _url()
	{
		if(isset($_ENV['SCRIPT_URI']))
		{
			$url = $_ENV['SCRIPT_URI'];
		}
		else
		{
			$url = 'http://' . $_SERVER['HTTP_HOST'] 
				. $_SERVER['REQUEST_URI'];
		}
		
		$url = str_replace(array(
			'/clearcache',
			'/index.php'
		), '', $url);
		
		$url = parse_url($url);
		
		$host = rtrim(@$url['host'], '/');
		$path = ltrim(@$url['path'], '/');
		
		return rtrim($host . '/' . $path, '/');
	}
	
	public function formatAsWebUrl($str, $hasFileExt=false, $separator='-', $prefix='')
	{
		$str = mb_strtolower($str, 'UTF-8');
		$fileInfo = null;
		$fileExt = '';
		
		if($hasFileExt)
		{
			$fileInfo= pathinfo($str);
			$fileExt = '.' . $fileInfo['extension'];
			$str = str_replace($fileExt, '', $fileInfo['basename']);
		};
		
		$find = array('/ä/', '/ö/', '/ü/', '/ß/', '/ç/', '/ø/');
		$replace = array('ae', 'oe', 'ue', 'ss', 'c', 'o');
		
		$str = preg_replace($find, $replace, $str);
		
		$str = urldecode($str);
		
		$str = preg_replace("/[\\057]+/", " ", $str); // 057 = SLASH
		$str = preg_replace("/ /", "_", $str);
		$str = preg_replace("/_/", "-", $str);
		$str = preg_replace("/[^a-z0-9-]+/", "-", $str);
		$str = preg_replace ("/[\\055]+/", '-', $str); // 055 = DASH
		$str = preg_replace ("/^[\\055]+/", '', $str); // 055 = DASH
		$str = preg_replace ("/[\\055]+$/", '', $str); // 055 = DASH
		$str = preg_replace ("/[\\055]+/", $separator, $str); // 055 = DASH
		
		if($prefix != '')
		{
			if(!stristr($str, $prefix . $separator))
			{
				$str = $prefix . $separator . $str;
			}
		}
		
		$helper = explode($separator, $str);
		$str = '';
		
		for($i=0;$i<sizeof($helper);$i++)
		{
			$bit = trim( $helper[$i] );
			
			if( $bit != '')
			{
				if( $str != '')
				{
					$str .= $separator;
				}
				$str .= $bit;
			}
		}
		
		$str .= $fileExt;
		
		return $str;
	}
	
	
}

set_exception_handler(array('Jumpage', 'loadCache'));


