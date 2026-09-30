<?php
/**
 * On This Day widget.
 *
 * @package TempusFugit
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Widget listing posts published on today's date in previous years, grouped by how long ago.
 *
 * @since 1.0.0
 */
class Tempus_OnThisDay_Widget extends WP_Widget {
	/**
	 * Sets up the widget name and description.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			'Tempus_OnThisDay_Widget', // Base ID.
			__( 'On This Day Widget', 'tempus-fugit' ), // Name.
			array(
				'classname'   => 'onthisday_widget',
				'description' => __( 'A widget that allows you to display a list of posts from this day in history', 'tempus-fugit' ),
			)
		);
	}

	/**
	 * Fills in default settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array $instance Widget settings.
	 * @return array Widget settings with defaults for 'title', 'number', 'nonefound', 'taxonomy',
	 *               and 'term'.
	 */
	public function defaults( $instance ) {
		$defaults = array(
			'title'     => '',
			'number'    => 5,
			'nonefound' => __( 'There were no posts on this day in previous years', 'tempus-fugit' ),
			'taxonomy'  => '',
			'term'      => '',
		);
		return wp_parse_args( $instance, $defaults );
	}

	/**
	 * Returns the term the widget is limited to.
	 *
	 * @since 1.2.1
	 *
	 * @param array $instance Widget settings.
	 * @return WP_Term|false|null The term, false if the configured term doesn't exist, or null if
	 *                            the widget isn't limited to a term.
	 */
	protected function get_widget_term( $instance ) {
		if ( empty( $instance['taxonomy'] ) || empty( $instance['term'] ) ) {
			return null;
		}
		$term = get_term_by( 'slug', $instance['term'], $instance['taxonomy'] );
		return ( $term instanceof WP_Term ) ? $term : false;
	}

	/**
	 * Returns posts for the widget, cached in a transient for an hour.
	 *
	 * The cache is separate for each widget, period, term, and number of posts.
	 *
	 * @since 1.2.1
	 *
	 * @param array              $query    Arguments for get_posts().
	 * @param string             $period   The day or week being shown, such as '03-15' or '2026-W40'.
	 * @param WP_Term|false|null $term     Term from get_widget_term().
	 * @param array              $instance Widget settings.
	 * @return int[] Post IDs.
	 */
	protected function get_widget_posts( $query, $period, $term, $instance ) {
		if ( false === $term ) {
			return array();
		}
		$number = max( 1, absint( $instance['number'] ) );
		$key    = 'tempus_widget_' . md5( wp_json_encode( array( $this->id, $period, $term ? $term->term_id : 0, $number ) ) );
		$posts  = get_transient( $key );
		if ( false === $posts ) {
			$query['numberposts'] = $number;
			$query['fields']      = 'ids';
			if ( $term ) {
				$query['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Limited to the widget's term.
					array(
						'taxonomy' => $term->taxonomy,
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					),
				);
			}
			$posts = get_posts( $query );
			set_transient( $key, $posts, HOUR_IN_SECONDS );
		}
		return $posts;
	}

	/**
	 * Outputs the widget on the front end.
	 *
	 * Results are cached in a transient for an hour. When the widget is limited to a term, it
	 * lists posts with that term and links to the term's On This Day archive.
	 *
	 * @since 1.0.0
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
		$term = $this->get_widget_term( $instance );
		$link = $term ? Tempus_On_This_Day::get_term_archive_link( $term ) : Tempus_On_This_Day::get_link();
		echo $args['before_widget']; // phpcs:ignore
		if ( $title ) {
			echo wp_kses( $args['before_title'] . sprintf( '<a href="%1$s">%2$s</a>', esc_url( $link ), $title ) . $args['after_title'], Tempus_Fugit_Plugin::kses_clean() );
		}
		$posts    = $this->get_widget_posts(
			array(
				'day'        => $date->format( 'd' ),
				'monthnum'   => $date->format( 'm' ),
				'date_query' => array(
					array(
						'before' => 'yesterday',
					),
				),
			),
			$date->format( 'm-d' ),
			$term,
			$instance
		);
		$organize = array();
		foreach ( $posts as $post ) {
			$diff = sprintf( '<a href="%1$s">%2$s</a>', esc_url( tempus_get_post_day_link( $post ) ), esc_html( human_time_diff( get_post_timestamp( $post ) ) ) );
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
	 * Returns a list item linking to a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int|WP_Post $post Post ID or post object.
	 * @return string List item HTML.
	 */
	public function list_item( $post ) {
		$post = get_post( $post );
		return sprintf( '<li><a href="%2$s">%1$s</a></li>', wp_kses( $this->get_the_title( $post ), Tempus_Fugit_Plugin::kses_clean() ), esc_url( get_the_permalink( $post ) ) );
	}

