<?php
/**
 * Week of Year Class
 *
 * Adds the ability to have permalinks with the week of the year.
 */
class Tempus_Week_Of_Year {
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

	public static function plugins_loaded() {
		self::rewrite_rules();
	}

	public static function query_vars( $var ) {
		$var[] = 'week';
		return $var;
	}

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

	public static function archive_permalink_structure_tags( $tags ) {
		/* translators: The numerical week of the year. */
		$tags['week'] = __( '%s (2 Digit Week.)', 'tempus-fugit' );
		return $tags;
	}

	public static function post_link( $permalink, $post ) {
		if ( false === strpos( $permalink, '%week%' ) ) {
			return $permalink;
		}
		$datetime = get_post_datetime( $post );
		return str_replace( '%week%', zeroise( $datetime->format( 'W' ), 2 ), $permalink );
	}

	public static function week_of_year( $query ) {
		// check if the user is requesting an admin page
		if ( is_admin() ) {
			return;
		}

		// If this is a date archive
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

	public static function is_week() {
		return ( is_date() && is_numeric( get_query_var( 'week' ) ) );
	}

	public static function archive_title( $title ) {
		if ( self::is_week() ) {
			$title  = get_the_date( _x( 'W, Y', 'weekly archives date format', 'default' ) );
			$prefix = _x( 'Week', 'date archive title prefix', 'default' );
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
		if ( self::is_week() ) {
			$title['title'] = get_the_date( 'W' );
		}
		return $title;
	}
}
