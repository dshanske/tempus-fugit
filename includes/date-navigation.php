<?php
/**
 * Date Navigation Functions
 */

function tempus_get_adjacent_date_link( $previous = true ) {
	if ( ! is_date() ) {
		return false;
	}
	$datetime = tempus_get_archive_datetime();
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
	$string = '<a href="' . $link . '" rel="' . $rel . '">' . $linktime->format( $format ) . '</a>';
	return $string;
}

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
