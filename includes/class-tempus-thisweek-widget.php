<?php

class Tempus_ThisWeek_Widget extends Tempus_OnThisDay_Widget {
	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		WP_Widget::__construct(
			'Tempus_ThisWeek_Widget',                // Base ID
			__( 'This Week Widget', 'tempus-fugit' ),        // Name
			array(
				'classname'   => 'thisweek_widget',
				'description' => __( 'A widget that allows you to display a list of posts from this week in history', 'tempus-fugit' ),
			)
		);

	} // end constructor

	/**
	 * Set Defaults.
	 *
	 * @param array $instance Instance variable.
	 * @return array Instance after defaults added.
	 *
	 */
	public function defaults( $instance ) {
		$defaults = array(
			'number'    => 5,
			'nonefound' => __( 'There were no posts on this week in previous years', 'tempus-fugit' ),
		);
		return wp_parse_args( $defaults, $instance );
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		$instance = $this->defaults( $instance );
		//$date = new DateTime( '2020-01-01' ); // Uncomment for testing
		$date = new DateTime( 'now', wp_timezone() );
		// phpcs:ignore
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . sprintf( '<a href="%1$s">%2$s</a>', Tempus_This_Week::get_link(), apply_filters( 'widget_title', $instance['title'] ) ) . $args['after_title']; // phpcs:ignore
		}
		$transient = 'thisweek_widget' . $date->format( 'w' );
		$posts     = get_transient( $transient );
		if ( false === $posts ) {
			$query = array(
				'w'         => $date->format( 'W' ),
				'numberposts' => $instance['number'],
				'fields' => 'ids'
			);
			$posts = get_posts( $query );
		}
		set_transient( $transient, $posts, HOUR_IN_SECONDS );
		$organize = array();
		foreach ( $posts as $post ) {
			$diff = human_time_diff( get_post_timestamp( $post ) );
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
				printf( __( '%s ago...', 'tempus-fugit' ), $title ); // phpcs:ignore
				echo '<ul>';
				echo implode( '', $year ); // phpcs:ignore
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
