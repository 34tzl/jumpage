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
ini_set('precision', 20);

class Jumpage
{
	protected $_cfg;
	
	public $baseurl;
	
	public $profile = array();
	public $images = array();
	public $posts = array();
	
	public $version = 'jumpage Framework 0.9';
	
	private $_notes = false;
	
	public function __construct($template='')
	{
		require_once "jumpage.config.php";
		
		if(isset($config['template']))
		{
			if(file_exists($config['template']))
			{
				$template = $config['template'];
			}
		}
		
		if(isset($config['accessToken']))
		{
			if(strlen(trim($config['accessToken'])) < 9)
			{
				exit('PAGE ACCESS TOKEN required! Get yours on <a href="http://jumpage.net/app">jumpage.net/app</a>');
			}
		}
		
		$this->baseurl = $this->_url();
		
		$this->_cfg = (object) $this->_initConfig($config);
		
		$this->_initProfile();
		$this->_initPosts();
		$this->_initImages();
		
		if($template != '')
		{
			if(file_exists($template))
			{
				$this->run($template);
			}
		}
	}
	
// 	public function getConfig()
// 	{
// 		return $this->_cfg;
// 	}
	
	private function _initConfig($config)
	{
		$config['defaultGraphUrl'] = 'http://graph.facebook.com/';
		$config['secureGraphUrl'] = 'https://graph.facebook.com/';
		
		return $config;
	}
	
