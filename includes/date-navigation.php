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
 * Returns a link to the previous or next day, month, or year archive.
 *
 * The period matches the archive being viewed. No link is returned for a date in the future.
 *
 * @since 1.2.0
 *
 * @param bool $previous Optional. Whether to link to the previous period (true) or the next (false).
 *                       Default true.
 * @return string|false Anchor tag HTML, an empty string if the adjacent period is in the future,
 *                      or false if this is not a day, month, or year archive.
 */
function tempus_get_adjacent_date_link( $previous = true ) {
	if ( ! is_date() ) {
		return false;
	}
	$datetime = tempus_get_archive_datetime();
	if ( ! $datetime ) {
		return false;
	}
	if ( is_day() ) {
		$interval = 'P1D';
	} elseif ( is_month() ) {
		$interval = 'P1M';
	} elseif ( is_year() ) {
		$interval = 'P1Y';
	}
	$linktime = $previous ? $datetime->sub( new DateInterval( $interval ) ) : $datetime->add( new DateInterval( $interval ) );
	$current  = new DateTime();
	if ( $current < $linktime ) {
		return '';
	}

	if ( is_day() ) {
		$link   = get_day_link( $datetime->format( 'Y' ), $datetime->format( 'm' ), $datetime->format( 'd' ) );
		$format = get_option( 'date_format' );
	} elseif ( is_month() ) {
		$link   = get_month_link( $datetime->format( 'Y' ), $datetime->format( 'm' ) );
		$format = 'F Y';
	} elseif ( is_year() ) {
		$link   = get_year_link( $datetime->format( 'Y' ) );
		$format = 'Y';
	}
	$rel    = $previous ? 'prev' : 'next';
	$string = '<a href="' . esc_url( $link ) . '" rel="' . esc_attr( $rel ) . '">' . esc_html( $linktime->format( $format ) ) . '</a>';
	return $string;
}

/**
 * Returns previous/next navigation markup for a date archive.
 *
 * Mirrors the markup of core's `get_the_post_navigation()`.
 *
 * @since 1.2.0
 *
 * @param array $args {
 *     Optional. Navigation arguments. Default empty array.
 *
 *     @type string $prev_text          Previous link text. Not currently used. Default '%title'.
 *     @type string $next_text          Next link text. Not currently used. Default '%title'.
 *     @type bool   $in_same_term       Not currently used. Default false.
 *     @type string $screen_reader_text Screen reader text for the nav heading. Not currently applied;
 *                                      the heading is always 'Date navigation'.
 *     @type string $aria_label         ARIA label for the nav element. Not currently applied;
 *                                      the label is always 'Date navigation'.
 *     @type string $class              Custom class for the nav element. Default 'date-navigation'.
 * }
 * @return string Navigation markup.
 */
function tempus_get_the_date_navigation( $args = array() ) {
	// Make sure the nav element has an aria-label attribute: fallback to the screen reader text.
	if ( ! empty( $args['screen_reader_text'] ) && empty( $args['aria_label'] ) ) {
		$args['aria_label'] = $args['screen_reader_text'];
	}

	$args       = wp_parse_args(
		$args,
		array(
			'prev_text'          => '%title',
			'next_text'          => '%title',
			'in_same_term'       => false,
			'screen_reader_text' => __( 'Date navigation', 'tempus-fugit' ),
			'aria_label'         => __( 'Dates', 'tempus-fugit' ),
			'class'              => 'date-navigation',
		)
	);
	$navigation = '';
	$previous   = tempus_get_adjacent_date_link();
	if ( $previous ) {
		$previous = '<div class="nav-previous">' . $previous . '</div>';
	}

	$next = tempus_get_adjacent_date_link( false );
	if ( $next ) {
		$next = '<div class="nav-next">' . $next . '</div>';
	}

	if ( empty( $screen_reader_text ) ) {
		$screen_reader_text = /* translators: Hidden accessibility text. */ __( 'Date navigation', 'tempus-fugit' );
	}
	if ( empty( $aria_label ) ) {
		$aria_label = $screen_reader_text;
	}

	$template = '
		<nav class="navigation %1$s" aria-label="%4$s">
			<h2 class="screen-reader-text">%2$s</h2>
			<div class="nav-links">%3$s</div>
		</nav>';
	return sprintf( $template, sanitize_html_class( $args['class'] ), esc_html( $screen_reader_text ), $previous . $next, esc_attr( $aria_label ) );
}
