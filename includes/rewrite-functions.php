<?php
/**
 * Rewrites Assist Functions
 *
 * Adds Rewrites
 */
function tf_get_feeds() {
	global $wp_rewrite;
	return $wp_rewrite->feeds;
}

function tf_get_feed_regex() {
	global $wp_rewrite;
	// Build a regex to match the feed section of URLs, something like (feed|atom|rss|rss2)/?
	$feedregex2 = '';
	foreach ( (array) $wp_rewrite->feeds as $feed_name ) {
			 $feedregex2 .= $feed_name . '|';
	}

	$feedregex2 = '(' . trim( $feedregex2, '|' ) . ')/?$';

	return $wp_rewrite->feed_base . '/' . $feedregex2;
}

function tf_get_pagination_regex() {
	global $wp_rewrite;
	return $wp_rewrite->pagination_base . '/?([0-9]{1,})/?$';
}