	private function _initProfile()
	{
		$this->profile = (array) $this->url_get_contents(
			$this->_cfg->defaultGraphUrl . $this->_cfg->fbUserName
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
		
	}
	
	private function _initImages()
	{
		$aid = $this->_cfg->fbWallId
			. '_' . $this->_cfg->fbAlbumId;
		
		if(!empty($aid))
		{
			$this->images = $this->getImages($aid, 'position', 24);
		}
		
// 		$this->images = $this->url_get_contents($this->_cfg->defaultGraphUrl 
// 			. $this->_cfg->fbAlbumId . '/photos' /* . '?access_token=' 
// 			. $this->_cfg->accessToken*/);
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
							$image = array();
								
// 							$helper = $media->photo->images[count($media->photo->images)-1];
// 							$image['src'] = $helper->src;
// 							$image['width'] = $helper->width;
// 							$image['height'] = $helper->height;
							
// 							$fql = 'SELECT photo_id, src, width, height FROM photo_src '
// 									. 'WHERE width<960 AND photo_id='
// 									. $media->photo->fbid . ' LIMIT 1';
							
// 							$item = $this->getByFqlQuery($fql);
							
							
							$pic = $this->url_get_contents(
								$this->_cfg->defaultGraphUrl . '/' 
									. $media->photo->fbid
							);
							
							foreach($pic->images as $img)
							{
								if($img->width < 960)
								{
									$item = $img;
									break;
								}
							}
							
							$image['pid'] = $media->photo->fbid;
							$image['src'] = $item->source; //$item[0]->src;
							$image['width'] = $item->width; // $media->photo->width; //$item[0]->width;
							$image['height'] = $item->height; //$media->photo->height; //$item[0]->height;
							$image['alt'] = $media->alt;
							$image['href'] = $media->href;
							
							break;
// 						}
						
// 					}
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
			272 - App story 
		*/
		
		$timeLimit = time() - ($this->_cfg->fbDaysBack * 24 * 60 * 60);
		
		$fql = "SELECT type, created_time, message, description, attachment, permalink FROM stream "
		. "WHERE (type=46 OR type=80 OR type=247) AND source_id='"
		. $this->_cfg->fbWallId . "' AND actor_id='"
		. $this->_cfg->fbWallId . "' AND is_hidden=0 AND created_time > "
		. $timeLimit;
		
		if(isset($this->_cfg->fbMinPostLen))
		{
			if(intval($this->_cfg->fbMinPostLen) > 0)
			{
				$fql .= " AND strlen(message) >= " . intval($this->_cfg->fbMinPostLen);
				
// 				$fql .= " AND (strlen(message) >= " . intval($this->_cfg->fbMinPostLen);
// 				$fql .= " OR strlen(description) >= " . intval($this->_cfg->fbMinPostLen) . ")";
			}
		}
		
		$fql .= " ORDER BY created_time DESC LIMIT " . $this->_cfg->fbMaxPosts;

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
				$href = $image->href;
				
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
			}
		}
		
	}
	
	

	public function getAlbums()
	{
		$albums = $this->url_get_contents(
			$this->_cfg->defaultGraphUrl
				. $this->_cfg->fbUserName . '/albums' 
		);
	}
	
	public function getAlbumsByType($type='normal')
	{
		/* 
			The type of photo album. Can be one of
			
			profile: The album containing profile pictures
			mobile: The album containing mobile upload photos
			wall The album containing photos posted to a user's Wall
			normal: For all other albums.
		*/
		
		$fql = array(
			"albums" => "SELECT aid, cover_pid, photo_count, name, description, link FROM album "
				. "WHERE visible='everyone' AND photo_count>0 AND owner='" . $this->_cfg->fbWallId . "' "
				. "AND type='" . $type . "'",
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
	
	public function getEvents()
	{
		if($this->_cfg->accessToken != '')
		{
			$events = $this->url_get_contents(
				$this->_cfg->secureGraphUrl
					. $this->_cfg->fbUserName . '/events?access_token='
					. $this->_cfg->accessToken
			);
		}
	}
	
// 	public function getPosts()
// 	{
	
// 	}
	
	public function getImages($aid='', $order='created DESC', $limit=9)
	{
		if($aid == '')
		{
			$aid = $this->_cfg->fbWallId 
				. '_' . $this->_cfg->fbAlbumId;
		}
		
// 		$fql = "SELECT src, width, height FROM photo_src WHERE width > 960 "
// 			. "AND photo_id IN(SELECT object_id FROM photo WHERE aid='" . $aid . "')";
		
		$fql = "SELECT pid, object_id, link, caption FROM photo WHERE aid='" 
			. $aid . "' ORDER BY " . $order; // created, position
		
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
		
		$fql = 'SELECT photo_id, src, width, height FROM photo_src '
			. 'WHERE width > 960 AND photo_id IN(' . $where . ')';
		
		$items = $this->getByFqlQuery($fql);
		$images = array();
		
		foreach($items as $item)
		{
			$helper[$item->photo_id]->src = $item->src;
			$helper[$item->photo_id]->width = $item->width;
			$helper[$item->photo_id]->height = $item->height;
			
			$images[] = $helper[$item->photo_id];
		}
		
		return $images;
	}

	public function getNotes()
	{
		$notes = false;
		
		if($this->_cfg->accessToken != '')
		{
			$notes = $this->url_get_contents(
				$this->_cfg->secureGraphUrl
					. $this->_cfg->fbUserName . '/notes?access_token='
					. $this->_cfg->accessToken
			);
		}
		
		return $notes;
	}

	public function getQuestions()
	{
		
	}
	
	public function getPost($fbPostId)
	{
	
	}
	
	public function getImage($fbImageId, $minImageWidth=960)
	{
		if($item = $this->url_get_contents(
			$this->_cfg->defaultGraphUrl . $fbImageId))
		{
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
	
	public function getNote($fbNoteId, $stripTags=false)
	{
		if($this->_notes === false)
		{
			$this->_notes = $this->getNotes();
		}
		
		foreach($this->_notes->data as $note)
		{
			if($note->id == $fbNoteId)
			{
				if($stripTags==true)
				{
					$note->message = preg_replace('/<\/p>?.<p>/i', "\n", $note->message);
					$note->message = strip_tags($note->message);
				}
				else
				{
					$note->message = str_replace('</p><p> </p><p>', '<br /><br />', $note->message);
					$note->message = str_replace(array('<div><p>','</p></div>', '<p>'), '', $note->message);
					$note->message = str_replace('</p>', '<br />', $note->message);
					$note->message = '<p>' . $note->message . '</p>';
					
// 					$note->message = '<p>' . str_replace(array(
// 							'<div><p>','</p></div>', '<p> </p>', '<p></p>'
// 					), '', $note->message) . '</p>';
				}
				
				return $note;
				
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
			'day' => array(),
			'time' => array(),
			'val' => array()
		);
		
		if(!empty($this->profile['hours']))
		{
			$times = array();
			$days = array();
			
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
				$this->_cfg->defaultGraphUrl
					. $this->_cfg->fbUserName . '?fields='
					. $fbFieldName . '&access_token='
					. $this->_cfg->accessToken
			);
		}
		
		if(empty($data[$fbFieldName]))
		{
			return $prefix . $default . $suffix;
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
		
		return $prefix . trim($value) . $suffix;
	}
	
	public function getByFqlQuery($query)
	{
		if($this->_cfg->accessToken != '')
		{
			$url = $this->_cfg->secureGraphUrl . '/fql?access_token='
				. $this->_cfg->accessToken . '&format=json-strings&q='
				. urlencode($query);
			
			if($result = $this->url_get_contents($url))
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
// 				. urlencode($query);
			
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
	
	public function url_get_contents($url, $use_include_path=false, $context=null, $debug=false)
	{
		if(strpos($url, '?') === false)
		{
			$url .= '?format=json-strings';
		}
		else
		{
			$url .= '&format=json-strings';
		}
		
		$contents = '';
		
// 	    if(function_exists('url_get_contents'))
// 	    {
	    	if($this->_bool('allow_url_fopen'))
	    	{
	    		$contents = @file_get_contents($url, $use_include_path, $context);
	    	}
// 	    }
		
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
	    	$err  = curl_getinfo($c, CURLINFO_HTTP_CODE);
	    	 
	    	curl_close($c);
	    }
	    
	    if($contents != '')
	    {
	    	if($contents = json_decode($contents))
	    	{
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
	
	
	private function _tidyFacebookMessage($message)
	{
		$message = strip_tags($message);
		$message = preg_replace('/\s+/', ' ', $message);
		
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
			$bits = parse_url($_ENV['SCRIPT_URI']);
			$url = $bits['host'];
		}
		else
		{
			$url = $_SERVER['HTTP_HOST'];
		}
		
		return $url;
	}
	
}

set_exception_handler(array('Jumpage', 'loadCache'));


