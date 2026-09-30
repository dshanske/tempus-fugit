<?php
/**
 * This Week archives.
 *
 * @package TempusFugit
 * @since 1.0.3
 */

/**
 * Adds This Week archives.
 *
 * `/thisweek` lists posts from the current week number in previous years, and
 * `/thisweek/NN` lists posts from that week number in every year.
 *
 * @since 1.0.3
 */
class Tempus_This_Week {
	/**
	 * Registers the hooks for This Week archives.
	 *
	 * @since 1.0.3
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
		add_filter( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ) );
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
	 * @since 1.0.3
	 */
	public static function plugins_loaded() {
		self::rewrite_rules();
	}

	/**
	 * Adds the `thisweek` public query var.
	 *
	 * Hooked to `query_vars`.
	 *
	 * @since 1.0.3
	 *
	 * @param string[] $var Public query vars.
	 * @return string[] Public query vars.
	 */
	public static function query_vars( $var ) {
		$var[] = 'thisweek';
		return $var;
	}

	/**
	 * Returns the URL slug for This Week archives.
	 *
	 * @since 1.0.3
	 *
	 * @return string Archive slug.
	 */
	public static function get_slug() {
		/**
		 * Filters the URL slug for This Week archives.
		 *
		 * @since 1.0.3
		 *
		 * @param string $slug Archive slug. Default 'thisweek'.
		 */
		return apply_filters( 'tempus_fugit_thisweek_slug', 'thisweek' );
	}

	/**
	 * Returns the URL of the current This Week archive.
	 *
	 * @since 1.0.3
	 *
	 * @param int|null $blog_id Optional. Site ID. Default null (the current site).
	 * @return string Archive URL.
	 */
	public static function get_link( $blog_id = null ) {
		if ( is_multisite() && get_blog_option( $blog_id, 'permalink_structure' ) || get_option( 'permalink_structure' ) ) {
				global $wp_rewrite;
			if ( $wp_rewrite->using_index_permalinks() ) {
				$url = get_home_url( $blog_id, $wp_rewrite->index . '/thisweek' );

			} else {
				$url = get_home_url( $blog_id, 'thisweek' );
			}
		} else {
				$url = trailingslashit( get_home_url( $blog_id, '' ) );
				// nginx only allows HTTP/1.0 methods when redirecting from / to /index.php.
				// To work around this, we manually add index.php to the URL, avoiding the redirect.
			if ( 'index.php' !== substr( $url, 9 ) ) {
				$url .= 'index.php';
			}

			$url = add_query_arg( 'thisweek', 1, $url );
		}
		return $url;
	}

