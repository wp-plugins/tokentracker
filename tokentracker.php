<?php
/*
Plugin Name: TokenTracker
Plugin URI: http://tokentracker.com/wordpress/
Description: Automatically inserts a TokenTracker token into your posts and pages on publishing.
Version: 0.5
Author: Mark Ashcroft
Author URI: http://tokentracker.com
*/

/**
* Last Mod: 2 Nov 2009.
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
*/

//error_reporting(E_ALL);																		//debug

define("TT_WP_VER_GTE_25", version_compare($wp_version, '2.5', '>='));
define("TT_WP_VER_GTE_27", version_compare($wp_version, '2.7', '>='));
define("TT_WP_VER_GTE_28", version_compare($wp_version, '2.8', '>='));

add_action('admin_head', 'tt_addStyleSheet');
add_action('admin_menu', 'tokentracker_plugin_settings');									//for Sub Menus
add_action('transition_post_status', 'tt_publish_entry', 10, 3);							//On publishing entry
add_action('edit_form_advanced', 'tt_messages');											//for Posts
add_action('edit_page_form', 'tt_messages');												//for Pages


//Set the tt menu items.
function tokentracker_plugin_settings() {
	$tt_icon = get_bloginfo('wpurl').'/wp-content/plugins/tokentracker/files/transparent_icon.gif';
	if (!TT_WP_VER_GTE_28) {
		$tt_icon = get_bloginfo('wpurl').'/wp-content/plugins/tokentracker/files/tt_menu_static.png';
	}
    add_menu_page('tokentracker_plugin', 'TokenTracker', 1, 'tokentracker_plugin', 'tokentracker_plugin', $tt_icon);
	add_submenu_page('tokentracker_plugin', 'TokenTracker', 'Settings', 1, 'tokentracker_plugin', 'tokentracker_plugin');	
	add_submenu_page('tokentracker_plugin', 'TokenTracker', 'Tracked', 1, 'tokentracker_plugin_tracked', 'tt_tracked');	
	add_submenu_page('tokentracker_plugin', 'TokenTracker', 'User Guide', 1, 'tokentracker_plugin_userguide', 'tt_user_guide');	
}


function tt_tracked() { echo '<div style="padding:2em"><p><a href="http://tokentracker.com/app/" target="_blank">View my tracked tokens &raquo;</a></p></div>'; }

function tt_user_guide() { echo '<div style="padding:2em"><p><a href="http://tokentracker.com/wordpress/userguide.php" target="_blank">TokenTracker Plugin for WordPress User Guide &raquo;</a></p></div>'; }

function tt_addStyleSheet() { echo '<link rel="stylesheet" type="text/css" media="all" href="'.get_bloginfo('wpurl').'/wp-content/plugins/tokentracker/files/admin-style.css" />'."\n"; }


//Start the plugin display.
function tokentracker_plugin() {
	if (!TT_WP_VER_GTE_25) {
		echo '<div class="error" style="padding:5px;"><strong>Error</strong>: The TokenTracker plugin requires WordPress version 2.5 or newer, please <a href="http://wordpress.org">upgrade your WordPress version</a>.</div>';
		return;
	}
	
	global $user_ID;
	$tt_display_msg = '';
	
	if ( isset($_POST['action']) ) {
		if ( $_POST['action'] == 'tokentracker_plugin_save_settings' ) {
			delete_usermeta($user_ID, 'tokentracker_api_key');
			tt_update_usermeta('tokentracker_api_key', $_POST['tokentracker_api_key']);

			$tt_display_msg = '<div id="message" class="updated fade cs_message">
		<p><strong>Settings saved.</strong></p>
		</div>';
		}
	}
	
	//TT enter api key form
echo'<div class="wrap">
';
	if (TT_WP_VER_GTE_27) {	
		echo '	<div id="icon-options-general" class="icon32"><br /></div>
		';
	}
	
	global $user_ID;
	$tokentracker_api_key = get_usermeta($user_ID,'tokentracker_api_key');			
	
echo '
<h2>TokenTracker API Settings</h2>';

	if ( $tt_display_msg ) { echo $tt_display_msg; }


echo '<div class="clear" style="padding-top:5px"></div>

<form name="form" method="post" action="">
<input type="hidden" name="option_page" value="TokenTracker API Settings" />
<input type="hidden" name="action" value="tokentracker_plugin_save_settings" />
<input type="hidden" name="_wp_http_referer" value="/wp-admin/admin.php?page=tokentracker_plugin" />

<table class="form-table">
<tr valign="top">
<th scope="row">Enter your API key</th>
<td>
<input type="text" name="tokentracker_api_key" id="tokentracker_api_key" class="large-text code" style="width:237px" value="'.$tokentracker_api_key.'">
<span class="tt_api_key_secret">&nbsp;&nbsp;Treat your API key as a secret, like a password!</span>
</td>
</tr>
</table>
<p class="tt_signup"><a href="http://tokentracker.com/app/signup.php" target="_blank">If you don\'t have a TokenTracker account and API key you can get one here for free &raquo;</a></p>
<p class="tt_submit"><input type="submit" name="Submit" class="button-primary" value="Save Changes" /></p>
</form>
</div>
';
	
	return;
}


