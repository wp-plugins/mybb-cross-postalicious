<?php
/*
 * This file is part of MyBB Cross-Postalicious.
 * Copyright 2012 Markus Echterhoff (http://www.markusechterhoff.com)
 *
 * MyBB Cross-Postalicious is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * MyBB Cross-Postalicious is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with MyBB Cross-Postalicious.  If not, see <http://www.gnu.org/licenses/>.
 */
 
require_once(dirname(__FILE__) . '/MyBBXPMessageHandler.php');

class MyBBXPCrossPoster {

	private $options;
	private $mybbdb;
	private $messageHandler;
	
	function __construct($messageHandler) {
		global $wpdb;
		
		$this->options = get_option('mybbxp_options');
		$this->messageHandler = $messageHandler;
		
		// re-use database connection if it's the same as wordpress
		if ($this->options['db_user'] == $wpdb->dbuser &&
				$this->options['db_password'] == $wpdb->dbpassword &&
				$this->options['db_name'] == $wpdb->dbname &&
				$this->options['db_host'] == $wpdb->dbhost) {
			$this->mybbdb =& $wpdb;
		} else {
			$this->mybbdb = new wpdb($this->options['db_user'], $this->options['db_password'], $this->options['db_name'], $this->options['db_host']);
		}
	}

	function cross_post($wpid) {

		// cross posting only works for pages and posts
		if ($_POST['post_type'] != 'post' && $_POST['post_type'] != 'page') {
			return;
		}
		
		// need edit permission to make cross posts, if called via publish hook we can assume that this is so, but just in case we change the hook one day...
		if ($_POST['post_type'] == 'page' && false == current_user_can('edit_page', $wpid)) {
			$this->messageHandler->error('You need edit page permissions to use this plugin', __FILE__, __LINE__);
			return;
		}
		if ($_POST['post_type'] == 'post' && false == current_user_can('edit_post', $wpid)) {
			$this->messageHandler->error('You need edit post permissions to use this plugin', __FILE__, __LINE__);
			return;
		}
		
		$post = get_post($wpid);
		$options =& $this->options;
		
		// make sure the user has entered something into our sexy cross-post box
		if ($_POST['mybbxp_content'] == null || $_POST['mybbxp_content'] == "") {
			$this->messageHandler->error('You need to enter your cross-post into the "MyBB Cross-Postalicious Post"-Box, please do so and update your post.', __FILE__, __LINE__);
			return;
		}
		if ($_POST['mybbxp_title'] == null || $_POST['mybbxp_title'] == "") {
			$this->messageHandler->error('You need to enter your cross-post title into the "MyBB Cross-Postalicious Post"-Box, please do so and update your post.', __FILE__, __LINE__);
			return;
		}
		
		// get xp title and content from POST
		$cross_post_title = $_POST['mybbxp_title'];
		$cross_post_content = $_POST['mybbxp_content'];
		
		// with magic quotes on, we need to unescape the POST variables
		if (get_magic_quotes_gpc()) {
 			$cross_post_title = stripslashes($cross_post_title);
 			$cross_post_content = stripslashes($cross_post_content);
 		}	
		
		// store cross-post title and content to post meta
		update_post_meta($wpid, 'mybbxp_title', $cross_post_title);
		update_post_meta($wpid, 'mybbxp_content', $cross_post_content);

		// parse postlink and title
		$cross_post_title = preg_replace('/!TITLE!/', $post->post_title, $cross_post_title);
		$cross_post_content = preg_replace('/!BEGINLINK!(.*)!ENDLINK!/', '[url=' . $post->guid . ']$1[/url]', $cross_post_content);
		$cross_post_content = preg_replace('/!TITLE!/', $post->post_title, $cross_post_content);
		
		// log into MyBB
		$cookies = $this->login();
		if ($cookies == null) {
			return;
		}
		
		// prepare post key
		$sql = "select uid, loginkey, salt, regdate"
			. " from " . $options['db_prefix'] . "users"
			. " where username=%s;";
		$mybb_user = $this->mybbdb->get_row($this->mybbdb->prepare($sql, $options['xp_username']));
		if ($this->there_are_db_errors()) {
			return;
		}
		if ($mybb_user == null) {
			$this->messageHandler->error("Unable to obtain MyBB user from the database, does it exist?", __FILE__, __LINE__);
			return;
		}
		$my_post_key = md5($mybb_user->loginkey . $mybb_user->salt . $mybb_user->regdate);

		$action = '';
		$mpid = get_post_meta($wpid, 'mybbxp_mpid', true);
		if (empty($mpid)) { // no cross post exists --> create new thread
		
			$action = 'new thread created';
			
			$url = $options['url'] . '/newthread.php?fid=' . $options['xp_fid'];
			$post_data = array(
				'my_post_key' => $my_post_key,
				'subject' => $cross_post_title,
				'icon' => -1,
				'message_new' => $cross_post_content,
				'message' => $cross_post_content,
				'numpolloptions' => 2,
				'submit' => 'Post Thread',
				'action' => 'do_newthread',
				'posthash' => md5(uniqid(mt_rand(), true)));
				
			$response = wp_remote_post($url, array('body' => $post_data, 'cookies' => $cookies));
			if (is_wp_error($response)) {
				$this->messageHandler->http_error($url, $post_data, $response, __FILE__, __LINE__);
				$this->messageHandler->error("Unable to save the cross-post", __FILE__, __LINE__);
				return;
			};
			
			if (strpos($response['body'], $options['xp_create_ok']) === false) {
				$this->messageHandler->iframe_error('Did not find the string for sucessful cross-post creation in html output below. Please fix either the error or update the string in the plugin settings.', $response['body']);
				return;
			}

			// get mybb post id of cross-post (it's the newest post by this user)
			$sql = "select pid"
				. " from " . $options['db_prefix'] . "posts"
				. " where uid=%d"
				. " order by dateline desc limit 1;";
			$mpid = $this->mybbdb->get_var($this->mybbdb->prepare($sql, $mybb_user->uid));
			if ($this->there_are_db_errors()) {
				return;
			}
			if ($mpid == null) {
				$this->messageHandler->error("Unable to get id of cross-post, has it been posted at all?", __FILE__, __LINE__);
				return;
			}
			
			// link cross-post and it's thread to wp post
			if (false === add_post_meta($wpid, 'mybbxp_mpid', $mpid, false)) {
				$this->messageHandler->error("Failed to add post meta, you should never see this message displayed, unless your wordpress is somehow broken.");
				return;
			}
		
		} else { // cross post already exists --> update if changed

			$sql = "select subject, message"
				. " from " . $options['db_prefix'] . "posts"
				. " where pid=%d;";
			$row = $this->mybbdb->get_row($this->mybbdb->prepare($sql, $mpid));
			if ($this->there_are_db_errors()) {
				return;
			}
			if (null == $row) {
				delete_post_meta($wpid, 'mybbxp_mpid');
				$this->messageHandler->error("Unable to fetch cross-post data, the linked cross-post id does not exist, perhaps the thread got deleted? I have unlinked this post for you, click Update to post a fresh cross-post.", __FILE__, __LINE__);
				return;
			}

			if ($row->subject == $cross_post_title && $row->message == $cross_post_content) {
				$action = 'nothing to do, cross-post unchanged';
			} else {
				$action = 'cross-post updated';
			
				$url = $options['url'] . '/editpost.php?pid=' . $mpid;
				$sql = "select posthash"
					. " from " . $options['db_prefix'] . "posts"
					. " where pid=%d;";
				$posthash = $this->mybbdb->get_var($this->mybbdb->prepare($sql, $mpid));
				if ($this->there_are_db_errors()) {
					return;
				}
				if ($posthash == null) {
					$this->messageHandler->error("Unable to update the cross-post because I can't seem to get the posthash from the database. This can happen when you delete the cross-post. Please go to the MyBBXP plugin settings and unlink the crosspost for post-id: " . $wpid . ", then update this post to recreate the cross-post", __FILE__, __LINE__);
					return;
				}
				$post_data = array(
					'my_post_key' => $my_post_key,
					'subject' => $cross_post_title,
					'icon' => -1,
					'message_new' => $cross_post_content,
					'message' => $cross_post_content,
					'numpolloptions' => 2,
					'submit' => 'Update Post',
					'action' => 'do_editpost',
					'posthash' => $posthash);
				
				$response = wp_remote_post($url, array('body' => $post_data, 'cookies' => $cookies));
				if( is_wp_error($response ) ) {
					$this->messageHandler->http_error($url, $post_data, $response, __FILE__, __LINE__);
					$this->messageHandler->error("Unable to update cross-post", __FILE__, __LINE__);
					return;
				};
			
				if (strpos($response['body'], $options['xp_update_ok']) === false) {
					$this->messageHandler->iframe_error('Did not find the string for sucessful cross-post update in html output below. Please fix either the error or update the string in the plugin settings.', $response['body']);
					return;
				}
			}
		}
			
		// get thread id for linking
		$sql = "select tid"
			. " from " . $options['db_prefix'] . "posts"
			. " where pid=%d;";
		$mtid = $this->mybbdb->get_var($this->mybbdb->prepare($sql, $mpid));
		if ($this->there_are_db_errors()) {
			return;
		}
		if ($mtid == null) {
			$this->messageHandler->error("Unable to get thread id of cross-post, the actual cross-posting was successful though.", __FILE__, __LINE__);
			return;
		}
			
		$this->messageHandler->notice('Another job well done (' . $action . '). <a href="' . $options['url'] . '/showthread.php?tid=' . $mtid . '">View Cross-Post</a><br/>');		
	}
	
