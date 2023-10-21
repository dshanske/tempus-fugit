<?php
/**
 * Order By
 *
 * Adds the ability to have Order By Permalinks
 */
class Tempus_Order_By {
	public function __construct() {
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
		add_filter( 'pre_get_posts', array( __CLASS__, 'order_by' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'get_the_archive_title', array( __CLASS__, 'archive_title' ) );
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
			$sort . tempus_get_feed_regex( false ),
			'index.php?feed=$matches[2]&sort=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			$sort . tempus_get_feed_regex(),
			'index.php?feed=$matches[2]&sort=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			$sort . tempus_get_pagination_regex(),
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
			$query->is_archive      = true;
			$query->is_home         = false;
			$query->is_comment_feed = false;
		}
		if ( 'updated' === $query->get( 'sort' ) ) {
			$query->set( 'orderby', 'modified' );
		} elseif ( 'oldest' === $query->get( 'sort' ) ) {
			$query->set( 'order', 'ASC' );
		} elseif ( 'random' === $query->get( 'sort' ) ) {
			$query->set( 'orderby', 'rand' );
		}
		return $query;
	}


	public static function archive_title( $title ) {
		$sort = get_query_var( 'sort' );
		if ( $sort ) {
			$return = self::title( $sort );
			if ( $return ) {
				return $return;
			}
		}
		return $title;
	}

	public static function title( $sort ) {
		$title = '';
		if ( 'updated' === $sort ) {
			$title = __( 'Last Updated', 'tempus-fugit' );
		} elseif ( 'random' === $sort ) {
			$title = __( 'Random Posts', 'tempus-fugit' );
		} elseif ( 'oldest' === $sort ) {
			$title = __( 'Oldest Posts', 'tempus-fugit' );
		}
		return $title;
	}

	public static function title_parts( $title ) {
		$sort = get_query_var( 'sort' );
		if ( $sort ) {
			$title['title'] = self::title( $sort );
		}
		return $title;
	}
}
