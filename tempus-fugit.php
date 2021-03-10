<?php
/**
 * Plugin Name: Tempus Fugit
 * Plugin URI: https://github.com/dshanske/tempus-fugit
 * Description: Enhance your Time Based Experiences in WordPress
 * Author: David Shanske
 * Author URI: https://david.shanske.com
 * Text Domain: tempus-fugit
 * Version: 1.0.0
 */

register_activation_hook( __FILE__, array( 'Tempus_Fugit_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Tempus_Fugit_Plugin', 'deactivate' ) );
add_action( 'upgrader_process_complete', array( 'Tempus_Fugit_Plugin', 'upgrader_process_complete' ), 10, 2 );

add_action( 'plugins_loaded', array( 'Tempus_Fugit_Plugin', 'plugins_loaded' ) );
add_action( 'init', array( 'Tempus_Fugit_Plugin', 'init' ) );

class Tempus_Fugit_Plugin {

	public static function plugins_loaded() {
		add_filter( 'pre_get_posts', array( __CLASS__, 'date_sort' ) );

		// As this is being ported from the Kind On This Day Widget do Not Load if it is Loaded.
		if ( ! class_exists( 'Kind_OnThisDay_Widget' ) ) {
			require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-onthisday-widget.php';
			// Register Widgets
			add_action(
				'widgets_init',
				function() {
					register_widget( 'Tempus_OnThisDay_Widget' );
				}
			);
		}
	}

	public static function init() {
		require_once plugin_dir_path( __FILE__ ) . '/includes/rewrite-functions.php';

		require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-day-of-year.php';
		new Tempus_Day_Of_Year();
		Tempus_Day_Of_Year::rewrite_rules();

		require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-order-by.php';
		new Tempus_Order_By();
		Tempus_Order_By::rewrite_rules();

		require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-on-this-day.php';
		new Tempus_On_This_Day();
		Tempus_On_This_Day::rewrite_rules();
	}

	public static function upgrader_process_complete( $upgrade_object, $options ) {
		$current_plugin_path_name = plugin_basename( __FILE__ );
		if ( ( 'update' === $options['action'] ) && ( 'plugin' === $options['type'] ) ) {
			foreach ( $options['plugins'] as $each_plugin ) {
				if ( $each_plugin === $current_plugin_path_name ) {
					require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-day-of-year.php';
					new Tempus_Day_Of_Year();
					flush_rewrite_rules();
				}
			}
		}
	}

	public static function activate() {
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-day-of-year.php';
		new Tempus_Day_Of_Year();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}


	public static function date_sort( $query ) {
		// check if the user is requesting an admin page
		if ( is_admin() ) {
			return;
		}

		// If this is a date archive but if there is no year indicating it is an On This Day, where you want to go backward.
		if ( is_date() && ! empty( $query->get( 'year' ) ) ) {
			$query->set( 'order', 'ASC' );
		}
		return $query;
	}
}
