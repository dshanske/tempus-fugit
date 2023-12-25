<?php

class Tempus_OnThisDay_Widget extends WP_Widget {
	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'Tempus_OnThisDay_Widget',                // Base ID
			__( 'On This Day Widget', 'tempus-fugit' ),        // Name
			array(
				'classname'   => 'onthisday_widget',
				'description' => __( 'A widget that allows you to display a list of posts from this day in history', 'tempus-fugit' ),
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
			'nonefound' => __( 'There were no posts on this day in previous years', 'tempus-fugit' ),
		);
		return wp_parse_args( $instance, $defaults );
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

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		// $date = new DateTime( '2020-01-01' ); // Uncomment for testing
		$date = new DateTime( 'now', wp_timezone() );
		echo $args['before_widget']; // phpcs:ignore
		if ( $title ) {
			echo wp_kses( $args['before_title'] . sprintf( '<a href="%1$s">%2$s</a>', Tempus_On_This_Day::get_link(), $title ) . $args['after_title'], Tempus_Fugit_Plugin::kses_clean() );
		}
		$transient = 'onthisday_widget' . $date->format( 'm-d' );
		$posts     = get_transient( $transient );
		if ( false === $posts ) {
			$query = array(
				'day'         => $date->format( 'd' ),
				'monthnum'    => $date->format( 'm' ),
				'date_query',
				array(
					array(
						'before' => 'yesterday',
					),
				),
				'numberposts' => $instance['number'],
				'fields'      => 'ids',
			);
			$posts = get_posts( $query );
		}
		set_transient( $transient, $posts, HOUR_IN_SECONDS );
		$organize = array();
		foreach ( $posts as $post ) {
			$diff = sprintf( '<a href="%1$s">%2$s</a>', tempus_get_post_day_link( $post ), human_time_diff( get_post_timestamp( $post ) ) );
			if ( ! array_key_exists( $diff, $organize ) ) {
				$organize[ $diff ] = array();
			}
			$organize[ $diff ][] = $this->list_item( $post );
		}

		echo '<div id="tempus-onthisday">';
		if ( ! empty( $organize ) ) {
			echo '<ul>';
			foreach ( $organize as $title => $year ) {
				echo '<li>';
				/* translators: %s: Human-readable time difference. */
				printf( esc_html( __( '%s ago...', 'tempus-fugit' ) ), wp_kses( $title, Tempus_Fugit_Plugin::kses_clean() ) );
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

	/**
	 * @access public
	 *
	 * @param WP_Post $post Post object
	 * @return string
	 */
	public function list_item( $post ) {
		$post = get_post( $post );
		return sprintf( '<li><a href="%2$s">%1$s</a></li>', $this->get_the_title( $post ), get_the_permalink( $post ) );
	}

	/**
	 * Construct a title for the post link.
	 *
	 * @access public
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	public function get_the_title( $post ) {
		$post = get_post( $post );
		if ( ! empty( $post->post_title ) ) {
			$title = $post->post_title;
		} elseif ( ! empty( $post->post_excerpt ) ) {
			$title = $post->post_excerpt;
		} elseif ( ! empty( $post->post_content ) ) {
			$title = mb_strimwidth( wp_strip_all_tags( $post->post_content ), 0, 40, '...' );
		} else {
			$title = get_the_date( 'Y ' . get_option( 'time_format' ), $post );
		}
		$title = apply_filters( 'tempus_widget_post_title', $title, $post );
		return trim( $title );
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		array_walk_recursive( $new_instance, 'sanitize_text_field' );
		return $new_instance;
	}


	/**
	 * Create the form for the Widget admin
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$instance = $this->defaults( $instance );
		?>
				<p><label for="title"><?php esc_html_e( 'Title: ', 'tempus-fugit' ); ?></label>
				<input type="text" size="30" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?> id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				value="<?php echo esc_html( ifset( $instance['title'] ) ); ?>" /></p>
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php esc_html_e( 'Number of Posts:', 'tempus-fugit' ); ?></label>
		<input type="number" min="1" step="1" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" value="<?php echo esc_attr( ifset( $instance['number'], 5 ) ); ?>" />
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'nonefound' ) ); ?>"><?php esc_html_e( 'Text if No Posts Found:', 'tempus-fugit' ); ?></label>
		<textarea class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'nonefound' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'nonefound' ) ); ?>"><?php echo esc_html( $instance['nonefound'] ); ?></textarea>
		</p>
		<?php
	}
}
