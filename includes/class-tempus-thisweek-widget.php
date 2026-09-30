<?php
/**
 * This Week widget.
 *
 * @package TempusFugit
 * @since 1.0.3
 */

defined( 'ABSPATH' ) || exit;

/**
 * Widget listing posts published in the current week number in previous years, grouped by how long ago.
 *
 * @since 1.0.3
 */
class Tempus_ThisWeek_Widget extends Tempus_OnThisDay_Widget {
	/**
	 * Sets up the widget name and description.
	 *
	 * @since 1.0.3
	 */
	public function __construct() {
		WP_Widget::__construct(
			'Tempus_ThisWeek_Widget', // Base ID.
			__( 'This Week Widget', 'tempus-fugit' ), // Name.
			array(
				'classname'   => 'thisweek_widget',
				'description' => __( 'A widget that allows you to display a list of posts from this week in history', 'tempus-fugit' ),
			)
		);
	}

	/**
	 * Fills in default settings.
	 *
	 * @since 1.0.3
	 *
	 * @param array $instance Widget settings.
	 * @return array Widget settings with defaults for 'title', 'number', and 'nonefound'.
	 */
	public function defaults( $instance ) {
		$defaults = array(
			'title'     => '',
			'number'    => 5,
			'nonefound' => __( 'There were no posts on this week in previous years', 'tempus-fugit' ),
		);
		return wp_parse_args( $instance, $defaults );
	}

	/**
	 * Outputs the widget on the front end.
	 *
	 * Results are cached in a transient for an hour.
	 *
	 * @since 1.0.3
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Display arguments, including 'before_title', 'after_title',
	 *                        'before_widget', and 'after_widget'.
	 * @param array $instance Widget settings.
	 */
	public function widget( $args, $instance ) {
		$instance = $this->defaults( $instance );

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		// $date = new DateTime( '2020-01-01' ); // Uncomment for testing.
		$date = new DateTime( 'now', wp_timezone() );
		echo $args['before_widget']; // phpcs:ignore
		if ( ! empty( $instance['title'] ) ) {
			echo wp_kses( $args['before_title'] . sprintf( '<a href="%1$s">%2$s</a>', esc_url( Tempus_This_Week::get_link() ), $title ) . $args['after_title'], Tempus_Fugit_Plugin::kses_clean() );
		}
		$transient = 'thisweek_widget' . $date->format( 'w' );
		$posts     = get_transient( $transient );
		if ( false === $posts ) {
			$query = array(
				'w'           => $date->format( 'W' ),
				'numberposts' => max( 1, absint( $instance['number'] ) ),
				'fields'      => 'ids',
				'date_query'  => array(
					array(
						'before' => 'first day of january this year',
					),
				),
			);
			$posts = get_posts( $query );
		}
		set_transient( $transient, $posts, HOUR_IN_SECONDS );
		$organize = array();
		foreach ( $posts as $post ) {
			$diff = sprintf( '<a href="%1$s">%2$s</a>', esc_url( tempus_get_post_week_link( $post ) ), esc_html( human_time_diff( get_post_timestamp( $post ) ) ) );
			if ( ! array_key_exists( $diff, $organize ) ) {
				$organize[ $diff ] = array();
			}
			$organize[ $diff ][] = $this->list_item( $post );
		}

		echo '<div id="tempus-thisweek">';
		if ( ! empty( $organize ) ) {
			echo '<ul>';
			foreach ( $organize as $title => $year ) {
				echo '<li>';
				/* translators: %s: Human-readable time difference. */
				printf( esc_html__( '%s ago...', 'tempus-fugit' ), wp_kses( $title, Tempus_Fugit_Plugin::kses_clean() ) );
				echo '<ul>';
				echo wp_kses( implode( '', $year ), Tempus_Fugit_Plugin::kses_clean() );
				echo '</li></ul>';
			}
			echo '</ul>';
		} else {
			echo esc_html( $instance['nonefound'] );
		}
		echo '</div>';
		echo $args['after_widget']; // phpcs:ignore
	}
}
