<?php
/**
 * Random Memory widget.
 *
 * @package TempusFugit
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Widget showing a random post, from all time, from this day in previous years, or from this
 * week in previous years, optionally limited to a term.
 *
 * A new post is picked on every page view. Only the number of matching posts is cached, so there
 * is no `ORDER BY RAND()` over the posts table.
 *
 * @since 1.3.0
 */
class Tempus_Random_Widget extends Tempus_OnThisDay_Widget {
	/**
	 * Sets up the widget name and description.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {
		WP_Widget::__construct(
			'Tempus_Random_Widget', // Base ID.
			__( 'Random Memory Widget', 'tempus-fugit' ), // Name.
			array(
				'classname'   => 'random_memory_widget',
				'description' => __( 'A widget that shows a random post from your archives', 'tempus-fugit' ),
			)
		);
	}

	/**
	 * Returns the periods a random memory can be picked from.
	 *
	 * @since 1.3.0
	 *
	 * @return string[] Period labels, keyed by period.
	 */
	public static function get_periods() {
		return array(
			'all'  => __( 'All time', 'tempus-fugit' ),
			'day'  => __( 'This day in previous years', 'tempus-fugit' ),
			'week' => __( 'This week in previous years', 'tempus-fugit' ),
		);
	}

	/**
	 * Fills in default settings.
	 *
	 * @since 1.3.0
	 *
	 * @param array $instance Widget settings.
	 * @return array Widget settings with defaults for 'title', 'number', 'nonefound', 'taxonomy',
	 *               'term', and 'period'.
	 */
	public function defaults( $instance ) {
		$defaults = array(
			'title'     => '',
			'number'    => 1,
			'nonefound' => __( 'There are no posts to remember yet', 'tempus-fugit' ),
			'taxonomy'  => '',
			'term'      => '',
			'period'    => 'all',
		);
		return wp_parse_args( $instance, $defaults );
	}

	/**
	 * Outputs the widget on the front end.
	 *
	 * The title links to the archive for the period: `/random/`, On This Day, or This Week, or
	 * the term's archive when the widget is limited to a term.
	 *
	 * @since 1.3.0
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
		$title  = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
		$period = array_key_exists( $instance['period'], self::get_periods() ) ? $instance['period'] : 'all';
		$term   = $this->get_widget_term( $instance );

		echo $args['before_widget']; // phpcs:ignore
		if ( $title ) {
			echo wp_kses( $args['before_title'] . sprintf( '<a href="%1$s">%2$s</a>', esc_url( self::get_period_link( $period, $term ) ), $title ) . $args['after_title'], Tempus_Fugit_Plugin::kses_clean() );
		}
		$this->display_posts( $this->get_random_posts( $period, $term, $instance ), 'tempus-random', $instance['nonefound'], 'tempus_get_post_day_link' );
		echo $args['after_widget']; // phpcs:ignore
	}

	/**
	 * Returns the archive URL for a period.
	 *
	 * @since 1.3.0
	 *
	 * @param string             $period 'all', 'day', or 'week'.
	 * @param WP_Term|false|null $term   Term from get_widget_term().
	 * @return string Archive URL.
	 */
	public static function get_period_link( $period, $term ) {
		if ( 'day' === $period ) {
			return $term ? Tempus_On_This_Day::get_term_archive_link( $term ) : Tempus_On_This_Day::get_link();
		}
		if ( 'week' === $period ) {
			return $term ? Tempus_This_Week::get_term_archive_link( $term ) : Tempus_This_Week::get_link();
		}
		return $term ? get_term_link( $term ) : Tempus_Order_By::get_link( 'random' );
	}

