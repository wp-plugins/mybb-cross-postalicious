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

class MyBBXPSettings {

	private $messageHandler;
	private $options;

	function __construct($messageHandler) {
		global $wpdb;
		
		//delete_option('mybbxp_options'); // to revert to default options for debugging
		
		$this->messageHandler = $messageHandler;
		$default_options = array(
			'url' => 'http://www.example.org/forum',
			'db_user' => $wpdb->dbuser,
			'db_password' => $wpdb->dbpassword,
			'db_name' => $wpdb->dbname,
			'db_host' => $wpdb->dbhost,
			'db_prefix' => 'mybb_',
			'xp_onoff' => 'both',
			'xp_username' => '',
			'xp_password' => '',
			'xp_fid' => '',
			'xp_login_ok' => 'You have successfully been logged in.',
			'xp_create_ok' => 'Thank you, your thread has been posted.',
			'xp_update_ok' => 'Thank you, this post has been edited.',
			'xp_discussion_link' => '<span style="display:block;font-size:1.5em;">There are !COUNT! comments in our discussion thread.</span>',
			'xp_discussion_link_one_comment' => '<span style="display:block;font-size:1.5em;">There is one comment in our discussion thread.</span>',
			'xp_discussion_link_zero_comments' => '<span style="display:block;font-size:1.5em;">Got comments? Post them here!</span>',
			'xp_default_crosspost_title' => '!TITLE!',
			'xp_default_crosspost_content' => 'This is the discussion thread for !BEGINLINK!!TITLE!!ENDLINK!. Post away!',
		);
		add_option('mybbxp_options', $default_options);
		
		$this->options = get_option('mybbxp_options');
	}
	
	function admin_menu_action() {
		$hook = add_options_page('MyBB Cross-Postalicios Settings', 'MyBBXP', 'manage_options', 'mybbxp_settings_page', array($this, 'settings_page')); // $page_title, $menu_title, $capability, $menu_slug, $function
		add_action('load-'.$hook,array($this->messageHandler, 'display_buffered_admin_message'));
		
		// apparently I have to call this as another callback
		add_action( 'admin_init', array($this, 'admin_init_action'));
	}
	
