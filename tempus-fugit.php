<?php
/**
 * Plugin Name: Tempus Fugit
 * Plugin URI: https://github.com/dshanske/tempus-fugit
 * Description: Enhance your Time Based Experiences in WordPress
 * Author: David Shanske
 * Author URI: https://david.shanske.com
 * Text Domain: tempus-fugit
 * Version: 1.2.0
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
			require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-thisweek-widget.php';
			// Register Widgets
			add_action(
				'widgets_init',
				function () {
					register_widget( 'Tempus_OnThisDay_Widget' );
					register_widget( 'Tempus_ThisWeek_Widget' );
				}
			);
		}
	}

	public static function init() {
		require_once plugin_dir_path( __FILE__ ) . '/includes/rewrite-functions.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/functions.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/date-navigation.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-day-of-year.php';
		new Tempus_Day_Of_Year();
		Tempus_Day_Of_Year::rewrite_rules();

		require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-week-of-year.php';
		new Tempus_Week_Of_Year();
		Tempus_Week_Of_Year::rewrite_rules();

		require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-order-by.php';
		new Tempus_Order_By();
		Tempus_Order_By::rewrite_rules();

		require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-on-this-day.php';
		new Tempus_On_This_Day();
		Tempus_On_This_Day::rewrite_rules();

		require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-this-week.php';
		new Tempus_This_Week();
		Tempus_This_Week::rewrite_rules();
	}

	public static function upgrader_process_complete( $upgrade_object, $options ) {
		$current_plugin_path_name = plugin_basename( __FILE__ );
		if ( ( 'update' === $options['action'] ) && ( 'plugin' === $options['type'] ) ) {
			foreach ( $options['plugins'] as $each_plugin ) {
				if ( $each_plugin === $current_plugin_path_name ) {
					require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-day-of-year.php';
					new Tempus_Day_Of_Year();
					flush_rewrite_rules();

					require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-this-week.php';
					new Tempus_This_Week();
					Tempus_This_Week::rewrite_rules();
				}
			}
		}
	}

	public static function activate() {
		require_once plugin_dir_path( __FILE__ ) . '/includes/rewrite-functions.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/functions.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-day-of-year.php';
		new Tempus_Day_Of_Year();
		flush_rewrite_rules();

		require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-this-week.php';
		new Tempus_This_Week();
		Tempus_This_Week::rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public static function date_sort( $query ) {
		// check if the user is requesting an admin page
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// If this is a date archive but if there is no year indicating it is an On This Day, where you want to go backward.
		if ( is_date() && ! empty( $query->get( 'year' ) ) ) {
			$query->set( 'order', 'ASC' );
		}

		// If the default is a series then it should be in ascending order.
		if ( is_tax( 'series' ) ) {
			$query->set( 'order', 'ASC' );
		}

		return $query;
	}

	/**
	 * Generic Filter Set of HTML paramaters for KSES
	 *
	 * @return return array Array of HTML elements.
	 */
	public static function kses_clean() {
		return array(
			'a'          => array(
				'class' => array(),
				'href'  => array(),
				'name'  => array(),
			),
			'abbr'       => array(),
			'b'          => array(),
			'br'         => array(),
			'code'       => array(),
			'ins'        => array(),
			'del'        => array(),
			'em'         => array(),
			'i'          => array(),
			'q'          => array(),
			'strike'     => array(),
			'strong'     => array(),
			'time'       => array(
				'datetime' => array(),
			),
			'blockquote' => array(),
			'pre'        => array(),
			'p'          => array(
				'class' => array(),
				'id'    => array(),
			),
			'h1'         => array(
				'class' => array(),
			),
			'h2'         => array(
				'class' => array(),
			),
			'h3'         => array(
				'class' => array(),
			),
			'h4'         => array(
				'class' => array(),
			),
			'h5'         => array(
				'class' => array(),
			),
			'h6'         => array(
				'class' => array(),
			),
			'ul'         => array(
				'class'       => array(),
				'id'          => array(),
				'title'       => array(),
				'aria-label'  => array(),
				'aria-hidden' => array(),

			),
			'li'         => array(
				'class'       => array(),
				'id'          => array(),
				'title'       => array(),
				'aria-label'  => array(),
				'aria-hidden' => array(),
			),
			'ol'         => array(),
			'span'       => array(
				'class'       => array(),
				'id'          => array(),
				'title'       => array(),
				'aria-label'  => array(),
				'aria-hidden' => array(),
				'data-prefix' => array(),
				'data-icon'   => array(),
			),
			'section'    => array(
				'class' => array(),
				'id'    => array(),
			),
			'img'        => array(
				'src'    => array(),
				'class'  => array(),
				'id'     => array(),
				'alt'    => array(),
				'title'  => array(),
				'width'  => array(),
				'height' => array(),
				'srcset' => array(),
			),
			'figure'     => array(),
			'figcaption' => array(),
			'picture'    => array(
				'srcset' => array(),
				'type'   => array(),
			),
			'svg'        => array(
				'version'     => array(),
				'viewbox'     => array(),
				'id'          => array(),
				'x'           => array(),
				'y'           => array(),
				'xmlns'       => array(),
				'xmlns:xlink' => array(),
				'xml:space'   => array(),
				'style'       => array(),
				'aria-hidden' => array(),
				'focusable'   => array(),
				'class'       => array(),
				'role'        => array(),
				'height'      => array(),
				'width'       => array(),
				'fill'        => array(),

			),
			'div'        => array(
				'class' => array(),
				'id'    => array(),
			),
			'g'          => array(
				'id'           => array(),
				'stroke'       => array(),
				'stroke-width' => array(),
				'fill-rule'    => array(),
				'fill'         => array(),
			),
			'path'       => array(
				'd'    => array(),
				'fill' => array(),
			),
		);
	}
}
