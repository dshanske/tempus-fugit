<?php
/**
 * Global Functions
 */
function tempus_get_post_day_link( $post = null ) {
	$post = get_post( $post ); // Allows support of current post and post ID.
	global $wp_rewrite;
	$daylink   = $wp_rewrite->get_day_permastruct();
	$datetime  = get_post_datetime( $post );
	$year      = $datetime->format( 'Y' );
	$month     = $datetime->format( 'm' );
	$day       = $datetime->format( 'd' );
	$dayofyear = $datetime->format( 'z' );
	if ( ! empty( $daylink ) ) {
		$daylink = str_replace( '%year%', $year, $daylink );
		$daylink = str_replace( '%monthnum%', zeroise( (int) $month, 2 ), $daylink );
		$daylink = str_replace( '%day%', zeroise( (int) $day, 2 ), $daylink );
		$daylink = str_replace( '%dayofyear%', zeroise( (int) $dayofyear, 2 ), $daylink );
		$daylink = home_url( user_trailingslashit( $daylink, 'day' ) );
	} else {
		$daylink = home_url( '?m=' . $year . zeroise( $month, 2 ) . zeroise( $day, 2 ) );
	}
	return $daylink;
}

function tempus_get_post_week_link( $post = null ) {
	$post     = get_post( $post ); // Allows support of current post and post ID.
	$weeklink = '%year%/W%week%';
	$datetime = get_post_datetime( $post );
	$year     = $datetime->format( 'Y' );
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



/*
 * Returns the date of the current archive
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