	/**
	 * Returns the text to use for a post's link.
	 *
	 * @since 1.0.0
	 *
	 * @param int|WP_Post $post Post ID or post object.
	 * @return string Link text.
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
		/**
		 * Filters the text used as the title of a post in the widget.
		 *
		 * The default is the post title, then the excerpt, then the first 40 characters of the
		 * content, then the post's year and time.
		 *
		 * @since 1.0.3
		 *
		 * @param string  $title Text to display for the post.
		 * @param WP_Post $post  The post.
		 */
		$title = apply_filters( 'tempus_widget_post_title', $title, $post );
		return trim( $title );
	}

	/**
	 * Sanitizes widget settings as they are saved.
	 *
	 * @since 1.0.0
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance New settings from the form.
	 * @param array $old_instance Previously saved settings.
	 * @return array Settings to save.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance              = array();
		$instance['title']     = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['number']    = isset( $new_instance['number'] ) ? max( 1, absint( $new_instance['number'] ) ) : 5;
		$instance['nonefound'] = isset( $new_instance['nonefound'] ) ? sanitize_textarea_field( $new_instance['nonefound'] ) : '';
		$instance['taxonomy']  = '';
		$instance['term']      = '';
		if ( ! empty( $new_instance['taxonomy'] ) && array_key_exists( $new_instance['taxonomy'], self::get_taxonomy_options() ) ) {
			$instance['taxonomy'] = $new_instance['taxonomy'];
			$instance['term']     = isset( $new_instance['term'] ) ? sanitize_title( $new_instance['term'] ) : '';
		}
		return $instance;
	}

	/**
	 * Returns the taxonomies a widget can be limited to.
	 *
	 * @since 1.2.1
	 *
	 * @return string[] Taxonomy labels, keyed by taxonomy name.
	 */
	public static function get_taxonomy_options() {
		return wp_list_pluck( get_taxonomies( array( 'public' => true ), 'objects' ), 'label', 'name' );
	}


	/**
	 * Outputs the widget settings form.
	 *
	 * @since 1.0.0
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		$instance = $this->defaults( $instance );
		?>
				<p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title: ', 'tempus-fugit' ); ?></label>
				<input type="text" size="30" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				value="<?php echo esc_attr( $instance['title'] ); ?>" /></p>
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php esc_html_e( 'Number of Posts:', 'tempus-fugit' ); ?></label>
		<input type="number" min="1" step="1" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" value="<?php echo esc_attr( $instance['number'] ); ?>" />
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'nonefound' ) ); ?>"><?php esc_html_e( 'Text if No Posts Found:', 'tempus-fugit' ); ?></label>
		<textarea class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'nonefound' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'nonefound' ) ); ?>"><?php echo esc_textarea( $instance['nonefound'] ); ?></textarea>
		</p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'taxonomy' ) ); ?>"><?php esc_html_e( 'Limit to:', 'tempus-fugit' ); ?></label>
		<select name="<?php echo esc_attr( $this->get_field_name( 'taxonomy' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'taxonomy' ) ); ?>">
			<option value=""><?php esc_html_e( 'All posts', 'tempus-fugit' ); ?></option>
			<?php foreach ( self::get_taxonomy_options() as $name => $label ) : ?>
			<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $instance['taxonomy'], $name ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select></p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'term' ) ); ?>"><?php esc_html_e( 'Term (slug):', 'tempus-fugit' ); ?></label>
		<input type="text" class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'term' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'term' ) ); ?>" value="<?php echo esc_attr( $instance['term'] ); ?>" /></p>
		<?php
	}
}
