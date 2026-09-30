<?php
/**
 * Rewrite helper functions.
 *
 * Builds the regular expressions used when registering the plugin's rewrite rules.
 *
 * @package TempusFugit
 * @since 1.0.0
 */

/**
 * Returns the feed types registered with the rewrite API.
 *
 * @since 1.0.0
 *
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @return string[] Feed names, for example 'rss2' and 'atom'.
 */
function tempus_get_feeds() {
	global $wp_rewrite;
	return $wp_rewrite->feeds;
}

/**
 * Builds a regex that matches the feed portion of a URL.
 *
 * @since 1.0.0
 *
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @param bool $base Optional. Whether to prefix the regex with the feed base, as in `feed/rss2`.
 *                   Default true.
 * @return string Feed regex, for example `feed/(feed|rdf|rss|rss2|atom)/?$`.
 */
function tempus_get_feed_regex( $base = true ) {
	global $wp_rewrite;
	// Build a regex to match the feed section of URLs, such as `(feed|atom|rss|rss2)/?$`.
	$feedregex2 = '';
	foreach ( (array) $wp_rewrite->feeds as $feed_name ) {
			$feedregex2 .= $feed_name . '|';
	}

	$feedregex2 = '(' . trim( $feedregex2, '|' ) . ')/?$';
	if ( $base ) {
		return $wp_rewrite->feed_base . '/' . $feedregex2;
	} else {
		return '/' . $feedregex2;
	}
}

/**
 * Builds a regex that matches the pagination portion of a URL.
 *
 * @since 1.0.0
 *
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @return string Pagination regex, for example `page/?([0-9]{1,})/?$`.
 */
function tempus_get_pagination_regex() {
	global $wp_rewrite;
	return $wp_rewrite->pagination_base . '/?([0-9]{1,})/?$';
}

/**
 * Joins URL segments into a rewrite rule regex anchored at the end.
 *
 * @since 1.0.2
 *
 * @param string[] $elements URL segments to join with slashes.
 * @return string Joined regex, or an empty string if no segments were given.
 */
function tempus_generate_permastruct( $elements ) {
	if ( empty( $elements ) || ! is_array( $elements ) ) {
		return '';
	}
		$elements[] = '?$';
		return implode( '/', $elements );
}
