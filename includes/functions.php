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
 * Returns the start of the date archive being viewed.
 *
 * @since 1.2.0
 * @since 1.3.0 Supports week and day-of-year archives, and returns the start of the period.
 *
 * @return DateTime|false Start of the archive's period in the site's timezone, or false if this
 *                        is not a day, month, year, week, or day-of-year archive.
 */
function tempus_get_archive_datetime() {
	$period = tempus_get_archive_period();
	return $period ? DateTime::createFromImmutable( $period['start'] ) : false;
}

/**
 * Returns the type and date range of the date archive being viewed.
 *
 * On This Day and This Week archives have no year, so they have no period.
 *
 * @since 1.3.0
 *
 * @return array|false {
 *     The archive's period, or false if this is not a day, month, year, week, or day-of-year archive.
 *
 *     @type string            $type  'day', 'month', 'year', 'week', or 'dayofyear'.
 *     @type DateTimeImmutable $start First second of the period, in the site's timezone.
 *     @type DateTimeImmutable $end   Last second of the period, in the site's timezone.
 * }
 */
function tempus_get_archive_period() {
	if ( ! is_date() ) {
		return false;
	}
	$timezone = wp_timezone();
	if ( Tempus_Week_Of_Year::is_week() && get_query_var( 'tempus_year' ) ) {
		$type  = 'week';
		$start = Tempus_Week_Of_Year::get_week_start( (int) get_query_var( 'tempus_year' ), (int) get_query_var( 'tempus_week' ) );
		$end   = $start->modify( '+6 days' );
	} elseif ( Tempus_Day_Of_Year::is_dayofyear() && get_query_var( 'tempus_year' ) ) {
		$type  = 'dayofyear';
		$start = DateTimeImmutable::createFromFormat( '!Y z', get_query_var( 'tempus_year' ) . ' ' . ( (int) get_query_var( 'dayofyear' ) - 1 ), $timezone );
		$end   = $start;
	} else {
		$date = tempus_get_archive_date_query();
		if ( empty( $date['year'] ) ) {
			return false;
		}
		// The ! resets unspecified fields, so a month archive starts on the 1st, not today's day.
		if ( is_day() ) {
			$type  = 'day';
			$start = DateTimeImmutable::createFromFormat( '!Y-m-d', $date['year'] . '-' . $date['monthnum'] . '-' . $date['day'], $timezone );
			$end   = $start;
		} elseif ( is_month() ) {
			$type  = 'month';
			$start = DateTimeImmutable::createFromFormat( '!Y-m', $date['year'] . '-' . $date['monthnum'], $timezone );
			$end   = $start->modify( 'last day of this month' );
		} elseif ( is_year() ) {
			$type  = 'year';
			$start = DateTimeImmutable::createFromFormat( '!Y', (string) $date['year'], $timezone );
			$end   = $start->modify( 'last day of december' );
		} else {
			return false;
		}
	}
	return array(
		'type'  => $type,
		'start' => $start->setTime( 0, 0 ),
		'end'   => $end->setTime( 23, 59, 59 ),
	);
}

/**
 * Returns the URL of a day-of-year archive, such as `/2024/075/`.
 *
 * Falls back to the day archive when there is no day-of-year permalink structure.
 *
 * @since 1.3.0
 *
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @param DateTimeInterface $date A date in the day.
 * @return string Archive URL.
 */
function tempus_get_day_of_year_link( $date ) {
	global $wp_rewrite;
	$struct = $wp_rewrite->get_extra_permastruct( 'dayofyear' );
	if ( ! $struct ) {
		return get_day_link( $date->format( 'Y' ), $date->format( 'm' ), $date->format( 'd' ) );
	}
	$path = str_replace( array( '%year%', '%dayofyear%' ), array( $date->format( 'Y' ), zeroise( (int) $date->format( 'z' ) + 1, 3 ) ), $struct );
	return home_url( user_trailingslashit( $path, 'day' ) );
}

/**
 * Returns the name of the term being viewed on a category, tag, or taxonomy archive.
 *
 * @since 1.3.0
 *
 * @return string Term name, or an empty string outside term archives.
 */
function tempus_get_queried_term_name() {
	if ( ! is_category() && ! is_tag() && ! is_tax() ) {
		return '';
	}
	$term = get_queried_object();
	return ( $term instanceof WP_Term ) ? $term->name : '';
}
