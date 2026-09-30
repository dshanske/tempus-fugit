<?php
/**
 * Template functions.
 *
 * Public helpers for themes: links to a post's day and week archives, and
 * information about the date archive being viewed.
 *
 * @package TempusFugit
 * @since 1.0.2
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the URL of the day archive for a post's publish date.
 *
 * Uses the site's date permalink structure, including the `%dayofyear%` tag,
 * and falls back to a `?m=YYYYMMDD` query URL when pretty permalinks are off.
 *
 * @since 1.0.2
 *
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @param int|WP_Post|null $post Optional. Post ID or post object. Default is the current post.
 * @return string Day archive URL.
 */
function tempus_get_post_day_link( $post = null ) {
	$post = get_post( $post ); // Allows support of current post and post ID.
	global $wp_rewrite;
	$daylink   = $wp_rewrite->get_day_permastruct();
	$datetime  = get_post_datetime( $post );
	$year      = $datetime->format( 'Y' );
	$month     = $datetime->format( 'm' );
	$day       = $datetime->format( 'd' );
	$dayofyear = (int) $datetime->format( 'z' ) + 1; // Ordinal day of the year, 001-366.
	if ( ! empty( $daylink ) ) {
		$daylink = str_replace( '%year%', $year, $daylink );
		$daylink = str_replace( '%monthnum%', zeroise( (int) $month, 2 ), $daylink );
		$daylink = str_replace( '%day%', zeroise( (int) $day, 2 ), $daylink );
		$daylink = str_replace( '%dayofyear%', zeroise( $dayofyear, 3 ), $daylink );
		$daylink = home_url( user_trailingslashit( $daylink, 'day' ) );
	} else {
		$daylink = home_url( '?m=' . $year . zeroise( $month, 2 ) . zeroise( $day, 2 ) );
	}
	return $daylink;
}

/**
 * Returns the URL of the week archive (`/YYYY/Www/`) for a post's publish date.
 *
 * Uses the ISO-8601 week number and the ISO-8601 year that week belongs to.
 *
 * @since 1.1.2
 *
 * @param int|WP_Post|null $post Optional. Post ID or post object. Default is the current post.
 * @return string Week archive URL.
 */
function tempus_get_post_week_link( $post = null ) {
	$post     = get_post( $post ); // Allows support of current post and post ID.
	$weeklink = '%year%/W%week%';
	$datetime = get_post_datetime( $post );
	$year     = $datetime->format( 'o' );
	$week     = $datetime->format( 'W' );
	$month    = $datetime->format( 'm' );
	$day      = $datetime->format( 'd' );
	if ( ! empty( $weeklink ) ) {
		$weeklink = str_replace( '%year%', $year, $weeklink );
		$weeklink = str_replace( '%week%', zeroise( (int) $week, 2 ), $weeklink );
		$weeklink = home_url( user_trailingslashit( $weeklink, 'week' ) );
	} else {
		$weeklink = home_url( '?m=' . $year . zeroise( $month, 2 ) . zeroise( $day, 2 ) );
	}
	return $weeklink;
}



/**
 * Returns the date parts of the date archive being viewed.
 *
 * Combines the date query vars with any `date_query` set on the main query.
 *
 * @since 1.2.0
 *
 * @return array|false Non-empty date parts keyed by name (for example 'year', 'monthnum',
 *                     'day', 'dayofyear'), or false if this is not a date archive.
 */
function tempus_get_archive_date_query() {
	if ( ! is_date() ) {
		return false;
	}
	$return     = array();
	$properties = array( 'day', 'monthnum', 'year', 'dayofyear', 'dayofweek', 'hour', 'minute', 'second', 'dayofweek_iso' );
	foreach ( $properties as $var ) {
		$return[ $var ] = get_query_var( $var );
	}
	$return = array_filter( $return );
	if ( is_array( get_query_var( 'date_query' ) ) ) {
		$date_query = wp_array_slice_assoc( get_query_var( 'date_query' ), $properties );
		$return     = array_merge( $return, $date_query );
	}
	return array_filter( $return );
}

/**
 * Returns the date of the day, month, or year archive being viewed.
 *
 * @since 1.2.0
 *
 * @return DateTime|false Archive date in the site's timezone, or false if this is not a
 *                        day, month, or year archive.
 */
function tempus_get_archive_datetime() {
	if ( ! is_date() ) {
		return false;
	}
	$date = tempus_get_archive_date_query();
	$d    = '';
	if ( array_key_exists( 'year', $date ) ) {
		$d .= $date['year'];
	}
	if ( array_key_exists( 'monthnum', $date ) ) {
		if ( ! empty( $d ) ) {
			$d .= '-';
		}
		$d .= $date['monthnum'];
	}
	if ( array_key_exists( 'day', $date ) ) {
		if ( ! empty( $d ) ) {
			$d .= '-';
		}
		$d .= $date['day'];
	}
	if ( is_day() ) {
		return date_create_from_format( 'Y-m-d', $d, wp_timezone() );
	} elseif ( is_month() ) {
		return date_create_from_format( 'Y-m', $d, wp_timezone() );
	} elseif ( is_year() ) {
		return date_create_from_format( 'Y', $d, wp_timezone() );
	}
	return false;
}
