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

	public static function rewrite_rules() {
		$onthisday_slug = apply_filters( 'tempus_fugit_onthisday_slug', 'onthisday' );

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

	}

	public static function pre_get_posts( $query ) {
		// check if the user is requesting an admin page
		if ( is_admin() ) {
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
					'month' => $now->format( 'n' ),
					'day'   => $now->format( 'j' ),
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


