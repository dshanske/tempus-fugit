<?php
/**
 * On This Day Class
 *
 * Adds On This Day functionality
 */
class Tempus_On_This_Day {
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
		$var[] = 'onthisday';
		return $var;
	}

	public static function get_slug() {
		return apply_filters( 'tempus_fugit_onthisday_slug', 'onthisday' );
	}

	public static function get_link( $blog_id = null ) {
		if ( is_multisite() && get_blog_option( $blog_id, 'permalink_structure' ) || get_option( 'permalink_structure' ) ) {
				global $wp_rewrite;
			if ( $wp_rewrite->using_index_permalinks() ) {
				$url = get_home_url( $blog_id, $wp_rewrite->index . '/onthisday' );

			} else {
				$url = get_home_url( $blog_id, 'onthisday' );
			}
		} else {
				$url = trailingslashit( get_home_url( $blog_id, '' ) );
				// nginx only allows HTTP/1.0 methods when redirecting from / to /index.php.
				// To work around this, we manually add index.php to the URL, avoiding the redirect.
			if ( 'index.php' !== substr( $url, 9 ) ) {
				$url .= 'index.php';
			}

			$url = add_query_arg( 'onthisday', 1, $url );
		}
		return $url;
	}

	public static function rewrite_rules() {
		$onthisday_slug = self::get_slug();

		// On This Specific Day.
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

		// On This Day Today.
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
			// On This Day Today Map with Pagination
			add_rewrite_rule(
				$onthisday_slug . '/map/' . tempus_get_pagination_regex(),
				'index.php?onthisday=1&paged=$matches[1]&map=1',
				'top'
			);

			if ( class_exists( 'Simple_Location_Plugin' ) ) {
				// On This Day Today Map.
				add_rewrite_rule(
					$onthisday_slug . '/map/?$',
					'index.php?onthisday=1&map=1',
					'top'
				);
			}

			if ( class_exists( 'Post_Kinds_Plugin' ) ) {
				$kind_photos_slug = apply_filters( 'kind_photos_slug', 'photos' );
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

	public static function pre_get_posts( $query ) {
		// check if the user is requesting an admin page
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$onthisday = get_query_var( 'onthisday' );
		// Return if  not set
		if ( $onthisday && empty( get_query_var( 'year' ) ) && empty( get_query_var( 'monthnum' ) ) && empty( get_query_var( 'day' ) ) ) {
			$now                    = new DateTime( 'now', wp_timezone() );
			$query->is_date         = true;
			$query->is_day          = true;
			$query->is_home         = false;
			$query->is_archive      = true;
			$query->is_comment_feed = false;
			$query->set(
				'date_query',
				array(
					array(
						'month'  => $now->format( 'n' ),
						'day'    => $now->format( 'j' ),
						'before' => 'yesterday',
					),
				)
			);
		}
		return $query;
	}

	public static function is_onthisday() {
		return ( is_day() && empty( get_query_var( 'year' ) ) );
	}

	public static function archive_title( $title ) {
		if ( self::is_onthisday() ) {
			$title  = get_the_date( _x( 'F j', 'daily archives date format', 'default' ) );
			$prefix = _x( 'On This Day:', 'date archive title prefix', 'default' );
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
		if ( self::is_onthisday() ) {
			$title['title'] = get_the_date();
		}
		return $title;
	}
}
