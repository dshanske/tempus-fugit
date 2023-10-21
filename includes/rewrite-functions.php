<?php
/**
 * Rewrites Assist Functions
 *
 * Adds Rewrites
 */
function tempus_get_feeds() {
	global $wp_rewrite;
	return $wp_rewrite->feeds;
}

function tempus_get_feed_regex( $base = true ) {
	global $wp_rewrite;
	// Build a regex to match the feed section of URLs, something like (feed|atom|rss|rss2)/?
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

function tempus_get_pagination_regex() {
	global $wp_rewrite;
	return $wp_rewrite->pagination_base . '/?([0-9]{1,})/?$';
}

function tempus_generate_permastruct( $elements ) {
	if ( empty( $elements ) || ! is_array( $elements ) ) {
		return '';
	}
		$elements[] = '?$';
		return implode( '/', $elements );
}
