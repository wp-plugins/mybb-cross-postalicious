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

function mybbxp_delete_stuff() {
	delete_option('mybbxp_options');
	delete_option('mybbxp_mybb_content_buffer');
	delete_option('mybbxp_admin_message');
	$posts = get_posts(array('numberposts' => -1, 'post_type' => 'post,page', 'post_status' => 'any'));
	foreach($posts as $post) {
		delete_post_meta($post->ID, 'mybbxp_content');
		delete_post_meta($post->ID, 'mybbxp_mpid');
		delete_post_meta($post->ID, 'mybbxp_active');
	}
}

if (false == defined('WP_UNINSTALL_PLUGIN')) {
	exit();
}

if (is_multisite()) {
    global $wpdb;
    $bids = $wpdb->get_col("select blog_id from " . $wpdb->blogs . ";");
    if ($bids) {
        foreach($bids as $bid) {
            switch_to_blog($bid);
            mybbxp_delete_stuff();
        }
        restore_current_blog();
    }
} else {
    mybbxp_delete_stuff();
}

?>