	function admin_init_action() {
		
		// general settings section
		add_settings_section('mybbxp_general', 'General', array($this, 'no_description'), 'mybbxp_settings_page'); // add_settings_section($id, $title, $callback, $page)
		 //add_settings_field($id, $title, $callback, $page, $section = 'default', $args = array())
		add_settings_field('url', 'MyBB Forum URL, no trailing slash', array($this, 'url_input'), 'mybbxp_settings_page', 'mybbxp_general');
		
		add_settings_section('mybbxp_db', 'MyBB Database Configuration', array($this, 'db_description'), 'mybbxp_settings_page');
		add_settings_field('db_user', 'MyBB Database Username', array($this, 'db_user_input'), 'mybbxp_settings_page', 'mybbxp_db');
		add_settings_field('db_password', 'MyBB Database Password', array($this, 'db_password_input'), 'mybbxp_settings_page', 'mybbxp_db');
		add_settings_field('db_name', 'MyBB Database Name', array($this, 'db_name_input'), 'mybbxp_settings_page', 'mybbxp_db');
		add_settings_field('db_host', 'MyBB Database Host', array($this, 'db_host_input'), 'mybbxp_settings_page', 'mybbxp_db');
		add_settings_field('db_prefix', 'MyBB Database Prefix', array($this, 'db_prefix_input'), 'mybbxp_settings_page', 'mybbxp_db');
		
		// cross-posting settings section
		add_settings_section('mybbxp_xp', 'Cross-Posting', array($this, 'no_description'), 'mybbxp_settings_page');
		add_settings_field('xp_onoff', 'Cross-Posting active by default for (can be overridden for individual posts/pages)', array($this, 'xp_onoff_input'), 'mybbxp_settings_page', 'mybbxp_xp');
		add_settings_field('xp_username', 'MyBB Cross-Posting Username', array($this, 'xp_username_input'), 'mybbxp_settings_page', 'mybbxp_xp');
		add_settings_field('xp_password', 'MyBB Cross-Posting Password', array($this, 'xp_password_input'), 'mybbxp_settings_page', 'mybbxp_xp');
		add_settings_field('xp_fid', 'MyBB Cross-Posting Forum ID (click on your forum, check the addressbar, find the "?fid=N" part at the end and type this number N into the field)', array($this, 'xp_fid_input'), 'mybbxp_settings_page', 'mybbxp_xp');
		add_settings_field('xp_login_ok', 'Something your forum outputs when you have logged in successfully', array($this, 'xp_login_ok_input'), 'mybbxp_settings_page', 'mybbxp_xp');
		add_settings_field('xp_create_ok', 'Something your forum outputs when you have successfully created a new thread', array($this, 'xp_create_ok_input'), 'mybbxp_settings_page', 'mybbxp_xp');
		add_settings_field('xp_update_ok', 'Something your forum outputs when you have successfully updated a post', array($this, 'xp_update_ok_input'), 'mybbxp_settings_page', 'mybbxp_xp');
		add_settings_field('xp_discussion_link', 'This will be a link to your cross-post displayed under your Wordpress post or page, use !COUNT! to insert a comment count', array($this, 'xp_discussion_link_input'), 'mybbxp_settings_page', 'mybbxp_xp');
		add_settings_field('xp_discussion_link_one_comment', 'This will be the cross-post link when there is only one comment', array($this, 'xp_discussion_link_one_comment_input'), 'mybbxp_settings_page', 'mybbxp_xp');
		add_settings_field('xp_discussion_link_zero_comments', 'This will be the cross-post link when there are no comments yet', array($this, 'xp_discussion_link_zero_comments_input'), 'mybbxp_settings_page', 'mybbxp_xp');
		add_settings_field('xp_default_crosspost_title', 'The default title of your cross-posts (you can override this setting for individual posts/pages). Insert the title of your post anywhere with "!TITLE!"', array($this, 'xp_default_crosspost_title_input'), 'mybbxp_settings_page', 'mybbxp_xp');
		add_settings_field('xp_default_crosspost_content', 'The default content of your cross-posts (you can override this setting for individual posts/pages). Create a link to this post/page by typing e.g. this: "!BEGINLINK!Click here to see the article!ENDLINK!" and insert the title of your post anywhere with "!TITLE!"', array($this, 'xp_default_crosspost_content_input'), 'mybbxp_settings_page', 'mybbxp_xp');
		
		// 'link post' settings section
		add_settings_section('mybbxp_link', 'Manual linking', array($this, 'link_description'), 'mybbxp_settings_page');
		add_settings_field('wpid', 'Wordpress post/page id', array($this, 'wpid_input'), 'mybbxp_settings_page', 'mybbxp_link');
		add_settings_field('mpid', 'MyBB post id (not the thread id)', array($this, 'mpid_input'), 'mybbxp_settings_page', 'mybbxp_link');
		
		// register our option so that they will be saved
		register_setting('mybbxp_options_group', 'mybbxp_options', array($this, 'sanitize_options'));
		register_setting('mybbxp_options_group', 'mybbxp_link', array($this, 'link'));
	}
	
	function db_description() {
		echo 'Enter your MyBB database details here, defaults to the same as your Wordpress ones, with mybb_ prefix';
	}
	
	function no_description() {
		// nothing to see here (pun intended)
	}
	
	function link_description() {
		echo 'Here you can manually link a wordpress post or page to a MyBB post. This should only necessary in edge cases, e.g. if you delete a Wordpress post and would like to link a new Wordpress post to the old forum post. Manual linking will disable comments, trackbacks and pingbacks for the Wordpress post/page. Be careful when looking for the MyBB post id to not accidentally use the thread id instead. The post id is called "pid" in the links, the thread id is "tid".';
	}
	
	function db_user_input($args) {
		echo '<input type="text" id="mybbxp_options[db_user]" name="mybbxp_options[db_user]" value="' . esc_attr($this->options['db_user']) . '" />'; 
	}
	
