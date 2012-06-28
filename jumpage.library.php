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
class Jumpage
{
	protected $_cfg;
	
	public $profile = array();
	public $images = array();
	public $posts = array();
	
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
		
		if(empty($config['accessToken']))
		{
			exit('PAGE ACCESS TOKEN required! Get yours on jumpage.net/app');
		}
		
		
		
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
	
	private function _initConfig($config)
	{
		$config['defaultGraphUrl'] = 'http://graph.facebook.com/';
		$config['secureGraphUrl'] = 'https://graph.facebook.com/';
		
		return $config;
	}
	
	private function _initProfile()
	{
		$this->profile = (array) json_decode(
			$this->url_get_contents($this->_cfg->defaultGraphUrl
				. $this->_cfg->fbUserName /*. '?access_token=' 
				. $this->_cfg->accessToken*/)
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
		}
		
	}
	
	private function _initImages()
	{
		$aid = $this->_cfg->fbWallId
			. '_' . $this->_cfg->fbAlbumId;
		
		if(!empty($aid))
		{
			$this->images = $this->getImages($aid);
		}
		
// 		$this->images = json_decode(
// 			$this->url_get_contents($this->_cfg->defaultGraphUrl 
// 				. $this->_cfg->fbAlbumId . '/photos' /* . '?access_token=' 
// 				. $this->_cfg->accessToken*/)
// 		);
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
							
							
							$pic = json_decode($this->url_get_contents(
								$this->_cfg->defaultGraphUrl 
									. '/' . $media->photo->fbid));
							
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
		
		$fql = "SELECT type, created_time, message, attachment, permalink FROM stream "
		. "WHERE (type=46 OR type=80 OR type=247) AND source_id='"
		. $this->_cfg->fbWallId . "' AND actor_id='"
		. $this->_cfg->fbWallId . "' AND is_hidden=0 AND created_time > "
		. $timeLimit . " ORDER BY created_time DESC LIMIT "
		. $this->_cfg->fbMaxPosts;

		$items = $this->getByFqlQuery($fql);
		
		foreach($items as $item)
		{
			$message = preg_replace('/(http.+)$/i', '[...]', $item->message);
			$image = $this->_getPostImage($item->attachment);
			
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
			
			if(!(($message == '' || $message == '[...]') && $image === false))
			{
				$message = $this->_tidyFacebookMessage($message);
				
				if($message != '')
				{
					$height += $this->_getTextHeight($message);
				}
				
				$this->posts[] = (object) array(
					'message' => $message,
					'href' => $href,
					'type' => $item->type,
					'image' => $image,
					'height' => $height
				);
			}
		}
	}
	
	

	public function getAlbums()
	{
		$albums = json_decode(
			$this->url_get_contents($this->_cfg->defaultGraphUrl
				. $this->_cfg->fbUserName . '/albums' /*. '?access_token=' 
				. $this->_cfg->accessToken*/)
		);
	}

	public function getEvents()
	{
		if($this->_cfg->accessToken != '')
		{
			$events = json_decode(
				$this->url_get_contents($this->_cfg->secureGraphUrl
					. $this->_cfg->fbUserName . '/events?access_token='
					. $this->_cfg->accessToken)
			);
		}
	}
	
// 	public function getPosts()
// 	{
	
// 	}
	
	public function getImages($aid='')
	{
		if($aid == '')
		{
			$aid = $this->_cfg->fbWallId 
				. '_' . $this->_cfg->fbAlbumId;
		}
		
// 		$fql = "SELECT src, width, height FROM photo_src WHERE width > 960 "
// 			. "AND photo_id IN(SELECT object_id FROM photo WHERE aid='" . $aid . "')";
		
		$fql = "SELECT pid, object_id, link, caption FROM photo WHERE aid='" 
			. $aid . "' ORDER BY created LIMIT 9";
		
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
			$notes = json_decode(
				$this->url_get_contents($this->_cfg->secureGraphUrl
					. $this->_cfg->fbUserName . '/notes?access_token='
					. $this->_cfg->accessToken)
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
	
	public function getImage($fbImageId)
	{
		$item = json_decode($this->url_get_contents(
			$this->_cfg->defaultGraphUrl
				. '/' . $fbImageId));
		
		foreach($item->images as $image)
		{
			if($image->width > 960)
			{
				return (object) array(
					'href' => $item->link,
					'src' => $image->source,
					'width' => $image->width,
					'height' => $image->height,
					'alt' => ''
				);
			}
		}
		
		return false;
	}
	
	public function getNote($fbNoteId)
	{
		if($this->_notes === false)
		{
			$this->_notes = $this->getNotes();
		}
		
		foreach($this->_notes->data as $note)
		{
			if($note->id == $fbNoteId)
			{
				$note->message = str_replace(array(
					'<div><p>','</p></div>'
				), '', $note->message);
				
				return $note;
				
				break;
			}
		}
		
		return false;
	}
	
	public function getQuestion($fbQuestionId)
	{
	
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
			$data = (array) @json_decode(
				$this->url_get_contents($this->_cfg->defaultGraphUrl
					. $this->_cfg->fbUserName . '?fields='
					. $fbFieldName . '&access_token='
					. $this->_cfg->accessToken)
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
				. $this->_cfg->accessToken . '&format=json-strings&q=' . urlencode($query);
			
			if($result = @$this->url_get_contents($url))
			{
				if($result = json_decode($result))
				{
					return $result->data;
				}
			}
			
		}
		
		if($this->_cfg->fqlProxyUrl != '')
		{
			$referer = $_SERVER['SERVER_NAME'];
			$options = array('http' => array(
				'header'=>array("Referer: $referer\r\n")
			));
			
			$context = stream_context_create($options);
			$url = $this->_cfg->fqlProxyUrl . '?q=' 
				. urlencode($query);
			
			if($result = @$this->url_get_contents($url, false, $context))
			{
				if($result = json_decode($result))
				{
					return $result->data;
				}
			}
		}
		
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
	
	public static function loadCache()
	{
		try
		{
			exit(file_get_contents(CACHE_FILE_NAME));
		}
		catch (Exception $e)
		{
			exit('An error occured');
		}
		
	}
	
	public function run($template)
	{
		require_once $template;
	}
	
	public function url_get_contents($url, $use_include_path=false, $context=null)
	{ 
	    if (!function_exists('curl_init'))
	    {
	    	return file_get_contents($url, $use_include_path, $context);
	    }
	    
		$ch = curl_init();
		
		$timeout = 5; // zero for no timeout
		
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		
		$content = curl_exec($ch);
		
		curl_close($ch);
	    
		return $content;
	}
	
	
	private function _tidyFacebookMessage($message)
	{
		$message = strip_tags($message);
		$message = preg_replace('/\s+/', ' ', $message);
		
		$message = str_replace(array(
			'&'
		), array(
			'&amp;'
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
	
}

set_exception_handler(array('Jumpage', 'loadCache'));