	private function login() {
		$options =& $this->options;
		$url = $options['url'] . '/member.php';
		$post_data = array(
			'username' => $options['xp_username'],
			'password' => $options['xp_password'],
			'action' => 'do_login',
			'remember' => 'no',
			'submit' => 'Login',
			'url' => $options['url']);
			
		$response = wp_remote_post($url, array('body' => $post_data));
		if (is_wp_error($response)) {
			$this->messageHandler->http_error($url, $post_data, $response, __FILE__, __LINE__);
			$this->messageHandler->error("An error occured when trying to log into MyBB.", __FILE__, __LINE__);
			return null;
		};
		
		if (strpos($response['body'], $options['xp_login_ok']) === false) {
			$this->messageHandler->iframe_error('Cannot find the string for successful login in the html output below. Please fix either the error or update the string in the plugin settings.', $response['body']);
			return;
		}

		return $response['cookies'];
	}
	
	private function there_are_db_errors() {
		if ($this->mybbdb->error) {
			$this->messageHandler->error('There is trouble with the MyBB database connection, perhaps re-check your <a href="' . admin_url('/options-general.php?page=mybbxp_settings_page') . '">settings</a>?', __FILE__, __LINE__);
			return true;
		}
		if ($this->mybbdb->last_error) {
			$this->messageHandler->error($this->mybbdb->last_error . ', Query: ' . $this->mybbdb->last_query , __FILE__, __LINE__);
			return true;
		}
		return false;
	}
	
	private function get_stuff_between($s, $d){
	    $s = explode($d, $s, 3);
	    return isset($s[1]) ? $s[1] : '';
	}
}

?>
