<?php
/*
 * Plugin Name: WPORG Repo Plugins
 * Plugin URI: http://trepmal.com/plugins/wporg-repo-plugins/
 * Description: Widget to display plugins on the wordpress.org repository by author
 * Version: 1.2
 * Author: Kailey Lampert
 * Author URI: http://kaileylampert.com/

Copyright (C) 2012-13 Kailey Lampert

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

add_action( 'plugins_loaded', 'wporg_repo_plugins_localize');
function wporg_repo_plugins_localize() {
	load_plugin_textdomain( 'wporg-repo-plugins', false, dirname( plugin_basename( __FILE__ ) ) .  '/lang' );
}

add_action( 'widgets_init', 'register_wporg_repo_plugins' );
function register_wporg_repo_plugins() {
	register_widget( 'WPORG_Repo_Plugins' );
}
class WPORG_Repo_Plugins extends WP_Widget {

	function __construct() {
		$widget_ops = array('classname' => 'wporg-repo-plugins', 'description' => __( 'Your WordPress.org Repository Plugins', 'wporg-repo-plugins' ) );
		$control_ops = array( 'width' => 400 );
		parent::WP_Widget( 'wporg_repo_plugins', __( 'WPORG Repo Plugins', 'wporg-repo-plugins' ), $widget_ops, $control_ops );
	}

	function widget( $args, $instance ) {
		extract( $args, EXTR_SKIP );
		extract( $instance, EXTR_SKIP );

		// do nothing if no username was provided
		if ( empty( $wporgusername ) ) return;

		echo $before_widget;

		echo $hide_title ? '' : $before_title . apply_filters( 'widget_title', $title ) . $after_title;

		//create a unique id for this widget and username
		$old_uid = $widget_id .'-'. $wporgusername;
		delete_transient( $old_uid );
		$uid = md5( $widget_id .'-'. serialize( $instance ) );
		//cache based on unique id
		if ( false === ( $plugins = get_transient( $uid ) ) ) {
			require_once( ABSPATH. 'wp-admin/includes/plugin-install.php' );
			$params = array( 'author' => $wporgusername );
			if ( ! isset( $per_page ) ) $per_page = 24; // might not be set if upgrading plugin without saving new options
			$params['per_page'] = $per_page;
			$api = plugins_api('query_plugins', $params );
			$plugins = json_decode( json_encode( $api->plugins ), true );
			set_transient( $uid, $plugins, 60*60 ); //keep for an hour
			// echo '<!-- just cached -->';
		}

		$keys = array_keys( $plugins[0] );
		// keys:
		// name, slug, version, author, author_profile, contributors,
		// requires, tested, compatibility, rating, num_ratings, homepage,
		// description, short_description

		//extra keys for custom/alternate functionality
		$keys[] = 'url';
		$keys[] = 'contributors_unlinked';
		$keys[] = 'author_unlinked';

		$format = stripslashes( $format );

		// store each plugin listing in an array, then we can use implode for easy separation
		$plugin_array = array();
		foreach( $plugins as $plg ) {
			$html = $format;
			foreach( $keys as $k ) {
				switch ( $k ) {
					case 'contributors' : //contains array. needs special handling
						$x = array();
						foreach( $plg[ $k ] as $name => $url ) {
							$x[] = "<a href='$url'>$name</a>";
						}
						$content = implode( ', ', $x );
						break;
					case 'contributors_unlinked' : //contains array, alt format. needs special handling
						$x = array_keys( $plg['contributors'] );
						$content = implode( ', ', $x );
						break;
					case 'compatibility' : //nested arrays. skipping for now
						break;
					case 'author_unlinked' : //alt format. needs special handling
						$content = strip_tags( $plg[ 'author' ] );
						break;
					case 'url' : //custom key. needs special handling
						$content = 'http://wordpress.org/extend/plugins/'.$plg['slug'];
						break;
					default : //doesn't need special handling!
						$content = $plg[ $k ];
				}
				$html = str_replace("[$k]", $content, $html );
			}
			$plugin_array[] = $html;
		}

		echo stripslashes( $before_format );
		echo implode( $separator, $plugin_array );
		echo stripslashes( $after_format );

		echo $after_widget;

	} //end widget()

	function update( $new_instance, $old_instance ) {

		$instance = $old_instance;
		$instance['title'] = esc_attr( $new_instance['title'] );
		$instance['hide_title'] = (bool) $new_instance['hide_title'] ? 1 : 0;
		$instance['wporgusername'] = esc_attr( $new_instance['wporgusername'] );
		$instance['per_page'] = intval( $new_instance['per_page'] );

		$instance['before_format'] = wp_filter_post_kses( $new_instance['before_format'] );
		$instance['format'] = wp_filter_post_kses( $new_instance['format'] );
		$instance['separator'] = wp_filter_post_kses( $new_instance['separator'] );
		$instance['after_format'] = wp_filter_post_kses( $new_instance['after_format'] );
		return $instance;

	} //end update()

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => 'My WordPress.org Plugins', 'hide_title' => 0, 'wporgusername' => '', 'per_page' => 24, 'before_format' => '<ul>', 'format' => '<li>[name]</li>', 'separator' => '', 'after_format' => '</ul>' ) );
		extract( $instance );
		?>
		<p style="width:63%;float:left;">
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'wporg-repo-plugins' );?>
				<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
			</label>
		</p>
		<p style="width:33%;float:right;padding-top:20px;height:20px;">
			<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('hide_title'); ?>" name="<?php echo $this->get_field_name('hide_title'); ?>"<?php checked( $hide_title ); ?> />
			<label for="<?php echo $this->get_field_id('hide_title'); ?>"><?php _e('Hide Title?', 'wporg-repo-plugins' );?></label>
		</p>
	<div style="width:48%;float:left;">
		<p>
			<label for="<?php echo $this->get_field_id( 'wporgusername' ); ?>"><?php _e( 'WP.org Username:', 'wporg-repo-plugins' );?>
				<input class="widefat" id="<?php echo $this->get_field_id('wporgusername'); ?>" name="<?php echo $this->get_field_name('wporgusername'); ?>" type="text" value="<?php echo $wporgusername; ?>" />
			</label>
			<small>&nbsp;</small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'before_format' ); ?>"><?php _e( 'Before Format:', 'wporg-repo-plugins' );?>
				<input class="widefat" id="<?php echo $this->get_field_id('before_format'); ?>" name="<?php echo $this->get_field_name('before_format'); ?>" type="text" value="<?php echo htmlspecialchars( stripslashes( $before_format ) ); ?>" />
			</label>
			<small><?php _e( 'Displayed before all the plugins.', 'wporg-repo-plugins' ); ?></small>
		</p>
	</div>
	<div style="width:48%;float:right;">
		<p>
			<label for="<?php echo $this->get_field_id( 'per_page' ); ?>"><?php _e( 'Number of plugins to retrieve:', 'wporg-repo-plugins' );?>
				<input class="widefat" id="<?php echo $this->get_field_id('per_page'); ?>" name="<?php echo $this->get_field_name('per_page'); ?>" type="number" value="<?php echo $per_page; ?>" />
			</label>
			<small>&nbsp;</small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'after_format' ); ?>"><?php _e( 'After Format:', 'wporg-repo-plugins' );?>
				<input class="widefat" id="<?php echo $this->get_field_id('after_format'); ?>" name="<?php echo $this->get_field_name('after_format'); ?>" type="text" value="<?php echo htmlspecialchars( stripslashes( $after_format ) ); ?>" />
			</label>
			<small><?php _e( 'Displayed after all the plugins.', 'wporg-repo-plugins' ); ?></small>
		</p>
	</div>
		<p style="clear:both;">
			<label for="<?php echo $this->get_field_id( 'format' ); ?>"><?php _e( 'Format:', 'wporg-repo-plugins' );?></label>
			<textarea class="widefat" id="<?php echo $this->get_field_id('format'); ?>" name="<?php echo $this->get_field_name('format'); ?>"><?php
				echo htmlspecialchars( stripslashes( $format ) );
			?></textarea>
			<small><?php _e( 'How to display each plugin.', 'wporg-repo-plugins' ); ?></small><br />
			<?php
				$keys = array( 'name', 'slug', 'version', 'author', 'author_profile', 'contributors', 'requires', 'tested', 'compatibility', 'rating', 'num_ratings', 'homepage', 'description', 'short_description' );
				$keys[] = 'url';
				$keys[] = 'contributors_unlinked';
				$keys[] = 'author_unlinked';
				unset( $keys[ array_search('compatibility', $keys ) ] ); //maybe later
				sort($keys);
				$keys = '['. implode('], [', $keys) .']';
			?>
			<small><?php printf( __( 'Accepted shortcodes: %s', 'wporg-repo-plugins'), $keys ); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'separator' ); ?>"><?php _e( 'Separator:', 'wporg-repo-plugins' );?>
				<input class="widefat" id="<?php echo $this->get_field_id('separator'); ?>" name="<?php echo $this->get_field_name('separator'); ?>" type="text" value="<?php echo htmlspecialchars( stripslashes( $separator ) ); ?>" />
			</label>
			<small><?php _e( 'Displayed between each plugin.', 'wporg-repo-plugins' ); ?></small>
		</p>
		<?php
	} //end form()
}