	/**
	 * Registers the This Week rules, with feeds and pagination.
	 *
	 * Also adds map views when the Simple Location plugin is active, and photo views when
	 * Post Kinds is active as well.
	 *
	 * @since 1.0.3
	 */
	public static function rewrite_rules() {
		$thisweek_slug = self::get_slug();

		// This Week for a specific week number.
		add_rewrite_rule(
			sprintf( '%1$s/([0-9]{2})/%2$s', $thisweek_slug, tempus_get_pagination_regex() ),
			'index.php?w=$matches[1]&paged=$matches[2]&thisweek=1',
			'top'
		);
		add_rewrite_rule(
			$thisweek_slug . '/([0-9]{2})/?$',
			'index.php?w=$matches[1]&thisweek=1',
			'top'
		);

		// The current week's archive.
		add_rewrite_rule(
			$thisweek_slug . '/feed/?$',
			'index.php?feed=' . get_default_feed() . '&thisweek=1',
			'top'
		);
		add_rewrite_rule(
			$thisweek_slug . '/' . tempus_get_feed_regex(),
			'index.php?feed=$matches[1]&thisweek=1',
			'top'
		);

		add_rewrite_rule(
			$thisweek_slug . '/' . tempus_get_pagination_regex(),
			'index.php?paged=$matches[1]&thisweek=1',
			'top'
		);

		add_rewrite_rule(
			$thisweek_slug . '/?$',
			'index.php?thisweek=1',
			'top'
		);

		// If the Simple Location plugin is installed, add the This Week rewrite options for the map view.
		if ( class_exists( 'Simple_Location_Plugin' ) ) {
			add_rewrite_rule(
				$thisweek_slug . '/([0-9]{2})/map/' . tempus_get_pagination_regex(),
				'index.php?w=$matches[1]&paged=$matches[2]&map=1',
				'top'
			);
			add_rewrite_rule(
				$thisweek_slug . '/([0-9]{2})/map/?$',
				'index.php?w=$matches[1]&map=1',
				'top'
			);
			// This Week map, paginated.
			add_rewrite_rule(
				$thisweek_slug . '/map/' . tempus_get_pagination_regex(),
				'index.php?thisweek=1&map=1',
				'top'
			);
			// This Week map.
			add_rewrite_rule(
				$thisweek_slug . '/map/?$',
				'index.php?thisweek=1&map=1',
				'top'
			);

			if ( class_exists( 'Post_Kinds_Plugin' ) ) {
				/** This filter is documented in the Post Kinds plugin. */
				$kind_photos_slug = apply_filters( 'kind_photos_slug', 'photos' );

				add_rewrite_rule(
					$thisweek_slug . '/' . $kind_photos_slug . '/' . tempus_get_pagination_regex(),
					'index.php?thisweek=1&kind_photos=1',
					'top'
				);

				// Photos from this week.
				add_rewrite_rule(
					$thisweek_slug . '/' . $kind_photos_slug . '/?$',
					'index.php?thisweek=1&kind_photos=1',
					'top'
				);
			}
		}
	}

	/**
	 * Turns a `/thisweek` request into a query for the current week number in previous years.
	 *
	 * Hooked to `pre_get_posts`. Only affects the main query on the front end.
	 *
	 * @since 1.0.3
	 *
	 * @param WP_Query $query The query being prepared.
	 * @return WP_Query|void The query, or nothing for requests that are skipped.
	 */
	public static function pre_get_posts( $query ) {
		// Only change the main query on the front end.
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$thisweek = get_query_var( 'thisweek' );
		// Only when no specific date was requested.
		if ( $thisweek && empty( get_query_var( 'year' ) ) && empty( get_query_var( 'monthnum' ) ) && empty( get_query_var( 'day' ) ) & empty( get_query_var( 'w' ) ) ) {
			$now                    = new DateTime( 'now', wp_timezone() );
			$query->is_date         = true;
			$query->is_day          = false;
			$query->is_home         = false;
			$query->is_archive      = true;
			$query->is_comment_feed = false;
			$query->set(
				'w',
				$now->format( 'W' )
			);
			$query->set(
				'date_query',
				array(
					array(
						'before' => 'first day of january this year',
					),
				)
			);
		}
		return $query;
	}

	/**
	 * Whether the current request is a week-number archive without a year or month.
	 *
	 * @since 1.0.3
	 *
	 * @return bool True for a This Week archive.
	 */
	public static function is_thisweek() {
		return ( is_date() && empty( get_query_var( 'year' ) ) && empty( get_query_var( 'monthnum' ) ) && ! empty( get_query_var( 'w' ) ) );
	}

	/**
	 * Sets the archive title for This Week archives, such as "Week: 12".
	 *
	 * Hooked to `get_the_archive_title`.
	 *
	 * @since 1.0.3
	 *
	 * @param string $title Archive title.
	 * @return string Archive title.
	 */
	public static function archive_title( $title ) {
		if ( self::is_thisweek() ) {
			$title  = get_the_date( _x( 'W', 'weekly archives date format', 'default' ) );
			$prefix = _x( 'Week:', 'date archive title prefix', 'default' );
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
	 * Sets the document title from the archive title, with HTML tags removed.
	 *
	 * Hooked to `document_title_parts`. Runs for every request, not only This Week archives.
	 *
	 * @since 1.0.3
	 *
	 * @param array $title Document title parts.
	 * @return array Document title parts.
	 */
	public static function title_parts( $title ) {
		$title['title'] = wp_strip_all_tags( self::archive_title( $title['title'] ) );
		return $title;
	}
}