//On puublishing entry
function tt_publish_entry($new_status, $old_status, $post) {

	if ( $post->post_type === 'revision' ) { return; }

	if ( $old_status != 'publish' &&  $new_status == 'publish' ) {

		if ( strpos($post->post_content, "/token.gif?id=") ) { return; }	//already have a token
		if ( strpos($post->post_content, "/token.png?id=") ) { return; }	//already have a token
	
		$api_key = '';
		global $user_ID;
		$api_key = get_usermeta($user_ID,'tokentracker_api_key');
	
		if ( $api_key === '' ) {
			//must have an api_key entered in settings
			wp_redirect(tt_currentURL().'?action=edit&post='.$post->ID.'&tt_message='.urlencode('To have TokenTracker issue you a token you need to have your API key'));
			exit;
		}
	
		$title = substr($post->post_title,0,255);
		$description = tt_html2txt($post->post_content);
		if (strlen($description) > 247) {
			$description = substr($description,0,247) . ' [...]';
		}
		$guid = get_permalink($post->ID);
		
		$fields = '';
		//assigns any tags to the token
		$posttags = get_the_tags($post->ID);
		if ($posttags) {
			$c = 0;
			foreach($posttags as $tag) {
				$fields['tag'][$c] = $tag->name;
				$c++;
			}
		}

		//assigns the author's name a custom field to the token
		$user_info = get_userdata($post->post_author);
		$fields['author'][0] = $user_info->display_name;
		
		$token_img = false;
		$token_img = tt_getToken($api_key, $post->ID, $guid, $title, $description, $fields);
		
		if ( $token_img != false ) {	

			if ( strlen($post->post_content) > 111 ) {
				$pos = strpos($post->post_content, ">");
				$pos2 = strpos($post->post_content, "<");
				if ( $pos < 111 && $pos != "" && $pos > 0 && $pos != false ) {
					$post_content = substr($post->post_content, 0, $pos+1).$token_img.substr($post->post_content, $pos+1);
				} elseif ( $pos2 < 111 && $pos2 != "" && $pos2 != false ) {
					$post_content = substr($post->post_content, 0, $pos2).$token_img.substr($post->post_content, $pos2);
				} else {
					$pos3 = strrpos(substr($post->post_content, 0, 111), " ");
					if ( $pos3 < 111 && $pos3 != "" && $pos3 > 0 && $pos3 != false && $pos2 !== 0 ) {
						$post_content = substr($post->post_content, 0, $pos3).$token_img.substr($post->post_content, $pos3);
					} else {
						$post_content = $post->post_content.$token_img;
					}
				}
			} else {
				$post_content = $post->post_content.$token_img;
			}

			//insert and save this into post_content.
			global $wpdb;
			if ( function_exists('mysql_set_charset') ) {
				$wpdb->query("UPDATE $wpdb->posts SET post_content = '".mysql_real_escape_string($post_content)."' WHERE ID = '".$post->ID."'");
			} else {
				$wpdb->query("UPDATE $wpdb->posts SET post_content = '".addslashes($post_content)."' WHERE ID = '".$post->ID."'");
			}
			return;
		
		}
		
	}
	
	return;
}


