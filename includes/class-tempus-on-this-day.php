<?php
/**
 * On This Day archives.
 *
 * @package TempusFugit
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds On This Day archives.
 *
 * `/onthisday` lists posts from today's date in previous years, and `/onthisday/MM/DD`
 * lists posts from that date in previous years.
 *
 * @since 1.0.0
 */
class Tempus_On_This_Day {
	/**
	 * Registers the hooks for On This Day archives.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
		add_filter( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ) );
		// Late, so taxonomies registered by other plugins on init are included.
		add_action( 'init', array( __CLASS__, 'taxonomy_rewrite_rules' ), 99 );
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
	 * Adds the `onthisday` public query var.
	 *
	 * Hooked to `query_vars`.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $var Public query vars.
	 * @return string[] Public query vars.
	 */
	public static function query_vars( $var ) {
		$var[] = 'onthisday';
		return $var;
	}

	/**
	 * Returns the URL slug for On This Day archives.
	 *
	 * @since 1.0.2
	 *
	 * @return string Archive slug.
	 */
	public static function get_slug() {
		/**
		 * Filters the URL slug for On This Day archives.
		 *
		 * @since 1.0.0
		 *
		 * @param string $slug Archive slug. Default 'onthisday'.
		 */
		return apply_filters( 'tempus_fugit_onthisday_slug', 'onthisday' );
	}

