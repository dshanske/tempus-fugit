<?php
/**
 * Week of year permalinks.
 *
 * @package TempusFugit
 * @since 1.0.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the `%week%` permalink tag and week archives, such as `/2024/W12/`.
 *
 * @since 1.0.5
 */
class Tempus_Week_Of_Year {
	/**
	 * Registers the hooks for week permalinks and archives.
	 *
	 * @since 1.0.5
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
		add_filter( 'available_permalink_structure_tags', array( __CLASS__, 'archive_permalink_structure_tags' ) );
		add_filter( 'pre_get_posts', array( __CLASS__, 'week_of_year' ) );
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
	 * @since 1.0.5
	 */
	public static function plugins_loaded() {
		self::rewrite_rules();
	}

	/**
	 * Adds the `week` public query var.
	 *
	 * Hooked to `query_vars`.
	 *
	 * @since 1.0.5
	 *
	 * @param string[] $var Public query vars.
	 * @return string[] Public query vars.
	 */
	public static function query_vars( $var ) {
		$var[] = 'week';
		return $var;
	}

	/**
	 * Registers the `%week%` rewrite tag and the `%year%/W%week%` permastruct.
	 *
	 * Also adds a kind archive permastruct when the Post Kinds plugin is active.
	 *
	 * @since 1.0.5
	 */
	public static function rewrite_rules() {
		add_rewrite_tag( '%week%', '([0-9]{2})', 'week=' );
		add_permastruct(
			'week',
			'%year%/W%week%',
			array(
				'with_front' => false,
				'ep_mask'    => EP_DATE,
			)
		);

		if ( class_exists( 'Kind_Taxonomy' ) ) {

			add_permastruct(
				'kind_week',
				'kind/%kind%/%year%/W%week%/',
				array(
					'walk_dirs' => false,
				)
			);
		}
	}

	/**
	 * Lists `%week%` among the tags on the Permalinks settings screen.
	 *
	 * Hooked to `available_permalink_structure_tags`.
	 *
	 * @since 1.0.5
	 *
	 * @param string[] $tags Tag descriptions, keyed by tag name.
	 * @return string[] Tag descriptions.
	 */
	public static function archive_permalink_structure_tags( $tags ) {
		/* translators: %s: Permalink structure tag. */
		$tags['week'] = __( '%s (2 Digit Week.)', 'tempus-fugit' );
		return $tags;
	}

	/**
	 * Replaces `%week%` in a permalink with the post's two-digit ISO-8601 week number.
	 *
	 * Hooked to `post_link` and `post_type_link`.
	 *
	 * @since 1.0.5
	 *
	 * @param string  $permalink The post's permalink.
	 * @param WP_Post $post      The post.
	 * @return string The permalink.
	 */
	public static function post_link( $permalink, $post ) {
		if ( false === strpos( $permalink, '%week%' ) ) {
			return $permalink;
		}
		$datetime = get_post_datetime( $post );
		return str_replace( '%week%', zeroise( $datetime->format( 'W' ), 2 ), $permalink );
	}

	/**
	 * Converts a year and week request into a date query.
	 *
	 * Hooked to `pre_get_posts`. Skips admin requests.
	 *
	 * @since 1.0.7
	 *
	 * @param WP_Query $query The query being prepared.
	 * @return WP_Query|void The query, or nothing for requests that are skipped.
	 */
	public static function week_of_year( $query ) {
		// Skip admin requests.
		if ( is_admin() ) {
			return;
		}

		// If this is a date archive for a year and week.
		if ( is_date() && ! empty( $query->get( 'week' ) ) && ! empty( $query->get( 'year' ) ) ) {
			$query->set(
				'date_query',
				array(
					'week' => $query->get( 'week' ),
					'year' => $query->get( 'year' ),
				)
			);
			$query->set( 'year', '' );
		}
		return $query;
	}

	/**
	 * Whether the current request is a week archive.
	 *
	 * @since 1.0.5
	 *
	 * @return bool True for a week archive.
	 */
	public static function is_week() {
		return ( is_date() && is_numeric( get_query_var( 'week' ) ) );
	}

	/**
	 * Sets the archive title for week archives, such as "Week 12, 2024".
	 *
	 * Hooked to `get_the_archive_title`.
	 *
	 * @since 1.0.5
	 *
	 * @param string $title Archive title.
	 * @return string Archive title.
	 */
	public static function archive_title( $title ) {
		if ( self::is_week() ) {
			$title  = get_the_date( _x( 'W, Y', 'weekly archives date format', 'default' ) );
			$prefix = _x( 'Week', 'date archive title prefix', 'default' );
			/** This filter is documented in wp-includes/general-template.php */
			$prefix = apply_filters( 'get_the_archive_title_prefix', $prefix );
			if ( $prefix ) {
				$title = sprintf(
				/* translators: 1: Title prefix. 2: Title. */
					_x( '%1$s %2$s', 'archive title', 'default' ),
					$prefix,
					'<span>' . $title . '</span>'
				);
			}
		}
		return $title;
	}

	/**
	 * Sets the document title for week archives to the week number.
	 *
	 * Hooked to `document_title_parts`.
	 *
	 * @since 1.0.5
	 *
	 * @param array $title Document title parts.
	 * @return array Document title parts.
	 */
	public static function title_parts( $title ) {
		if ( self::is_week() ) {
			$title['title'] = get_the_date( 'W' );
		}
		return $title;
	}
}
