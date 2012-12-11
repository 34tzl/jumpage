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
	
	public $locale = 'en_US';
	
	public $version = 'jumpage Framework 0.9';
	
	private $_notes = false;
	
	public function __construct($template='', $cfgfile='jumpage.config.php')
	{
		if(!file_exists($cfgfile))
		{
			exit('CONFIG FILE NOT FOUND! ' . $cfgfile);
		}
		
		require_once $cfgfile;
		
		if(isset($config['template']))
		{
			if(file_exists($config['template']))
			{
				$template = $config['template'];
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
		
		$this->_cfg = (object) $this->_initConfig($config);
		$this->baseurl = $this->_url();
		
		$item = $this->url_get_contents($this->_cfg->secureGraphUrl
			. $this->_cfg->fbUserName . '/notes?limit=1&access_token='
			. $this->_cfg->fbAccessToken
		);
		
		if(isset($item->error))
		{
			if($item->error->type == 'OAuthException')
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
		
		$basePageFields = 'name, type, website, about, description, phone, categories, hours';
		
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
// 			. $this->_cfg->fbAccessToken*/);
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
					
					parse_str(parse_url($media->href, PHP_URL_QUERY), $query);
					$src = urldecode(@$query['v']);
					
					if($src != '') // youtube
					{
						$image = array();
						$w = $h = 200;
							
						$image['html'] = '<iframe class="ytplayer" width="800" height="600" src="http://www.youtube.com/embed/'
								. $src . '?autoplay=0&amp;controls=0&amp;autohide=2&amp;showinfo=0"></iframe>';
						$image['width'] = $w;;
						$image['height'] = $h;
						$image['alt'] = $media->alt;
						$image['href'] = $media->href;
						$image['type'] = 'video';
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
		. "WHERE post_id IN(SELECT post_id FROM stream WHERE type IN(46,80,247) AND NOT is_hidden "
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
	
	public function getEvents(
			$shortdesc=2, 
			$sortdesc=true, 
			$sincenow=true, 
			$limit=10, 
			$fields='id,name,description,start_time,location,picture.type(large)')
	{
		$events = array();
		
		if($this->_cfg->fbAccessToken != '')
		{
			$url = $this->_cfg->secureGraphUrl
				. $this->_cfg->fbUserName . '/events?fields=' 
				. $fields . '&access_token='
				. $this->_cfg->fbAccessToken;
			
			if($sincenow)
			{
				$url .= '&since=now';
			}
			
			if($limit > 0)
			{
				$url .= '&limit=' . intval($limit);
			}
			
			$items = $this->url_get_contents($url);
			
			foreach($items->data as $event)
			{
				$datetime = strtotime($event->start_time);
				$imgsrc = $event->picture->data->url;
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
					
					for($i=0; $i<$shortdesc; $i++)
					{
						$description .= $bits[$i];
					}
					
					$description = $this->_tidyFacebookMessage($description);
				}
				
				$events[$datetime] = (object) array(
					'id' => $event->id,
					'name' => $event->name,
					'description' => $description,
					'location' => $event->location,
					'datetime' => $datetime,
					'picture' => $picture,
					'link' => 'http://www.facebook.com/' . $event->id
				);
			}

			if($sortdesc)
			{
				array_multisort($events, SORT_DESC);
			}
			else
			{
				array_multisort($events, SORT_ASC);
			}
		}
		
		return $events;
	}
	
// 	public function getPosts()
// 	{
	
// 	}
	
	public function getImages($aid='', $order='created DESC', $limit=9, $width=940)
	{
		if($aid == '')
		{
			$aid = $this->_cfg->fbWallId 
				. '_' . $this->_cfg->fbAlbumId;
		}
		
// 		$fql = "SELECT src, width, height FROM photo_src WHERE width > 960 "
// 			. "AND photo_id IN(SELECT object_id FROM photo WHERE aid='" . $aid . "')";
		
		$albumtypes = array(
			'profile',
			'mobile',
			'wall'
		);
		
		if(in_array($aid, $albumtypes))
		{
			$fql = "SELECT aid FROM album WHERE type='" . $aid . "' AND owner='" 
				. $this->_cfg->fbWallId . "' LIMIT 1";
			
			$item = $this->getByFqlQuery($fql);
			$aid = $item[0]->aid;
		}
		
		$fql = "SELECT pid, object_id, link, caption FROM photo WHERE aid='" 
			. $aid . "'";
		
		if($aid == 'wall')
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
		
		$fql = 'SELECT photo_id, src, width, height FROM photo_src '
			. 'WHERE width > ' . $width . ' AND photo_id IN(' . $where . ')';
		
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
		
		if($this->_cfg->fbAccessToken != '')
		{
			$notes = $this->url_get_contents(
				$this->_cfg->secureGraphUrl
					. $this->_cfg->fbUserName . '/notes?access_token='
					. $this->_cfg->fbAccessToken
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
	
	public function getNote($fbNoteId, $stripTags=false, $lineBreaks=false)
	{
		if($this->_notes === false)
		{
			$this->_notes = $this->getNotes();
		}
		
		foreach($this->_notes->data as $note)
		{
			if($note->id == $fbNoteId)
			{
				$note->message = $this->_replaceSpecialCharacters($note->message);
				
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
				
				if($lineBreaks)
				{
					$note->message = nl2br($note->message);
				}
				
				$note->message = preg_replace('/\s+/', ' ', $note->message);
				
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
		
		return $prefix . htmlentities(trim($value), ENT_QUOTES, "UTF-8") . $suffix;
	}
	
	public function getByFqlQuery($query)
	{
		if($this->_cfg->fbAccessToken != '')
		{
			$url = $this->_cfg->secureGraphUrl . '/fql?access_token='
				. $this->_cfg->fbAccessToken . '&format=json-strings&q='
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
		
		$url .= '&locale=' .  $this->locale;
		
		$contents = '';
		
		try {
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
				"\xc3\xb1"	   => "&#241;",
				"\x96"		   => "&#241;",
				"\xe2\x81\x83" => '&bull;',
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
		$host = $_SERVER['HTTP_HOST'];
		
		if(isset($_ENV['SCRIPT_URI']))
		{
			$host = parse_url(
				$_ENV['SCRIPT_URI'], PHP_URL_HOST
			);
		}
		
		return $host;
	}
	
}

set_exception_handler(array('Jumpage', 'loadCache'));