	/**
	 * Returns the URL of today's On This Day archive.
	 *
	 * @since 1.0.2
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

			$url = add_query_arg( 'onthisday', 1, $url );
		}
		return $url;
	}

	/**
	 * Registers the On This Day rules, with feeds and pagination.
	 *
	 * Also adds map views when the Simple Location plugin is active, and photo views when
	 * Post Kinds is active as well.
	 *
	 * @since 1.0.0
	 */
	public static function rewrite_rules() {
		$onthisday_slug = self::get_slug();

		// On This Day for a specific date.
		add_rewrite_rule(
			sprintf( '%1$s/([0-9]{2})/([0-9]{2})/%2$s', $onthisday_slug, tempus_get_pagination_regex() ),
			'index.php?onthisday=1&monthnum=$matches[1]&day=$matches[2]&paged=$matches[3]',
			'top'
		);
		add_rewrite_rule(
			$onthisday_slug . '/([0-9]{2})/([0-9]{2})/?$',
			'index.php?onthisday=1&monthnum=$matches[1]&day=$matches[2]',
			'top'
		);

		// Today's On This Day archive.
		add_rewrite_rule(
			$onthisday_slug . '/feed/?$',
			'index.php?feed=' . get_default_feed() . '&onthisday=1',
			'top'
		);
		add_rewrite_rule(
			$onthisday_slug . '/' . tempus_get_feed_regex(),
			'index.php?feed=$matches[1]&onthisday=1',
			'top'
		);

		add_rewrite_rule(
			sprintf( '%1$s/%2$s', $onthisday_slug, tempus_get_pagination_regex() ),
			'index.php?onthisday=1&paged=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			$onthisday_slug . '/?$',
			'index.php?onthisday=1',
			'top'
		);

		// If the Simple Location plugin is installed, add the On This Day rewrite options for the map view.
		if ( class_exists( 'Simple_Location_Plugin' ) ) {
			add_rewrite_rule(
				$onthisday_slug . '/([0-9]{2})/([0-9]{2})/map/' . tempus_get_pagination_regex(),
				'index.php?monthnum=$matches[1]&day=$matches[2]&paged=$matches[3]&map=1',
				'top'
			);
			add_rewrite_rule(
				$onthisday_slug . '/([0-9]{2})/([0-9]{2})/map/?$',
				'index.php?monthnum=$matches[1]&day=$matches[2]&map=1',
				'top'
			);
			// On This Day map, paginated.
			add_rewrite_rule(
				$onthisday_slug . '/map/' . tempus_get_pagination_regex(),
				'index.php?onthisday=1&paged=$matches[1]&map=1',
				'top'
			);

			if ( class_exists( 'Simple_Location_Plugin' ) ) {
				// On This Day map.
				add_rewrite_rule(
					$onthisday_slug . '/map/?$',
					'index.php?onthisday=1&map=1',
					'top'
				);
			}

			if ( class_exists( 'Post_Kinds_Plugin' ) ) {
				/** This filter is documented in the Post Kinds plugin. */
				$kind_photos_slug = apply_filters( 'kind_photos_slug', 'photos' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Post Kinds filter.
				add_rewrite_rule(
					$onthisday_slug . '/' . $kind_photos_slug . '/' . tempus_get_pagination_regex(),
					'index.php?onthisday=1&kind_photos=1',
					'top'
				);
				// Photos on This Day.
				add_rewrite_rule(
					$onthisday_slug . '/' . $kind_photos_slug . '/?$',
					'index.php?onthisday=1&kind_photos=1',
					'top'
				);
			}
		}
	}

	/**
	 * Returns the URL of today's On This Day archive for a category, tag, or other term.
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
		return add_query_arg( 'onthisday', 1, $link );
	}

	/**
	 * Registers On This Day rules for the term archives of every public taxonomy.
	 *
	 * Adds `/tag/foo/onthisday/`, `/category/news/onthisday/03/15/`, and the like, with feeds
	 * and pagination. Uses each taxonomy's own permalink base.
	 *
	 * Hooked to `init` at priority 99.
	 *
	 * @since 1.2.1
	 */
	public static function taxonomy_rewrite_rules() {
		$slug = self::get_slug();
		foreach ( tempus_get_taxonomy_archive_regexes() as $base => $query_var ) {
			$query = 'index.php?' . $query_var . '=$matches[1]&onthisday=1';
			add_rewrite_rule(
				sprintf( '%1$s/%2$s/([0-9]{2})/([0-9]{2})/%3$s', $base, $slug, tempus_get_pagination_regex() ),
				$query . '&monthnum=$matches[2]&day=$matches[3]&paged=$matches[4]',
				'top'
			);
			add_rewrite_rule( $base . '/' . $slug . '/([0-9]{2})/([0-9]{2})/?$', $query . '&monthnum=$matches[2]&day=$matches[3]', 'top' );
			add_rewrite_rule( $base . '/' . $slug . '/feed/?$', $query . '&feed=' . get_default_feed(), 'top' );
			add_rewrite_rule( $base . '/' . $slug . '/' . tempus_get_feed_regex(), $query . '&feed=$matches[2]', 'top' );
			add_rewrite_rule( $base . '/' . $slug . '/' . tempus_get_pagination_regex(), $query . '&paged=$matches[2]', 'top' );
			add_rewrite_rule( $base . '/' . $slug . '/?$', $query, 'top' );
		}
	}

	/**
	 * Limits On This Day requests to a month and day in previous years.
	 *
	 * `/onthisday` uses today's date. `/onthisday/MM/DD` uses that date; core adds the month and
	 * day conditions. Either way, only posts from before this year are listed.
	 *
	 * Hooked to `pre_get_posts`. Only affects the main query on the front end.
	 *
	 * @since 1.0.0
	 * @since 1.2.1 Also excludes the current year from `/onthisday/MM/DD`.
	 *
	 * @param WP_Query $query The query being prepared.
	 * @return WP_Query|void The query, or nothing for requests that are skipped.
	 */
	public static function pre_get_posts( $query ) {
		// Only change the main query on the front end.
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( ! $query->get( 'onthisday' ) || $query->get( 'year' ) ) {
			return $query;
		}
		$now    = new DateTimeImmutable( 'now', wp_timezone() );
		$clause = array(
			'before' => $now->setDate( (int) $now->format( 'Y' ), 1, 1 )->setTime( 0, 0 )->format( 'Y-m-d H:i:s' ),
		);
		// Without a specific date, use today's.
		if ( ! $query->get( 'monthnum' ) && ! $query->get( 'day' ) ) {
			$query->is_date         = true;
			$query->is_day          = true;
			$query->is_home         = false;
			$query->is_archive      = true;
			$query->is_comment_feed = false;
			$clause['month']        = (int) $now->format( 'n' );
			$clause['day']          = (int) $now->format( 'j' );
		}
		$query->set( 'date_query', array( $clause ) );
		return $query;
	}

	/**
	 * Whether the current request is an On This Day archive (a day archive without a year).
	 *
	 * @since 1.0.0
	 *
	 * @return bool True for an On This Day archive.
	 */
	public static function is_onthisday() {
		return ( is_day() && empty( get_query_var( 'year' ) ) );
	}

	/**
	 * Sets the archive title for On This Day archives, such as "On This Day: March 15".
	 *
	 * On a term archive the term is included, such as "On This Day in Travel: March 15".
	 *
	 * Hooked to `get_the_archive_title`.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title Archive title.
	 * @return string Archive title.
	 */
	public static function archive_title( $title ) {
		if ( self::is_onthisday() ) {
			$title = get_the_date( _x( 'F j', 'daily archives date format', 'tempus-fugit' ) );
			$term  = tempus_get_queried_term_name();
			if ( $term ) {
				/* translators: %s: Category, tag, or other term name. */
				$prefix = sprintf( _x( 'On This Day in %s:', 'date archive title prefix', 'tempus-fugit' ), $term );
			} else {
				$prefix = _x( 'On This Day:', 'date archive title prefix', 'tempus-fugit' );
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
	 * Sets the document title for On This Day archives to the date.
	 *
	 * On a term archive, the archive title is used instead, so the term is included.
	 *
	 * Hooked to `document_title_parts`.
	 *
	 * @since 1.0.0
	 *
	 * @param array $title Document title parts.
	 * @return array Document title parts.
	 */
	public static function title_parts( $title ) {
		if ( self::is_onthisday() ) {
			$title['title'] = tempus_get_queried_term_name() ? wp_strip_all_tags( self::archive_title( '' ) ) : get_the_date();
		}
		return $title;
	}
}
