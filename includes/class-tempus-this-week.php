<?php
/**
 * This Week Class
 *
 * Adds On This Day functionality
 */
class Tempus_This_Week {
	public function __construct() {
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
		add_filter( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ) );
		add_filter( 'get_the_archive_title', array( __CLASS__, 'archive_title' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
	}

	public static function plugins_loaded() {
		self::rewrite_rules();
	}

	public static function query_vars( $var ) {
		$var[] = 'thisweek';
		return $var;
	}

	public static function get_slug() {
		return apply_filters( 'tempus_fugit_thisweek_slug', 'thisweek' );
	}

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

	public static function rewrite_rules() {
		$thisweek_slug = self::get_slug();

		// On This Specific Week
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

		// This Week
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

		// If the Simple Location plugin is installed, add the On This Day rewrite options for the map view.
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
			// Week Map with Pagination
			add_rewrite_rule(
				$thisweek_slug . '/map/' . tempus_get_pagination_regex(),
				'index.php?thisweek=1&map=1',
				'top'
			);
			// Week Map
			add_rewrite_rule(
				$thisweek_slug . '/map/?$',
				'index.php?thisweek=1&map=1',
				'top'
			);

			if ( class_exists( 'Post_Kinds_Plugin' ) ) {
				$kind_photos_slug = apply_filters( 'kind_photos_slug', 'photos' );

				add_rewrite_rule(
					$thisweek_slug . '/' . $kind_photos_slug . '/' . tempus_get_pagination_regex(),
					'index.php?thisweek=1&kind_photos=1',
					'top'
				);

				// Photos on This Day.
				add_rewrite_rule(
					$thisweek_slug . '/' . $kind_photos_slug . '/?$',
					'index.php?thisweek=1&kind_photos=1',
					'top'
				);
			}
		}
	}

	public static function pre_get_posts( $query ) {
		// check if the user is requesting an admin page
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$thisweek = get_query_var( 'thisweek' );
		// Return if  not set
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

	public static function is_thisweek() {
		return ( is_date() && empty( get_query_var( 'year' ) ) && empty( get_query_var( 'monthnum' ) ) && ! empty( get_query_var( 'w' ) ) );
	}

	public static function archive_title( $title ) {
		if ( self::is_thisweek() ) {
			$title  = get_the_date( _x( 'W', 'weekly archives date format', 'default' ) );
			$prefix = _x( 'Week:', 'date archive title prefix', 'default' );
			/**
			 * Filters the archive title prefix.
			 *
			 * @since 5.5.0
			 *
			 * @param string $prefix Archive title prefix.
			 */
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

	public static function title_parts( $title ) {
		$title['title'] = wp_strip_all_tags( self::archive_title( $title['title'] ) );
		return $title;
	}
}
