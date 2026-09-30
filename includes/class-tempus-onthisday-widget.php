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
	 * Option holding the widget cache version. Changing it invalidates every widget cache.
	 *
	 * @since 1.2.1
	 *
	 * @var string
	 */
	const CACHE_VERSION_OPTION = 'tempus_fugit_widget_cache_version';

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
	 * Registers the hooks that invalidate widget caches when published posts change.
	 *
	 * @since 1.2.1
	 */
	public static function register_cache_hooks() {
		add_action( 'transition_post_status', array( __CLASS__, 'transition_post_status' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'deleted_post' ), 10, 2 );
		add_action( 'set_object_terms', array( __CLASS__, 'set_object_terms' ) );
	}

	/**
	 * Invalidates every widget cache.
	 *
	 * Bumps a version number that is part of each cache key, so it works with or without a
	 * persistent object cache. Old entries expire on their own.
	 *
	 * @since 1.2.1
	 */
	public static function flush_cache() {
		update_option( self::CACHE_VERSION_OPTION, (int) get_option( self::CACHE_VERSION_OPTION, 0 ) + 1 );
	}

	/**
	 * Invalidates widget caches when a post is published, unpublished, or updated while published.
	 *
	 * Hooked to `transition_post_status`.
	 *
	 * @since 1.2.1
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post.
	 */
	public static function transition_post_status( $new_status, $old_status, $post ) {
		if ( 'publish' === $new_status || 'publish' === $old_status ) {
			self::flush_cache();
		}
	}

	/**
	 * Invalidates widget caches when a published post is deleted without going to the trash.
	 *
	 * Hooked to `deleted_post`.
	 *
	 * @since 1.2.1
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function deleted_post( $post_id, $post ) {
		if ( 'publish' === $post->post_status ) {
			self::flush_cache();
		}
	}

	/**
	 * Invalidates widget caches when a published post's terms change.
	 *
	 * Hooked to `set_object_terms`.
	 *
	 * @since 1.2.1
	 *
	 * @param int $object_id Object ID.
	 */
	public static function set_object_terms( $object_id ) {
		if ( 'publish' === get_post_status( $object_id ) ) {
			self::flush_cache();
		}
	}

	/**
	 * Returns posts for the widget, cached in a transient for an hour.
	 *
	 * The cache is separate for each widget, period, term, and number of posts, and is replaced
	 * whenever a published post changes (see flush_cache()).
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
		$key    = $this->get_cache_key( array( $period, $term ? $term->term_id : 0, $number ) );
		$posts  = get_transient( $key );
		if ( false === $posts ) {
			$query['numberposts'] = $number;
			$query['fields']      = 'ids';
			$posts                = get_posts( $query + $this->get_tax_query( $term ) );
			set_transient( $key, $posts, HOUR_IN_SECONDS );
		}
		return $posts;
	}

	/**
	 * Returns a transient name for cached widget data.
	 *
	 * The name includes the widget's ID and the cache version, so it changes whenever a
	 * published post changes (see flush_cache()).
	 *
	 * @since 1.2.1
	 *
	 * @param array $parts What the cached data depends on, such as the period, term, and number.
	 * @return string Transient name.
	 */
	protected function get_cache_key( $parts ) {
		return 'tempus_widget_' . md5( wp_json_encode( array_merge( array( $this->id, (int) get_option( self::CACHE_VERSION_OPTION, 0 ) ), $parts ) ) );
	}

	/**
	 * Returns query arguments that limit posts to a term.
	 *
	 * @since 1.2.1
	 *
	 * @param WP_Term|null $term Term, or null for no limit.
	 * @return array Query arguments with a `tax_query`, or an empty array.
	 */
	protected function get_tax_query( $term ) {
		if ( ! $term ) {
			return array();
		}
		return array(
			'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Limited to the widget's term.
				array(
					'taxonomy' => $term->taxonomy,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			),
		);
	}

	/**
	 * Outputs a list of posts grouped by how long ago they were published.
	 *
	 * Each group is headed with "N years ago..." linking to an archive for that post.
	 *
	 * @since 1.2.1
	 *
	 * @param int[]    $posts      Post IDs.
	 * @param string   $id         HTML ID for the container.
	 * @param string   $nonefound  Text to show when there are no posts.
	 * @param callable $group_link Returns the URL for a group heading, given a post ID.
	 */
	protected function display_posts( $posts, $id, $nonefound, $group_link ) {
		$organize = array();
		foreach ( $posts as $post ) {
			$diff = sprintf( '<a href="%1$s">%2$s</a>', esc_url( call_user_func( $group_link, $post ) ), esc_html( human_time_diff( get_post_timestamp( $post ) ) ) );
			if ( ! array_key_exists( $diff, $organize ) ) {
				$organize[ $diff ] = array();
			}
			$organize[ $diff ][] = $this->list_item( $post );
		}

		echo '<div id="' . esc_attr( $id ) . '">';
		if ( ! empty( $organize ) ) {
			echo '<ul>';
			foreach ( $organize as $title => $group ) {
				echo '<li>';
				/* translators: %s: Human-readable time difference. */
				printf( esc_html( __( '%s ago...', 'tempus-fugit' ) ), wp_kses( $title, Tempus_Fugit_Plugin::kses_clean() ) );
				echo '<ul>';
				echo wp_kses( implode( '', $group ), Tempus_Fugit_Plugin::kses_clean() );
				echo '</ul></li>';
			}
			echo '</ul>';
		} else {
			echo esc_html( $nonefound );
		}
		echo '</div>';
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
		$posts = $this->get_widget_posts(
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
		$this->display_posts( $posts, 'tempus-onthisday', $instance['nonefound'], 'tempus_get_post_day_link' );
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
