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
		
		$fql = 'SELECT pid, src FROM photo WHERE aid="' . $aid . '"';
		
		$this->images = $this->getByFqlQuery($fql);
		
		
// 		$this->images = json_decode(
// 			$this->url_get_contents($this->_cfg->defaultGraphUrl 
// 				. $this->_cfg->fbAlbumId . '/photos' /* . '?access_token=' 
// 				. $this->_cfg->accessToken*/)
// 		);
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
		
		$fql = "SELECT created_time, type, message, permalink FROM stream "
		. "WHERE message<>'' AND type<>56 AND source_id ='"
		. $this->_cfg->fbWallId . "' AND actor_id='"
		. $this->_cfg->fbWallId . "' AND is_hidden=0 AND created_time > "
		. $timeLimit . " ORDER BY created_time DESC LIMIT "
		. $this->_cfg->fbMaxPosts;

		$items = $this->getByFqlQuery($fql);
		
		foreach($items as $item)
		{
			$message = preg_replace('/(http.+)$/i', '[...]', $item->message);
			
			if($message != '[...]')
			{
				$this->posts[] = (object) array(
					'message' => $this->_tidyFacebookMessage($message),
					'href' => $item->permalink
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
		
		$fql = 'SELECT pid, src FROM photo WHERE aid="' . $aid . '"';
		
		return $this->getByFqlQuery($fql);
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
		
		if(in_array($fbFieldName, $this->profile))
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
				. $this->_cfg->accessToken . '&q=' . urlencode($query);
			
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
	
}

set_exception_handler(array('Jumpage', 'loadCache'));


