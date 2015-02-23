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
 
require_once(dirname(__FILE__) . '/MyBBXPCrossPoster.php');
require_once(dirname(__FILE__) . '/MyBBXPMessageHandler.php');
require_once(dirname(__FILE__) . '/MyBBXPSettings.php');

class MyBBXP {

	private $messageHandler;
	private $options;
	private $mybbdb;
	
	function __construct() {
		$this->options = get_option('mybbxp_options');
		$this->messageHandler = new MyBBXPMessageHandler();
	}
	
	function activation_hook() {
		// noting to do
	}

	// callback to cross-post on publish post action
	function publish_post_or_page_action($wpid) {
		if (isset($_POST['mybbxp_active'])) {
			update_post_meta($wpid, 'mybbxp_active', '1');
			$crossPoster = new MyBBXPCrossPoster($this->messageHandler);
			$crossPoster->cross_post($wpid);
		} else {
			update_post_meta($wpid, 'mybbxp_active', '0');
		}
	}
	
	// callback to display buffered messages on admin head post action
	function admin_head_post_php_action() {
		$this->messageHandler->display_buffered_admin_message();
	}
	
	// callback to add big cross-post thread link to post
	function the_content_filter($content) {
		global $wpdb, $post;
		
		if (get_post_meta($post->ID, 'mybbxp_active', true) !== '1') {
			return $content;
		}	 
		
		// re-use database connection if it's the same as wordpress
		if ($this->options['db_user'] == $wpdb->dbuser &&
				$this->options['db_password'] == $wpdb->dbpassword &&
				$this->options['db_name'] == $wpdb->dbname &&
				$this->options['db_host'] == $wpdb->dbhost) {
			$mybbdb =& $wpdb;
		} else {
			$mybbdb = new wpdb($this->options['db_user'], $this->options['db_password'], $this->options['db_name'], $this->options['db_host']);
		}
		
		// get thread id and replies
		$sql = "select tid, replies"
			. " from " . $this->options['db_prefix'] . "threads"
			. " where tid=("
				. "select tid"
				. " from " . $this->options['db_prefix'] . "posts"
				. " where pid=%d" 
				. ");";
		$row = $mybbdb->get_row($mybbdb->prepare($sql, get_post_meta($post->ID, 'mybbxp_mpid', true)));
		if ($mybbdb->error) {
			return $content . '<div>MyBBXP is having trouble with the MyBB database connection, perhaps re-check your <a href="' . admin_url('/options-general.php?page=mybbxp_settings_page') . '">settings</a></div>?';
		}
		if ($mybbdb->last_error) {
			return $content . '<div>MyBBXP encountered an SQL error. Did you select the right database in your <a href="' . admin_url('/options-general.php?page=mybbxp_settings_page') . '">settings</a>? If so, then this is probably a bug.</div>';
		}
		if ($row == null) {
			// the thread got deleted, unlink the post
			delete_post_meta($post->ID, 'mybbxp_mpid');
			return $content;
		}

		$mtid = $row->tid;
		$count = $row->replies;

		$msg = null;
		if ($count == 0) {
			$msg = $this->options['xp_discussion_link_zero_comments'];
		} elseif ($count == 1) {
			$msg = $this->options['xp_discussion_link_one_comment'];
		} else {
			$msg = preg_replace('/!COUNT!/', $count, $this->options['xp_discussion_link']);
		}
		
		return $content . '<a href="' . $this->options['url'] . '/showthread.php?tid=' . $mtid . '">' . $msg . '</a>';
	}
	
	// callback to add our settings to the admin menu
	function admin_menu_action() {
		$settings = new MyBBXPSettings($this->messageHandler);
		$settings->admin_menu_action();
	}
	
	// callback to set comment and ping stati to closed when cross-posting
	function comment_and_ping_stati_pre($status) {
		if (false == isset($_POST['mybbxp_active'])) {
			return $status;
		}
		
		if ($status != 'closed') {
			$this->messageHandler->notice('I disabled comments, trackbacks and pingbacks for this post. Just letting you know, because you did not disable them manually.<br/>');
		}
		
		return 'closed';
	}
	
	// callback to flush our message buffers into options so that they can be displayed later
	function shutdown_action() {
		if ($this->messageHandler->has_messages()) {
			$this->messageHandler->flush_messages();
		}
	}
			
	// callback to add our delicious meta boxes when boxed are added
	function add_meta_boxes_action() {
		add_meta_box('mybbxp', 'MyBB Cross-Postalicious', array($this, 'cross_post_form'), 'post', 'normal', 'high');
		add_meta_box('mybbxp', 'MyBB Cross-Postalicious', array($this, 'cross_post_form'), 'page', 'normal', 'high');
	}
	
	// callback to display a delicious meta box
	function cross_post_form() {
		global $post;

		$title = $this->options['xp_default_crosspost_title'];
		$content = $this->options['xp_default_crosspost_content'];
		if ($post->ID != 0) {
			$custom_title = get_post_meta($post->ID, 'mybbxp_title', true);
			if ($custom_title != null) {
				$title = $custom_title;
			}
			$custom_content = get_post_meta($post->ID, 'mybbxp_content', true);
			if ($custom_content != null) {
				$content = $custom_content;
			}
		}

		$checked = '';
		$active = get_post_meta($post->ID, 'mybbxp_active', true);
		if ($active === '1' ||
				(
					$active === '' && // get_post_meta with third param true returns a string, i.e. '' instead of null
					(
						$this->options['xp_onoff'] == 'both' ||
						$this->options['xp_onoff'] == 'posts' && $post->post_type == 'post' ||
						$this->options['xp_onoff'] == 'pages' && $post->post_type == 'page'
					)
				)
		) {
			$checked = 'checked';
		}
		?>

		<script type="text/javascript">
		/* <![CDATA[ */  
		    jQuery(function($) {
		    	if ($('#mybbxp_active').is(':checked')) {
		    		$('#mybbxp_content_container').show();
		    	}
		    	$('#mybbxp_active').click(function() {
		    		$('#mybbxp_content_container').css('display', $(this).is(':checked') ? 'block' : 'none');
		    	});
		    });
		/* ]]> */
		</script>
		<p>
			<label for="mybbxp_active">Cross-post?</label>
			<input type="checkbox" id="mybbxp_active" name="mybbxp_active" <?php echo $checked; ?>/><br/><br/>
		</p>
		<div id="mybbxp_content_container" style="display:none;">
			<p>
				<label for="mybbxp_title">Title of cross-post:</label>
				<input type="text" id="mybbxp_title" name="mybbxp_title" style="width:100%;" value="<?php echo esc_attr($title != null ? $title : ''); ?>" />
			</p>
			<p>
				<label for="mybbxp_content">Content of cross-post:</label>
				<textarea id="mybbxp_content" name="mybbxp_content" cols="40" rows="7" style="width:100%;"><?php echo $content != null ? $content : '' ?></textarea>
			</p>
			<p>
				This is the content of your MyBB cross-post. You can use MyCode just as you would in your Forum.<br/>
				Create a link to this post/page by typing e.g. this: "<strong>!BEGINLINK!</strong>Click here to see the article<strong>!ENDLINK!</strong>" and insert the title of your post anywhere with "<strong>!TITLE!</strong>"
			</p>
		</div>

		<?php
	}
}

?>
