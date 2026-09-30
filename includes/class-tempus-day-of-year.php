<?php
/**
 * Day of year permalinks.
 *
 * @package TempusFugit
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the `%dayofyear%` permalink tag and day-of-year date archives, such as `/2024/075/`.
 *
 * The day of the year is ordinal: January 1 is 001.
 *
 * @since 1.0.0
 */
class Tempus_Day_Of_Year {
	/**
	 * Registers the hooks for day-of-year permalinks and archives.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
		add_filter( 'pre_get_posts', array( __CLASS__, 'day_of_year' ) );
		add_filter( 'available_permalink_structure_tags', array( __CLASS__, 'archive_permalink_structure_tags' ) );
		add_filter( 'post_link', array( __CLASS__, 'post_link' ), 10, 2 );
		add_filter( 'post_type_link', array( __CLASS__, 'post_link' ), 10, 2 );
		add_filter( 'get_the_archive_title', array( __CLASS__, 'archive_title' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
	}

	/**
	 * Registers the rewrite rules.
	 *
	 * Hooked to `plugins_loaded`. Instances are created on `init`, after that action
	 * has fired, so this currently never runs; `Tempus_Fugit_Plugin::init()` calls
	 * `rewrite_rules()` directly.
	 *
	 * @since 1.0.0
	 */
	public static function plugins_loaded() {
		self::rewrite_rules();
	}

	/**
	 * Adds the `dayofyear` public query var.
	 *
	 * Hooked to `query_vars`.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $var Public query vars.
	 * @return string[] Public query vars.
	 */
	public static function query_vars( $var ) {
		$var[] = 'dayofyear';
		return $var;
	}

	/**
	 * Registers the `%dayofyear%` rewrite tag and the `%year%/%dayofyear%` permastruct.
	 *
	 * Also adds a kind archive permastruct when the Post Kinds plugin is active.
	 *
	 * @since 1.0.0
	 */
	public static function rewrite_rules() {
		add_rewrite_tag( '%dayofyear%', '([0-9]{3})', 'dayofyear=' );
		add_permastruct(
			'dayofyear',
			'%year%/%dayofyear%',
			array(
				'with_front' => false,
				'ep_mask'    => EP_DATE,
			)
		);

		if ( class_exists( 'Kind_Taxonomy' ) ) {

			add_permastruct(
				'kind_dayofyear',
				'kind/%kind%/%year%/%dayofyear%/',
				array(
					'walk_dirs' => false,
				)
			);
		}
	}

	/**
	 * Converts a year and day-of-year request into a date query.
	 *
	 * Hooked to `pre_get_posts`. Only affects the main query on the front end.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Query $query The query being prepared.
	 * @return WP_Query|void The query, or nothing for requests that are skipped.
	 */
	public static function day_of_year( $query ) {
		// Only change the main query on the front end.
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// If this is a date archive for a year and day of the year.
		if ( is_date() && ! empty( $query->get( 'dayofyear' ) ) && ! empty( $query->get( 'year' ) ) ) {
			$query->set(
				'date_query',
				array(
					'dayofyear' => (int) $query->get( 'dayofyear' ),
					'year'      => $query->get( 'year' ),
				)
			);
			$query->set( 'year', '' );
		}
		return $query;
	}

	/**
	 * Lists `%dayofyear%` among the tags on the Permalinks settings screen.
	 *
	 * Hooked to `available_permalink_structure_tags`.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $tags Tag descriptions, keyed by tag name.
	 * @return string[] Tag descriptions.
	 */
	public static function archive_permalink_structure_tags( $tags ) {
		/* translators: %s: Permalink structure tag. */
		$tags['dayofyear'] = __( '%s (Day of the year, for example 366.)', 'tempus-fugit' );
		return $tags;
	}

	/**
	 * Replaces `%dayofyear%` in a permalink with the post's ordinal day of the year (001-366).
	 *
	 * Hooked to `post_link` and `post_type_link`.
	 *
	 * @since 1.0.0
	 *
	 * @param string  $permalink The post's permalink.
	 * @param WP_Post $post      The post.
	 * @return string The permalink.
	 */
	public static function post_link( $permalink, $post ) {
		if ( false === strpos( $permalink, '%dayofyear%' ) ) {
			return $permalink;
		}
		$datetime = get_post_datetime( $post );
		// PHP's 'z' is zero-based; the ordinal day of the year (and MySQL DAYOFYEAR) starts at 1.
		return str_replace( '%dayofyear%', zeroise( (int) $datetime->format( 'z' ) + 1, 3 ), $permalink );
	}

	/**
	 * Whether the current request is a day-of-year archive.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True for a day-of-year archive.
	 */
	public static function is_dayofyear() {
		return ( is_date() && is_numeric( get_query_var( 'dayofyear' ) ) );
	}

	/**
	 * Sets the archive title for day-of-year archives, such as "Day: March 15, 2024".
	 *
	 * Hooked to `get_the_archive_title`.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title Archive title.
	 * @return string Archive title.
	 */
	public static function archive_title( $title ) {
		if ( self::is_dayofyear() ) {
			$title  = get_the_date( _x( 'F j, Y', 'daily archives date format', 'tempus-fugit' ) );
			$prefix = _x( 'Day:', 'date archive title prefix', 'tempus-fugit' );
			/** This filter is documented in wp-includes/general-template.php */
			$prefix = apply_filters( 'get_the_archive_title_prefix', $prefix ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
			if ( $prefix ) {
				$title = sprintf(
				/* translators: 1: Title prefix. 2: Title. */
					_x( '%1$s %2$s', 'archive title', 'tempus-fugit' ),
					$prefix,
					'<span>' . $title . '</span>'
				);
			}
		}
		return $title;
	}

	/**
	 * Sets the document title for day-of-year archives to the archive date.
	 *
	 * Hooked to `document_title_parts`.
	 *
	 * @since 1.0.0
	 *
	 * @param array $title Document title parts.
	 * @return array Document title parts.
	 */
	public static function title_parts( $title ) {
		if ( self::is_dayofyear() ) {
			$title['title'] = get_the_date();
		}
		return $title;
	}
}