	/**
	 * Returns query arguments for the posts in a period.
	 *
	 * "This day" and "this week" only include previous years, like the On This Day and This Week
	 * archives.
	 *
	 * @since 1.3.0
	 *
	 * @param string $period 'all', 'day', or 'week'.
	 * @return array Query arguments.
	 */
	public static function get_period_query( $period ) {
		$now = new DateTimeImmutable( 'now', wp_timezone() );
		if ( 'day' === $period ) {
			return array(
				'monthnum'   => (int) $now->format( 'n' ),
				'day'        => (int) $now->format( 'j' ),
				'date_query' => array(
					array(
						'before' => $now->setDate( (int) $now->format( 'Y' ), 1, 1 )->setTime( 0, 0 )->format( 'Y-m-d H:i:s' ),
					),
				),
			);
		}
		if ( 'week' === $period ) {
			return array(
				// Matched as an ISO-8601 week by Tempus_This_Week::posts_where().
				'tempus_iso_week'  => (int) $now->format( 'W' ),
				'suppress_filters' => false,
				'date_query'       => array(
					array(
						'before' => Tempus_Week_Of_Year::get_week_start( (int) $now->format( 'o' ), 1 )->format( 'Y-m-d H:i:s' ),
					),
				),
			);
		}
		return array();
	}

	/**
	 * Returns random posts from a period.
	 *
	 * The number of matching posts is cached like other widget results. Each call then picks
	 * random positions and fetches the posts at those positions.
	 *
	 * @since 1.3.0
	 *
	 * @param string             $period   'all', 'day', or 'week'.
	 * @param WP_Term|false|null $term     Term from get_widget_term().
	 * @param array              $instance Widget settings.
	 * @return int[] Post IDs.
	 */
	protected function get_random_posts( $period, $term, $instance ) {
		if ( false === $term ) {
			return array();
		}
		$query = self::get_period_query( $period ) + $this->get_tax_query( $term );

		// The day or week is part of the key, so "this day" and "this week" counts roll over.
		$now   = new DateTimeImmutable( 'now', wp_timezone() );
		$key   = $this->get_cache_key( array( 'random-count', $period, $now->format( 'm-d o-W' ), $term ? $term->term_id : 0 ) );
		$count = get_transient( $key );
		if ( false === $count ) {
			$counter = new WP_Query(
				$query + array(
					'posts_per_page'      => 1,
					'fields'              => 'ids',
					'ignore_sticky_posts' => true,
				)
			);
			$count   = (int) $counter->found_posts;
			set_transient( $key, $count, HOUR_IN_SECONDS );
		}

		// Pick distinct random positions.
		$offsets = array();
		$needed  = min( max( 1, absint( $instance['number'] ) ), $count );
		while ( $needed > 0 ) {
			$offset = wp_rand( 0, $count - 1 );
			if ( ! isset( $offsets[ $offset ] ) ) {
				$offsets[ $offset ] = true;
				--$needed;
			}
		}

		$posts = array();
		foreach ( array_keys( $offsets ) as $offset ) {
			$posts = array_merge(
				$posts,
				get_posts(
					$query + array(
						'numberposts' => 1,
						'offset'      => $offset,
						'orderby'     => 'ID',
						'order'       => 'ASC',
						'fields'      => 'ids',
					)
				)
			);
		}
		return $posts;
	}

	/**
	 * Sanitizes widget settings as they are saved.
	 *
	 * @since 1.3.0
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance New settings from the form.
	 * @param array $old_instance Previously saved settings.
	 * @return array Settings to save.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance           = parent::update( $new_instance, $old_instance );
		$instance['number'] = isset( $new_instance['number'] ) ? $instance['number'] : 1;
		$instance['period'] = ( isset( $new_instance['period'] ) && array_key_exists( $new_instance['period'], self::get_periods() ) ) ? $new_instance['period'] : 'all';
		return $instance;
	}

	/**
	 * Outputs the widget settings form.
	 *
	 * @since 1.3.0
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		$instance = $this->defaults( $instance );
		parent::form( $instance );
		?>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'period' ) ); ?>"><?php esc_html_e( 'Pick from:', 'tempus-fugit' ); ?></label>
		<select name="<?php echo esc_attr( $this->get_field_name( 'period' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'period' ) ); ?>">
			<?php foreach ( self::get_periods() as $value => $label ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $instance['period'], $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select></p>
		<?php
	}
}
