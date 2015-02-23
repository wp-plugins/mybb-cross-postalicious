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
 
class MyBBXP_Recent_Topics_Widget extends WP_Widget {


	public function __construct() {
		parent::__construct(
	 		'mybbxp_recent_topics_widget',
			'MyBBXP Recent Topics',
			array('description' => 'Displays recent topics from your MyBB forum', 'text_domain')
		);
	}

	// display
	public function widget($args, $instance) {		
		
		// re-use database connection if it's the same as wordpress
		$options = get_option('mybbxp_options');
		if ($options['db_user'] == $wpdb->dbuser &&
				$options['db_password'] == $wpdb->dbpassword &&
				$options['db_name'] == $wpdb->dbname &&
				$options['db_host'] == $wpdb->dbhost) {
			$mybbdb =& $wpdb;
		} else {
			$mybbdb = new wpdb($options['db_user'], $options['db_password'], $options['db_name'], $options['db_host']);
		}
	
		extract($args);
		$title = apply_filters('widget_title', $instance['title']);
		$options = get_option('mybbxp_options');
		
		echo $before_widget;
		if (false == empty($title)) {
			echo $before_title . $title . $after_title;
		}
		
		$sql = 'select tid, subject from '
				. $options['db_prefix'] . 'threads' // sanitized in MyBBXPSettings
				. ' where visible=1'
				. ' and fid in( '
				. $instance['fids'] // sanitized below
				. ') order by '
				. ($instance['define_recent'] == 'created' ? 'dateline' : 'lastpost') // sanitized below and inline
				. ' desc limit '
				. $instance['num_topics'] // sanitized below
				. ';';
		$threads = $mybbdb->get_results($sql);
		
		if ($mybbdb->error) {
			echo 'MyBBXP is having trouble with the MyBB database connection, perhaps re-check your <a href="' . admin_url('/options-general.php?page=mybbxp_settings_page') . '">settings</a>?';
			echo $after_widget;
			return;
		}
		if ($mybbdb->last_error) {
			echo $this->mybbdb->last_error . '<br/>Query: ' . $this->mybbdb->last_query;
			echo $after_widget;
			return;
		}
			
		echo '<ul>';
		foreach ($threads as $thread) {
			echo '<li><a href="' . $options['url'] . '/showthread.php?tid=' . $thread->tid . '">' . $thread->subject . '</a></li>';
		}
		echo '</ul>';
		
		echo $after_widget;
	}

	// update / sanitize widget config parameters
	public function update($new_instance, $old_instance) {
		$instance = array();
		$instance['title'] = strip_tags($new_instance['title']);
		preg_match_all('/\d+/', $new_instance['fids'], $matches);
		$instance['fids'] = implode(', ', $matches[0]);
		$instance['num_topics'] = preg_replace('/[^\d]/', '', $new_instance['num_topics']);
		$instance['define_recent'] = preg_match('/created/', $new_instance['define_recent']) === 1 ? 'created' : 'lastpost';
		return $instance;
	}

	// form display
	public function form($instance) {
	
		// set defaults
		$title = isset($instance['title']) ? $instance['title'] : 'Recent Forum Topics';
		$define_recent = isset($instance['define_recent']) ? $instance['define_recent'] : 'created';
		$num_topics = isset($instance['num_topics']) ? $instance['num_topics'] : '5';
		$fids = isset($instance['fids']) ? $instance['fids'] : '1, 2, 3';		
?>
		<p>
		<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
		</p>
		<p>
		<label for="<?php echo $this->get_field_id('define_recent'); ?>">Recent by means of:</label>
		<select id="<?php echo $this->get_field_id('define_recent'); ?>" name="<?php echo $this->get_field_name('define_recent'); ?>">
			<option value="created" <?php echo $define_recent == 'created' ? 'selected' : ''; ?>>thread creation</option>
			<option value="lastpost" <?php echo $define_recent == 'lastpost' ? 'selected' : ''; ?>>last post</option>
		</select>
		</p>
		<p>
		<label for="<?php echo $this->get_field_id('fids'); ?>">IDs of forums to include:</label> 
		<input class="widefat" id="<?php echo $this->get_field_id('fids'); ?>" name="<?php echo $this->get_field_name('fids'); ?>" type="text" value="<?php echo esc_attr($fids); ?>" />
		</p>
		<p>
		<label for="<?php echo $this->get_field_id('num_topics'); ?>">Number of topics to show:</label> 
		<input class="widefat" id="<?php echo $this->get_field_id('num_topics'); ?>" name="<?php echo $this->get_field_name('num_topics'); ?>" type="text" value="<?php echo esc_attr($num_topics); ?>" />
		</p>
<?php
	}
}

?>
