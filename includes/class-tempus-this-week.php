<?php
/**
 * This Week archives.
 *
 * @package TempusFugit
 * @since 1.0.3
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds This Week archives.
 *
 * `/thisweek` lists posts from the current week number in previous years, and
 * `/thisweek/NN` lists posts from that week number in every year. Weeks are ISO-8601 weeks,
 * which start on Monday, whatever the site's "Week starts on" setting.
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
		// Late, so taxonomies registered by other plugins on init are included.
		add_action( 'init', array( __CLASS__, 'taxonomy_rewrite_rules' ), 99 );
		add_filter( 'posts_where', array( __CLASS__, 'posts_where' ), 10, 2 );
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
		$permalink_structure = is_multisite() ? get_blog_option( $blog_id, 'permalink_structure' ) : get_option( 'permalink_structure' );
		if ( $permalink_structure ) {
			global $wp_rewrite;
			if ( $wp_rewrite->using_index_permalinks() ) {
				$url = get_home_url( $blog_id, $wp_rewrite->index . '/' . self::get_slug() );
			} else {
				$url = get_home_url( $blog_id, self::get_slug() );
			}
		} else {
			$url = trailingslashit( get_home_url( $blog_id, '' ) );
			// nginx only allows HTTP/1.0 methods when redirecting from / to /index.php.
			// To work around this, we manually add index.php to the URL, avoiding the redirect.
			if ( 'index.php' !== substr( $url, -9 ) ) {
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
				$kind_photos_slug = apply_filters( 'kind_photos_slug', 'photos' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Post Kinds filter.

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
	 * Returns the URL of the current This Week archive for a category, tag, or other term.
	 *
	 * @since 1.2.1
	 *
	 * @param WP_Term|int|string $term     Term object, ID, or slug.
	 * @param string             $taxonomy Optional. Taxonomy name. Required when `$term` is a slug.
	 * @return string Archive URL, or an empty string if the term doesn't exist.
	 */
	public static function get_term_archive_link( $term, $taxonomy = '' ) {
		global $wp_rewrite;
		$term = ( is_string( $term ) && ! is_numeric( $term ) ) ? get_term_by( 'slug', $term, $taxonomy ) : get_term( $term, $taxonomy );
		if ( ! $term instanceof WP_Term ) {
			return '';
		}
		// The term exists, so this is a URL rather than an error.
		$link = get_term_link( $term );
		if ( $wp_rewrite->get_extra_permastruct( $term->taxonomy ) ) {
			return user_trailingslashit( trailingslashit( $link ) . self::get_slug() );
		}
		return add_query_arg( 'thisweek', 1, $link );
	}

	/**
	 * Registers This Week rules for the term archives of every public taxonomy.
	 *
	 * Adds `/tag/foo/thisweek/`, `/category/news/thisweek/11/`, and the like, with feeds and
	 * pagination. Uses each taxonomy's own permalink base.
	 *
	 * Hooked to `init` at priority 99.
	 *
	 * @since 1.2.1
	 */
	public static function taxonomy_rewrite_rules() {
		$slug = self::get_slug();
		foreach ( tempus_get_taxonomy_archive_regexes() as $base => $query_var ) {
			$query = 'index.php?' . $query_var . '=$matches[1]&thisweek=1';
			add_rewrite_rule(
				sprintf( '%1$s/%2$s/([0-9]{2})/%3$s', $base, $slug, tempus_get_pagination_regex() ),
				$query . '&w=$matches[2]&paged=$matches[3]',
				'top'
			);
			add_rewrite_rule( $base . '/' . $slug . '/([0-9]{2})/?$', $query . '&w=$matches[2]', 'top' );
			add_rewrite_rule( $base . '/' . $slug . '/feed/?$', $query . '&feed=' . get_default_feed(), 'top' );
			add_rewrite_rule( $base . '/' . $slug . '/' . tempus_get_feed_regex(), $query . '&feed=$matches[2]', 'top' );
			add_rewrite_rule( $base . '/' . $slug . '/' . tempus_get_pagination_regex(), $query . '&paged=$matches[2]', 'top' );
			add_rewrite_rule( $base . '/' . $slug . '/?$', $query, 'top' );
		}
	}

	/**
	 * Turns a This Week request into a query for an ISO-8601 week number.
	 *
	 * `/thisweek` uses the current week and only lists posts from before this week began.
	 * `/thisweek/NN` uses week NN in every year. The week is matched in `posts_where()`,
	 * because core's `w` query var numbers weeks by the site's "Week starts on" setting.
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
		// Only for This Week requests without a specific date.
		if ( ! $query->get( 'thisweek' ) || $query->get( 'year' ) || $query->get( 'monthnum' ) || $query->get( 'day' ) ) {
			return $query;
		}
		$query->is_date         = true;
		$query->is_day          = false;
		$query->is_home         = false;
		$query->is_archive      = true;
		$query->is_comment_feed = false;

		$week = (int) $query->get( 'w' );
		// Replace core's week number with the ISO-8601 week.
		$query->set( 'w', '' );
		if ( $week ) {
			$query->set( 'tempus_iso_week', $week );
			return $query;
		}

		$now = new DateTimeImmutable( 'now', wp_timezone() );
		$query->set( 'tempus_iso_week', (int) $now->format( 'W' ) );
		$query->set(
			'date_query',
			array(
				array(
					'before' => self::get_current_week_start()->format( 'Y-m-d H:i:s' ),
				),
			)
		);
		return $query;
	}

	/**
	 * Limits a query to posts published in an ISO-8601 week number, in any year.
	 *
	 * Applies to any query with the `tempus_iso_week` query var, which the This Week archive
	 * and widget set. MySQL's `WEEK()` mode 3 numbers weeks the same way as ISO-8601.
	 *
	 * Hooked to `posts_where`.
	 *
	 * @since 1.2.1
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string   $where The WHERE clause of the query.
	 * @param WP_Query $query The query.
	 * @return string The WHERE clause.
	 */
	public static function posts_where( $where, $query ) {
		global $wpdb;
		$week = (int) $query->get( 'tempus_iso_week' );
		if ( $week < 1 || $week > 53 ) {
			return $where;
		}
		return $where . $wpdb->prepare( " AND WEEK( {$wpdb->posts}.post_date, 3 ) = %d", $week );
	}

	/**
	 * Returns midnight on the Monday that started the current ISO-8601 week, in the site's timezone.
	 *
	 * @since 1.2.1
	 *
	 * @return DateTimeImmutable Start of the current week.
	 */
	public static function get_current_week_start() {
		$now = new DateTimeImmutable( 'now', wp_timezone() );
		return Tempus_Week_Of_Year::get_week_start( (int) $now->format( 'o' ), (int) $now->format( 'W' ) );
	}

	/**
	 * Whether the current request is a This Week archive.
	 *
	 * @since 1.0.3
	 *
	 * @return bool True for a This Week archive.
	 */
	public static function is_thisweek() {
		return ( is_date() && get_query_var( 'thisweek' ) && get_query_var( 'tempus_iso_week' ) && empty( get_query_var( 'year' ) ) && empty( get_query_var( 'monthnum' ) ) );
	}

	/**
	 * Sets the archive title for This Week archives, such as "Week: 12".
	 *
	 * On a term archive the term is included, such as "Week in Travel: 12".
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
			$title = get_the_date( _x( 'W', 'weekly archives date format', 'tempus-fugit' ) );
			$term  = tempus_get_queried_term_name();
			if ( $term ) {
				/* translators: %s: Category, tag, or other term name. */
				$prefix = sprintf( _x( 'Week in %s:', 'date archive title prefix', 'tempus-fugit' ), $term );
			} else {
				$prefix = _x( 'Week:', 'date archive title prefix', 'tempus-fugit' );
			}
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