	function db_password_input($args) {
		echo '<input type="password" id="mybbxp_options[db_password]" name="mybbxp_options[db_password]" value="' . esc_attr($this->options['db_password']) . '" />'; 
	}
	
	function db_name_input($args) {
		echo '<input type="text" id="mybbxp_options[db_name]" name="mybbxp_options[db_name]" value="' . esc_attr($this->options['db_name']) . '" />'; 
	}
	
	function db_host_input($args) {
		echo '<input type="text" id="mybbxp_options[db_host]" name="mybbxp_options[db_host]" value="' . esc_attr($this->options['db_host']) . '" />'; 
	}
	
	function db_prefix_input($args) {
		echo '<input type="text" id="mybbxp_options[db_prefix]" name="mybbxp_options[db_prefix]" value="' . esc_attr($this->options['db_prefix']) . '" />'; 
	}
	
	function url_input($args) {
		echo '<input style="width:30%;" type="text" id="mybbxp_options[url]" name="mybbxp_options[url]" value="' . esc_attr($this->options['url']) . '" />'; 
	}
	
	function xp_onoff_input($args) {
		echo '<select id="mybbxp_options[xp_onoff]" name="mybbxp_options[xp_onoff]">';
		echo '	<option value="none" ' . ($this->options['xp_onoff'] == 'none' ? 'selected' : '') . '>never</option>';
		echo '	<option value="posts" ' . ($this->options['xp_onoff'] == 'posts' ? 'selected' : '') . '>posts only</option>';
		echo '	<option value="pages" ' . ($this->options['xp_onoff'] == 'pages' ? 'selected' : '') . '>pages only</option>';
		echo '	<option value="both" ' . ($this->options['xp_onoff'] == 'both' ? 'selected' : '') . '>both</option>';
		echo '</select>'; 
	}
	
	function xp_username_input($args) {
		echo '<input type="text" id="mybbxp_options[xp_username]" name="mybbxp_options[xp_username]" value="' . esc_attr($this->options['xp_username']) . '" />'; 
	}
	
	function xp_password_input($args) {
		echo '<input type="password" id="mybbxp_options[xp_password]" name="mybbxp_options[xp_password]" value="' . esc_attr($this->options['xp_password']) . '" />'; 
	}
	
	function xp_fid_input($args) {
		echo '<input type="text" id="mybbxp_options[xp_fid]" name="mybbxp_options[xp_fid]" value="' . $this->options['xp_fid'] . '" />'; 
	}
		
	function xp_login_ok_input($args) {
		echo '<textarea style="width:60%;" type="text" id="mybbxp_options[xp_login_ok]" name="mybbxp_options[xp_login_ok]">' . $this->options['xp_login_ok'] . '</textarea>'; 
	}
		
	function xp_create_ok_input($args) {
		echo '<textarea style="width:60%;" type="text" id="mybbxp_options[xp_create_ok]" name="mybbxp_options[xp_create_ok]">' . $this->options['xp_create_ok'] . '</textarea>'; 
	}
		
	function xp_update_ok_input($args) {
		echo '<textarea style="width:60%;" type="text" id="mybbxp_options[xp_update_ok]" name="mybbxp_options[xp_update_ok]">' . $this->options['xp_update_ok'] . '</textarea>'; 
	}
	
	function xp_discussion_link_input($args) {
		echo '<textarea style="width:60%;" type="text" id="mybbxp_options[xp_discussion_link]" name="mybbxp_options[xp_discussion_link]">' . $this->options['xp_discussion_link'] . '</textarea>'; 
	}
	
	function xp_discussion_link_one_comment_input($args) {
		echo '<textarea style="width:60%;" type="text" id="mybbxp_options[xp_discussion_link_one_comment]" name="mybbxp_options[xp_discussion_link_one_comment]">' . $this->options['xp_discussion_link_one_comment'] . '</textarea>'; 
	}
	
