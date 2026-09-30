<?php
/**
 * Sorted archives.
 *
 * @package TempusFugit
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds `/updated`, `/oldest`, and `/random` archives of all posts.
 *
 * @since 1.0.0
 */
class Tempus_Order_By {
	/**
	 * Registers the hooks for sorted archives.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
		add_filter( 'pre_get_posts', array( __CLASS__, 'order_by' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'get_the_archive_title', array( __CLASS__, 'archive_title' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ) );
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
	 * Adds the `tempus_sort` public query var.
	 *
	 * Hooked to `query_vars`.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $var Public query vars.
	 * @return string[] Public query vars.
	 */
	public static function query_vars( $var ) {
		$var[] = 'tempus_sort';
		return $var;
	}

	/**
	 * Registers the rules for `/updated`, `/oldest`, and `/random`, with feeds and pagination.
	 *
	 * @since 1.0.0
	 */
	public static function rewrite_rules() {
		$sort = '(updated|oldest|random)/';
		add_rewrite_rule(
			$sort . tempus_get_feed_regex( false ),
			'index.php?feed=$matches[2]&tempus_sort=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			$sort . tempus_get_feed_regex(),
			'index.php?feed=$matches[2]&tempus_sort=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			$sort . tempus_get_pagination_regex(),
			'index.php?tempus_sort=$matches[1]&paged=$matches[2]',
			'top'
		);

		add_rewrite_rule(
			$sort . '?$',
			'index.php?tempus_sort=$matches[1]',
			'top'
		);
	}

	/**
	 * Applies the order requested by the `tempus_sort` query var.
	 *
	 * Hooked to `pre_get_posts`. Skips admin requests.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Query $query The query being prepared.
	 * @return WP_Query|void The query, or nothing for requests that are skipped.
	 */
	public static function order_by( $query ) {
		// Skip admin requests.
		if ( is_admin() ) {
			return;
		}

		if ( ! empty( $query->get( 'tempus_sort' ) ) ) {
			$query->is_archive      = true;
			$query->is_home         = false;
			$query->is_comment_feed = false;
		}
		if ( 'updated' === $query->get( 'tempus_sort' ) ) {
			$query->set( 'orderby', 'modified' );
		} elseif ( 'oldest' === $query->get( 'tempus_sort' ) ) {
			$query->set( 'order', 'ASC' );
		} elseif ( 'random' === $query->get( 'tempus_sort' ) ) {
			$query->set( 'orderby', 'rand' );
		}
		return $query;
	}


	/**
	 * Sets the archive title for sorted archives, such as "Last Updated".
	 *
	 * Hooked to `get_the_archive_title`.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title Archive title.
	 * @return string Archive title.
	 */
	public static function archive_title( $title ) {
		$sort = get_query_var( 'tempus_sort' );
		if ( $sort ) {
			$return = self::title( $sort );
			if ( $return ) {
				return $return;
			}
		}
		return $title;
	}

	/**
	 * Returns the URL of a sorted archive, such as `/random/`.
	 *
	 * @since 1.2.1
	 *
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 *
	 * @param string $sort Sort type: 'updated', 'oldest', or 'random'.
	 * @return string Archive URL, or an empty string for an unknown sort type.
	 */
	public static function get_link( $sort ) {
		global $wp_rewrite;
		if ( ! in_array( $sort, array( 'updated', 'oldest', 'random' ), true ) ) {
			return '';
		}
		if ( $wp_rewrite->using_index_permalinks() ) {
			return home_url( user_trailingslashit( $wp_rewrite->index . '/' . $sort ) );
		}
		if ( $wp_rewrite->using_permalinks() ) {
			return home_url( user_trailingslashit( $sort ) );
		}
		return add_query_arg( 'tempus_sort', $sort, home_url( '/' ) );
	}

	/**
	 * Returns the title for a sort type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sort Sort type: 'updated', 'random', or 'oldest'.
	 * @return string Title, or an empty string for an unknown sort type.
	 */
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

	/**
	 * Sets the document title for sorted archives.
	 *
	 * Hooked to `document_title_parts`.
	 *
	 * @since 1.0.0
	 *
	 * @param array $title Document title parts.
	 * @return array Document title parts.
	 */
	public static function title_parts( $title ) {
		$sort = get_query_var( 'tempus_sort' );
		if ( $sort ) {
			$title['title'] = self::title( $sort );
		}
		return $title;
	}
}
