<?php
/**
 * Date navigation template functions.
 *
 * Previous and next links between day, month, and year archives.
 *
 * @package TempusFugit
 * @since 1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns a link to the previous or next date archive that has posts.
 *
 * Works on day, month, year, week, and day-of-year archives. The link goes to the archive of the
 * same type that contains the nearest earlier (or later) published post, so empty periods are
 * skipped and there is no link past the newest post.
 *
 * @since 1.2.0
 * @since 1.2.1 Skips periods without posts, supports week and day-of-year archives, and added `$text`.
 *
 * @param bool   $previous Optional. Whether to link to the previous archive (true) or the next (false).
 *                         Default true.
 * @param string $text     Optional. Link text. `%title` is replaced with the archive's date, such as
 *                         "June 14, 2020" or "Week 11, 2024". Default '%title'.
 * @return string|false Anchor tag HTML, an empty string if there are no posts in that direction,
 *                      or false if this is not a day, month, year, week, or day-of-year archive.
 */
function tempus_get_adjacent_date_link( $previous = true, $text = '%title' ) {
	$period = tempus_get_archive_period();
	if ( ! $period ) {
		return false;
	}
	// Posts before the start of this period, or after its end.
	$bound = array( 'inclusive' => false );
	if ( $previous ) {
		$bound['before'] = $period['start']->format( 'Y-m-d H:i:s' );
	} else {
		$bound['after'] = $period['end']->format( 'Y-m-d H:i:s' );
	}
	$posts = get_posts(
		array(
			'numberposts' => 1,
			'fields'      => 'ids',
			'orderby'     => 'date',
			'order'       => $previous ? 'DESC' : 'ASC',
			'date_query'  => array( $bound ),
		)
	);
	if ( ! $posts ) {
		return '';
	}

	$date      = get_post_datetime( $posts[0] );
	$timestamp = $date->getTimestamp();
	switch ( $period['type'] ) {
		case 'week':
			$link  = tempus_get_post_week_link( $posts[0] );
			$label = sprintf(
				/* translators: 1: ISO-8601 week number. 2: ISO-8601 year. */
				__( 'Week %1$s, %2$s', 'tempus-fugit' ),
				$date->format( 'W' ),
				$date->format( 'o' )
			);
			break;
		case 'dayofyear':
			$link  = tempus_get_day_of_year_link( $date );
			$label = wp_date( get_option( 'date_format' ), $timestamp );
			break;
		case 'day':
			$link  = get_day_link( $date->format( 'Y' ), $date->format( 'm' ), $date->format( 'd' ) );
			$label = wp_date( get_option( 'date_format' ), $timestamp );
			break;
		case 'month':
			$link  = get_month_link( $date->format( 'Y' ), $date->format( 'm' ) );
			$label = wp_date( _x( 'F Y', 'monthly archives date format', 'tempus-fugit' ), $timestamp );
			break;
		default:
			$link  = get_year_link( $date->format( 'Y' ) );
			$label = $date->format( 'Y' );
	}

	$rel = $previous ? 'prev' : 'next';
	return '<a href="' . esc_url( $link ) . '" rel="' . $rel . '">' . wp_kses_post( str_replace( '%title', esc_html( $label ), $text ) ) . '</a>';
}

/**
 * Returns previous/next navigation markup for a date archive.
 *
 * Mirrors the markup of core's `get_the_post_navigation()`.
 *
 * @since 1.2.0
 * @since 1.2.1 Applies the `prev_text`, `next_text`, `screen_reader_text`, and `aria_label` arguments.
 *
 * @param array $args {
 *     Optional. Navigation arguments. Default empty array.
 *
 *     @type string $prev_text          Previous link text. `%title` is replaced with the archive's
 *                                      date. Default '%title'.
 *     @type string $next_text          Next link text. `%title` is replaced with the archive's date.
 *                                      Default '%title'.
 *     @type string $screen_reader_text Screen reader text for the nav heading.
 *                                      Default 'Date navigation'.
 *     @type string $aria_label         ARIA label for the nav element. Default is the
 *                                      screen reader text.
 *     @type string $class              Custom class for the nav element. Default 'date-navigation'.
 * }
 * @return string Navigation markup.
 */
function tempus_get_the_date_navigation( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'prev_text'          => '%title',
			'next_text'          => '%title',
			/* translators: Hidden accessibility text. */
			'screen_reader_text' => __( 'Date navigation', 'tempus-fugit' ),
			'aria_label'         => '',
			'class'              => 'date-navigation',
		)
	);
	if ( empty( $args['aria_label'] ) ) {
		$args['aria_label'] = $args['screen_reader_text'];
	}

	$previous = tempus_get_adjacent_date_link( true, $args['prev_text'] );
	if ( $previous ) {
		$previous = '<div class="nav-previous">' . $previous . '</div>';
	}

	$next = tempus_get_adjacent_date_link( false, $args['next_text'] );
	if ( $next ) {
		$next = '<div class="nav-next">' . $next . '</div>';
	}

	$template = '
		<nav class="navigation %1$s" aria-label="%4$s">
			<h2 class="screen-reader-text">%2$s</h2>
			<div class="nav-links">%3$s</div>
		</nav>';
	return sprintf( $template, sanitize_html_class( $args['class'] ), esc_html( $args['screen_reader_text'] ), $previous . $next, esc_attr( $args['aria_label'] ) );
}
