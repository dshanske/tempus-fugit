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
		add_filter( 'pre_post_link', array( __CLASS__, 'pre_post_link' ), 10, 2 );
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
	 * Adds the `tempus_week` public query var.
	 *
	 * Hooked to `query_vars`.
	 *
	 * @since 1.0.5
	 *
	 * @param string[] $var Public query vars.
	 * @return string[] Public query vars.
	 */
	public static function query_vars( $var ) {
		$var[] = 'tempus_week';
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
		add_rewrite_tag( '%week%', '([0-9]{2})', 'tempus_week=' );
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
	 * Fills in `%year%` and `%week%` with the post's ISO-8601 week-numbering year and week.
	 *
	 * Hooked to `pre_post_link`, which runs before WordPress replaces `%year%` with the
	 * calendar year. ISO weeks can cross New Year: December 30, 2024 is in week 1 of 2025,
	 * so in a structure with `%week%`, `%year%` must be the ISO year.
	 *
	 * @since 1.3.0
	 *
	 * @param string  $permalink The permalink structure.
	 * @param WP_Post $post      The post.
	 * @return string The permalink structure.
	 */
	public static function pre_post_link( $permalink, $post ) {
		return self::post_link( $permalink, $post );
	}

	/**
	 * Replaces `%week%` in a permalink with the post's two-digit ISO-8601 week number.
	 *
	 * If `%year%` is still in the permalink, it is replaced with the ISO-8601 year that the
	 * week belongs to.
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
		return str_replace( array( '%year%', '%week%' ), array( $datetime->format( 'o' ), $datetime->format( 'W' ) ), $permalink );
	}

	/**
	 * Converts a year and week request into a query for the dates in that ISO-8601 week.
	 *
	 * The week runs from Monday to Sunday. A week that doesn't exist in that year, such as
	 * week 53 in a year with 52 weeks, matches no posts.
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
		if ( is_date() && ! empty( $query->get( 'tempus_week' ) ) && ! empty( $query->get( 'year' ) ) ) {
			$year   = (int) $query->get( 'year' );
			$week   = (int) $query->get( 'tempus_week' );
			$monday = self::get_week_start( $year, $week );
			if ( (int) $monday->format( 'o' ) !== $year || (int) $monday->format( 'W' ) !== $week ) {
				// Not a week in this year.
				$query->set( 'post__in', array( 0 ) );
			} else {
				$query->set(
					'date_query',
					array(
						array(
							'after'     => $monday->format( 'Y-m-d H:i:s' ),
							'before'    => $monday->modify( '+6 days' )->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' ),
							'inclusive' => true,
						),
					)
				);
			}
			// Core would add its own YEAR() condition, so keep the year where navigation can find it.
			$query->set( 'tempus_year', (int) $query->get( 'year' ) );
			$query->set( 'year', '' );
		}
		return $query;
	}

	/**
	 * Returns midnight on the Monday that starts an ISO-8601 week, in the site's timezone.
	 *
	 * A week number outside the year rolls over into the neighboring year.
	 *
	 * @since 1.3.0
	 *
	 * @param int $year ISO-8601 week-numbering year.
	 * @param int $week ISO-8601 week number.
	 * @return DateTimeImmutable Start of the week.
	 */
	public static function get_week_start( $year, $week ) {
		$date = new DateTimeImmutable( 'now', wp_timezone() );
		return $date->setISODate( $year, $week, 1 )->setTime( 0, 0 );
	}

	/**
	 * Whether the current request is a week archive.
	 *
	 * @since 1.0.5
	 *
	 * @return bool True for a week archive.
	 */
	public static function is_week() {
		return ( is_date() && is_numeric( get_query_var( 'tempus_week' ) ) );
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
			$title  = get_the_date( _x( 'W, o', 'weekly archives date format', 'tempus-fugit' ) );
			$prefix = _x( 'Week', 'date archive title prefix', 'tempus-fugit' );
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
