<?php
/**
 * Global Functions
 */
function tempus_get_post_day_link( $post ) {
	global $wp_rewrite;
		$daylink = $wp_rewrite->get_day_permastruct();
	$datetime    = get_post_datetime( $post );
	$year        = $datetime->format( 'Y' );
	$month       = $datetime->format( 'm' );
	$day         = $datetime->format( 'd' );
	$dayofyear   = $datetime->format( 'z' );
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

