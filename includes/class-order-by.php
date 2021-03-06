<?php
/**
 * Order By
 *
 * Adds the ability to have Order By Permalinks
 */
class Order_By {
	public function __construct() {
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
		add_filter( 'pre_get_posts', array( __CLASS__, 'order_by' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		//add_filter( 'get_the_archive_title', array( __CLASS__, 'archive_title' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ) );
	}

	public static function plugins_loaded() {
		self::rewrite_rules();
	}

	public static function query_vars( $var ) {
		$var[] = 'sort';
		return $var;
	}

	public static function rewrite_rules() {
		$sort = '(updated|oldest|random)/';
		add_rewrite_rule( 
			$sort . tf_get_feed_regex( false ),
			'index.php?feed=$matches[2]&sort=$matches[1]',
			'top'
		);
		add_rewrite_rule( 
			$sort . tf_get_feed_regex(),
			'index.php?feed=$matches[2]&sort=$matches[1]',
			'top'
		);

		add_rewrite_rule( 
			$sort . tf_get_pagination_regex(),
			'index.php?sort=$matches[1]&paged=$matches[2]',
			'top'
		);

		add_rewrite_rule( 
			$sort . '?$',
			'index.php?sort=$matches[1]',
			'top'
		);

	}

	public static function order_by( $query ) {
		// check if the user is requesting an admin page
		if ( is_admin() ) {
			return;
		}

		if ( ! empty( $query->get( 'sort' ) ) ) {
			$query->is_archive = true;
			$query->is_home = false;
			$query->is_comment_feed = false;
		}
		if ( 'updated' === $query->get( 'sort' ) ) {
			$query->set( 'orderby', 'modified' );
		} else if ( 'oldest' === $query->get( 'sort' ) ) {
			$query->set( 'order', 'ASC' );
		} else if ( 'random' === $query->get( 'sort' ) ) {
			$query->set( 'orderby', 'rand' );
		}
		return $query;
	}


	public static function is_sort() {
		return ( get_query_var( 'sort' ) );
	}

	public static function archive_title( $title ) {
		if ( self::is_updated() ) {
			$title  = 'Updated'; // get_the_date( _x( 'F j, Y', 'daily archives date format' ) );
			$prefix = _x( 'Day:', 'date archive title prefix' );
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
					_x( '%1$s %2$s', 'archive title' ),
					$prefix,
					'<span>' . $title . '</span>'
				);
			}
		}
		return $title;
	}

	public static function title_parts( $title ) {
		if ( self::is_sort() ) {
			$title['title'] = 'Archive';
		}
		return $title;
	}
}