function tt_getToken($api_key, $post_id, $guid, $title = '', $description = '', $fields = '') {
	
	if ( $api_key == '' || $guid == '' ) { return false; }

		require_once("TokenTracker.class.php");
		$tokentracker = new TokenTracker;
		
		$tokentracker->api_key = $api_key;
		$tokentracker->guid = $guid;
		$tokentracker->title = $title;
		$tokentracker->description = $description;
		//Assigns the admin users email and blog title of this blog as the publisher - as the token is being assigned to this blog, change as appropriate if for syndication.
		$tokentracker->publisher = get_bloginfo('name');
		$tokentracker->publisher_email = get_bloginfo('admin_email');
		
		if ( $fields != '' ) {
			$tokentracker->fields = $fields;
		}
	
		if ( $tokentracker->request_token() ) {
			
			// Successfully got token ID.
			return $tokentracker->token_img_code;
				
		} else {
			
			// Failed
			$err_message = $tokentracker->error_message;
			wp_redirect(tt_currentURL().'?action=edit&post='.$post_id.'&tt_message='.urlencode($err_message));
			exit;
			
		}
	
	return false;
}


function tt_html2txt($html) {
	$html = strip_tags($html);
	$search = array('@<script[^>]*?>.*?</script>@si',	// Strip out javascript
	               '@<style[^>]*?>.*?</style>@siU',		// Strip style tags properly
	               '@<[\/\!]*?[^<>]*?>@si',				// Strip out HTML tags
	               '@<![\s\S]*?--[ \t\n\r]*>@',			// Strip multi-line comments including CDATA
				   '@\[caption.*?\[/caption\]@si'		// Strip out wp code
	);
	$text = preg_replace($search, '', $html);
	unset($search);
	$search2 = array('@\n@s',							// Stip out new lines
				   '@\r@s',								// Stip out returns
				   '@\t@s',								// Stip out tabs  
				   '@  @s'								// Stip out double spaces
	);	
	$text2 = preg_replace($search2, ' ', $text);
	unset($search2);
	return trim($text2);
}


function tt_update_usermeta($meta_key, $meta_value) {

	if (!TT_WP_VER_GTE_25) { return false; }
	
	global $wpdb;
	global $user_ID;
	
	if ($user_ID == '') { return; }
	if ($user_ID == 0) { return; }
	
	if ( get_usermeta($user_ID,$meta_key) ) {
		$wpdb->query("
		UPDATE $wpdb->usermeta SET `meta_value` = '$meta_value'
		WHERE `user_id` = $user_ID AND `meta_key` = '$meta_key' ");
	} else {
		$wpdb->query( $wpdb->prepare( "
		INSERT INTO $wpdb->usermeta
		( user_id, meta_key, meta_value )
		VALUES ( %d, %s, %s )", 
		 $user_ID, $meta_key, $meta_value) );
	}
	
}


function tt_messages() {
	if ( isset($_GET['tt_message']) ) {
		if ( strpos(urldecode($_GET['tt_message']), 'have your API key') ) {
			echo '<div class="wrap" style="padding-top:1em"><div class="error" style="padding:5px;"><strong>TokenTracker</strong>: '.urldecode($_GET['tt_message']).' <a href="admin.php?page=tokentracker_plugin">entered in the settings &raquo;</a></div></div>';
			return;	
		}
		echo '<div class="wrap" style="padding-top:1em"><div class="error" style="padding:5px;"><strong>TokenTracker Error</strong>: '.urldecode($_GET['tt_message']).'</div></div>';
		return;
	}
	return;
}


function tt_currentURL() {
	//TO DO test and get address working with WPMU sub domain setup
	$pageURL = 'http';
	if ( isset($_SERVER["HTTPS"]) ) {
		if ($_SERVER["HTTPS"] == "on") { $pageURL .= "s"; }
	}
	$pageURL .= "://";
	if ($_SERVER["SERVER_PORT"] != "80") {
		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}

	return $pageURL;
}


?>