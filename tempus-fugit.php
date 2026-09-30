<?php
/**
 * Plugin Name: Tempus Fugit
 * Plugin URI: https://github.com/dshanske/tempus-fugit
 * Description: Enhance your Time Based Experiences in WordPress
 * Author: David Shanske
 * Author URI: https://david.shanske.com
 * Text Domain: tempus-fugit
 * License: GPLv2 or later
 * Version: 1.2.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 *
 * @package TempusFugit
 */

defined( 'ABSPATH' ) || exit;

// These run when the file loads, before test coverage starts. The tests check that the hooks are registered.
// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit requires the exact annotation text.
// @codeCoverageIgnoreStart
register_activation_hook( __FILE__, array( 'Tempus_Fugit_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Tempus_Fugit_Plugin', 'deactivate' ) );
add_action( 'upgrader_process_complete', array( 'Tempus_Fugit_Plugin', 'upgrader_process_complete' ), 10, 2 );

add_action( 'plugins_loaded', array( 'Tempus_Fugit_Plugin', 'plugins_loaded' ) );
add_action( 'init', array( 'Tempus_Fugit_Plugin', 'init' ) );
// @codeCoverageIgnoreEnd
// phpcs:enable Squiz.Commenting.InlineComment.InvalidEndChar

/**
 * Plugin bootstrap.
 *
 * Loads each feature, registers the widgets, and handles activation, deactivation, and upgrades.
 *
 * @since 1.0.0
 */
class Tempus_Fugit_Plugin {

	/**
	 * Registers the date sort filter and the widgets.
	 *
	 * Hooked to `plugins_loaded`. The widgets are skipped if the Post Kinds On This Day widget,
	 * which they were ported from, is already loaded.
	 *
	 * @since 1.0.0
	 */
	public static function plugins_loaded() {
		add_filter( 'pre_get_posts', array( __CLASS__, 'date_sort' ) );

		// These widgets were ported from Post Kinds, so skip them if its On This Day widget is loaded.
		if ( ! class_exists( 'Kind_OnThisDay_Widget' ) ) {
			require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-onthisday-widget.php';
			require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-thisweek-widget.php';
			require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-random-widget.php';
			// Register the widgets.
			add_action(
				'widgets_init',
				function () {
					register_widget( 'Tempus_OnThisDay_Widget' );
					register_widget( 'Tempus_ThisWeek_Widget' );
					register_widget( 'Tempus_Random_Widget' );
				}
			);
			Tempus_OnThisDay_Widget::register_cache_hooks();
		}
	}

	/**
	 * Loads the template functions and each feature class, and registers their rewrite rules
	 * and the shortcodes.
	 *
	 * Hooked to `init`.
	 *
	 * @since 1.0.0
	 */
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

		// The shortcodes render the widgets, which aren't loaded if Post Kinds provides its own.
		if ( class_exists( 'Tempus_OnThisDay_Widget' ) ) {
			require_once plugin_dir_path( __FILE__ ) . '/includes/class-tempus-shortcodes.php';
			Tempus_Shortcodes::register();
		}
	}

	/**
	 * Resets the rewrite rules after this plugin is updated.
	 *
	 * Hooked to `upgrader_process_complete`.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Upgrader $upgrade_object Upgrader instance.
	 * @param array       $options        {
	 *     Details of the update.
	 *
	 *     @type string   $action  Type of action, for example 'update'.
	 *     @type string   $type    Type of item updated, for example 'plugin'.
	 *     @type string[] $plugins Basenames of the plugins updated.
	 * }
	 */
	public static function upgrader_process_complete( $upgrade_object, $options ) {
		$current_plugin_path_name = plugin_basename( __FILE__ );
		if ( ( 'update' === $options['action'] ) && ( 'plugin' === $options['type'] ) ) {
			foreach ( $options['plugins'] as $each_plugin ) {
				if ( $each_plugin === $current_plugin_path_name ) {
					// The previous version's code is still loaded, so let the next request rebuild the rules.
					self::reset_rewrite_rules();
				}
			}
		}
	}

	/**
	 * Activation hook. Resets the rewrite rules so they are rebuilt with this plugin's rules.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		self::reset_rewrite_rules();
	}

	/**
	 * Deactivation hook. Resets the rewrite rules so they are rebuilt without this plugin's rules.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		self::reset_rewrite_rules();
	}

	/**
	 * Clear the stored rewrite rules so they are regenerated on the next request.
	 *
	 * Flushing during activation, deactivation, or upgrade happens before this plugin's rules
	 * are registered (or while they are still registered), so the stored rules would be wrong.
	 * When the option is empty, WordPress rebuilds it on the next request once every
	 * active plugin has registered its rules on init.
	 *
	 * @since 1.2.1
	 */
	public static function reset_rewrite_rules() {
		delete_option( 'rewrite_rules' );
	}

	/**
	 * Sorts date archives for a specific year, and series archives, from oldest to newest.
	 *
	 * Hooked to `pre_get_posts`. Only affects the main query on the front end. On This Day
	 * archives have no year, so they keep the default newest-first order.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Query $query The query being prepared.
	 * @return WP_Query|void The modified query, or nothing for admin and secondary queries.
	 */
	public static function date_sort( $query ) {
		// Only change the main query on the front end.
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Sort date archives for a specific year oldest first. On This Day archives have no year and keep newest first.
		if ( is_date() && ! empty( $query->get( 'year' ) ) ) {
			$query->set( 'order', 'ASC' );
		}

		// Series archives read in order, oldest first.
		if ( is_tax( 'series' ) ) {
			$query->set( 'order', 'ASC' );
		}

		return $query;
	}

	/**
	 * Returns the HTML elements and attributes allowed in widget output.
	 *
	 * Used with `wp_kses()`.
	 *
	 * @since 1.0.9
	 *
	 * @return array Allowed HTML, keyed by element name, with arrays of allowed attributes.
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