	function xp_discussion_link_zero_comments_input($args) {
		echo '<textarea style="width:60%;" type="text" id="mybbxp_options[xp_discussion_link_zero_comments]" name="mybbxp_options[xp_discussion_link_zero_comments]">' . $this->options['xp_discussion_link_zero_comments'] . '</textarea>'; 
	}
	
	function xp_default_crosspost_title_input($args) {
		echo '<textarea style="width:60%;" id="mybbxp_options[xp_default_crosspost_title]" name="mybbxp_options[xp_default_crosspost_title]">' . $this->options['xp_default_crosspost_title'] . '</textarea>'; 
	}
	
	function xp_default_crosspost_content_input($args) {
		echo '<textarea style="width:60%;" id="mybbxp_options[xp_default_crosspost_content]" name="mybbxp_options[xp_default_crosspost_content]">' . $this->options['xp_default_crosspost_content'] . '</textarea>'; 
	}
	
	function wpid_input($args) {
		echo '<input type="text" id="mybbxp_link[wpid]" name="mybbxp_link[wpid]" value="" />'; 
	}
	
	function mpid_input($args) {
		echo '<input type="text" id="mybbxp_link[mpid]" name="mybbxp_link[mpid]" value="" />'; 
	}
	
	function sanitize_options($insane_options) {
		// most of this is just 'trim', it's an admin configuring this after all
		$sane_options = array(
			'url' => trim($insane_options['url']),
			'db_user' => trim($insane_options['db_user']),
			'db_password' => $insane_options['db_password'],
			'db_name' => trim($insane_options['db_name']),
			'db_host' => trim($insane_options['db_host']),
			'db_prefix' => preg_replace('/[^a-z0-9_]/i', '', trim($insane_options['db_prefix'])),
			'xp_onoff' => trim($insane_options['xp_onoff']),
			'xp_username' => trim($insane_options['xp_username']),
			'xp_password' => $insane_options['xp_password'],
			'xp_fid' => trim($insane_options['xp_fid']),
			'xp_login_ok' => trim($insane_options['xp_login_ok']),
			'xp_create_ok' => trim($insane_options['xp_create_ok']),
			'xp_update_ok' => trim($insane_options['xp_update_ok']),
			'xp_discussion_link' => trim($insane_options['xp_discussion_link']),
			'xp_discussion_link_one_comment' => trim($insane_options['xp_discussion_link_one_comment']),
			'xp_discussion_link_zero_comments' => trim($insane_options['xp_discussion_link_zero_comments']),
			'xp_default_crosspost_title' => trim($insane_options['xp_default_crosspost_title']),
			'xp_default_crosspost_content' => trim($insane_options['xp_default_crosspost_content']),
		);
		
		return $sane_options;
	}
	
	function link($pids) {
		global $wpdb;
		
		$wpid = preg_replace('/[^\d]/', '', trim($pids['wpid']));
		$mpid = preg_replace('/[^\d]/', '', trim($pids['mpid']));
		if ($wpid != null && $mpid != null) {
			$sql = "update $wpdb->posts set comment_status='closed', ping_status='closed' where ID=%d";
			$wpdb->query($wpdb->prepare($sql, $wpid));
			if ($wpdb->last_error) {
				$this->messageHandler->error($wpdb->last_error . ', Query: ' . $wpdb->last_query , __FILE__, __LINE__);
				return;
			}
			update_post_meta($wpid, 'mybbxp_active', '1');
			update_post_meta($wpid, 'mybbxp_mpid', $mpid);
			$this->messageHandler->notice('Wordpress post ' . $wpid . ' has been linked to MyBB post ' . $mpid . '.<br/>');
		}
		return null;
	}
	
	function settings_page() {
?>
<div class="wrap">
	<h2>MyBB Cross-Postalicious Settings Page</h2>

	<form method="post" action="options.php">
		<?php settings_fields('mybbxp_options_group'); ?>
		<?php do_settings_sections('mybbxp_settings_page'); ?>
		<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>
	</form>
</div>
<?php
	}
}

?>
