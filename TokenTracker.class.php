<?php
/*****************************************************************

TokenTracker - API client
Version: 0.3
Last Mod: 2 Nov 2009.
  
* 
* Copyright (C) 2009 Mark W. B. Ashcroft
* 
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
* 
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
* 
* For more information please contact us: mail [AT] tokentracker [DOT] com
* 

The latest version can be obtained from: http://tokentracker.com

*****************************************************************/


class TokenTracker {
	
	/* public user definable vars */
	var $api_key						 	= 	"";			// your TokenTracker API key.
	var $guid 								= 	"";			// optional the URL (address) you wish to track.
	var $title								= 	"";			// optional (255 chracters max).
	var $publisher 							= 	"";			// optional (requires 'publisher_email' if using).
	var $publisher_email 					= 	"";			// optional (requires 'publisher' if using).
	var $description 						= 	"";			// optional (255 chracters max).
	var $fields 							= 	"";			// optional (array).
	
	var $error_message						=	"";			// the error messages returned.
	var $error_code							=	0;			// the error code.
	
	var $token_id							=	"";			// the returned token ID.
	var $token_url							=	"";			// the returned token URL.
	var $token_img_code						=	"";			// xhtml img code which can be used in content.
	
	/* private vars */
	var $_token_generator_api_endpoint 		= 	"http://tokentracker.com/get-token.php";
	
	function request_token() {
		
		// make sure is valid submit:
		if ( $this->api_key == '' ) { 
			$this->error_code = 001;
			$this->error_message = 'Invalid parameters sent.';
			$this->token_img_code = $this->_formatfooter('http://tokentracker.com/token.gif');
			return false;
		}

		if ( $this->title == '' && $this->guid == '' ) { 
			$this->error_code = 001;
			$this->error_message = 'Must have a title or a web address (guid).';
			$this->token_img_code = $this->_formatfooter('http://tokentracker.com/token.gif');
			return false;
		}
		
 		// make sure is valid URL submitted:
		if ( $this->guid != '' ) {
			if ( preg_match('#^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?#i', $this->guid) == false ) {
				$this->error_code = 005;
				$this->error_message = 'The URL sent is invalid, please enter a valid web address including http://';
				$this->token_img_code = $this->_formatfooter('http://tokentracker.com/token.gif');
				return false;
			}
		}
		
		require_once(ABSPATH.WPINC.'/class-snoopy.php');													// use WP's copy of snoopy
		$snoopy = new Snoopy;
		$snoopy->read_timeout = 20;																			// seconds
		
		$parameters = '?api_key='.$this->api_key;
		$parameters .= '&guid='.urlencode(substr($this->guid, 0, 255));
		$parameters .= '&title='.urlencode(substr($this->_striptextspaces($snoopy->_striptext($this->title)), 0, 255));
		$parameters .= '&description='.urlencode(substr($this->_striptextspaces($snoopy->_striptext($this->description)), 0, 255));
		if ( $this->publisher_email != '' ) {
			//must have both or none
			$parameters .= '&publisher='.urlencode(substr($this->publisher, 0, 100));
			$parameters .= '&publisher_email='.md5($this->publisher_email);
		}
		
		if ( $this->fields != '' ) {
			//foreach field in array
			foreach ($this->fields as $field_name => $field_values) {
				foreach ($field_values as $field_value) {
					$parameters .= '&'.$field_name.'[]='.urlencode(substr($this->_striptextspaces($snoopy->_striptext($field_value)), 0, 100));
				}
			}
		}	

		//echo "url: " . $this->_token_generator_api_endpoint.$parameters . "\n"; 

		if ($snoopy->fetch($this->_token_generator_api_endpoint.$parameters)) {
			$res = $snoopy->results;
			unset($snoopy);
			//var_dump($res); 																					// for debug
			$pos = strpos($res, 'tt_api_server_rsp status="ok"');
			if ($pos === false) { 																			// failed
				$pos2 = strpos($res, 'tt_api_server_rsp status="fail"');
				if ($pos2 === false) { 
					$this->error_code = 003;
					$this->error_message = 'The TokenTracker API is not responding at this time, please try again. 003';
					$this->token_img_code = $this->_formatfooter('http://tokentracker.com/token.gif');
					return false;
				} else {
					preg_match('#msg="(.*)"#s', $res, $err_msg);
					$this->error_code = 004;
					$this->error_message = $err_msg[1];
					$this->token_img_code = $this->_formatfooter('http://tokentracker.com/token.gif');
					return false;
				}
			}
			preg_match('#<token_id>(.*)<\/token_id>#s', $res, $token_id);
			preg_match('#<token_url>(.*)<\/token_url>#s', $res, $token_url);
			$this->status = 1;
			$this->token_id = $token_id[1];
			$this->token_url = $token_url[1];
			$this->token_img_code = $this->_formatfooter($token_url[1]);
			return true;																					// success
		} else {																							// failed
			unset($snoopy);
			//echo "Snoopy error: ".$snoopy->error."\n";																// for debug
			$this->error_code = 002;
			$this->error_message = 'The TokenTracker API is not responding at this time, please try again.';
			$this->token_img_code = $this->_formatfooter('http://tokentracker.com/token.gif');
			return false;
		}
		
		return false;
		
	}
	
	
	// xhtml formatted footer which can be used in entry (webpage/post/page/etc)
	function _formatfooter($token_url) {
		return '<img style="border: 0pt none; width: 0pt; height: 0pt; display: none;" src="'.$token_url.'" alt="" />';
	}

	
	// Strips returns and double spaces
	function _striptextspaces($txt) {
		$search = array('@\n@s',																			// Stip out new lines
					   '@\r@s',																				// Stip out returns
					   '@  @s',
					   '@  @s',
					   '@  @s',
					   '@<@s',
					   '@>@s',
					   '@\t@s'																				// Stip out tabs
		);	
		$text = preg_replace($search, ' ', $txt);
		unset($search);
		return trim($text);
	} //end func.

}
?>