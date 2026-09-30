<?php
/**
 * Tests for the On This Day and This Week widgets.
 *
 * @package TempusFugit
 */

/**
 * Widget tests.
 */
class Test_Tempus_Widgets extends WP_UnitTestCase {

	/**
	 * Today's date in the site timezone.
	 *
	 * @var DateTimeImmutable
	 */
	private $today;

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		$this->today = new DateTimeImmutable( 'now', wp_timezone() );
	}

	/**
	 * Returns midday on the Wednesday of this ISO week number, at least a number of years ago.
	 *
	 * Goes further back if needed to find a year that has this week (week 53 is not in every year).
	 *
	 * @param int $years Minimum number of years ago.
	 * @return DateTimeImmutable Date in the same ISO week number.
	 */
	private function same_week_years_ago( $years ) {
		$week = (int) $this->today->format( 'W' );
		$year = (int) $this->today->format( 'o' ) - $years;
		while ( (int) $this->today->setISODate( $year, $week, 3 )->format( 'W' ) !== $week ) {
			--$year;
		}
		return $this->today->setISODate( $year, $week, 3 )->setTime( 12, 0 );
	}

	/**
	 * Renders a widget and returns its output.
	 *
	 * @param WP_Widget $widget   Widget.
	 * @param array     $instance Widget settings.
	 * @return string Widget HTML.
	 */
	private function render( $widget, $instance ) {
		ob_start();
		$widget->widget(
			array(
				'before_widget' => '<section>',
				'after_widget'  => '</section>',
				'before_title'  => '<h2>',
				'after_title'   => '</h2>',
			),
			$instance
		);
		return ob_get_clean();
	}

	public function test_onthisday_widget_without_posts() {
		$widget = new Tempus_OnThisDay_Widget();
		$widget->_set( 1 );
		$output = $this->render( $widget, array() );
		$this->assertStringNotContainsString( '<h2>', $output );
		$this->assertStringContainsString( '<div id="tempus-onthisday">There were no posts on this day in previous years</div>', $output );

		$output = $this->render( $widget, array( 'nonefound' => 'Nothing yet' ) );
		$this->assertStringContainsString( 'Nothing yet', $output );
	}

	public function test_onthisday_widget_groups_posts_by_years_ago() {
		$date = $this->today->modify( '-4 years' )->setTime( 12, 0 );
		$post = self::factory()->post->create(
			array(
				'post_title' => 'Four years back',
				'post_date'  => $date->format( 'Y-m-d H:i:s' ),
			)
		);
		$widget = new Tempus_OnThisDay_Widget();
		$widget->_set( 1 );
		$output = $this->render( $widget, array( 'title' => 'Memories' ) );

		$this->assertStringContainsString( '<h2><a href="' . Tempus_On_This_Day::get_link() . '">Memories</a></h2>', $output );
		$this->assertStringContainsString( '<a href="' . tempus_get_post_day_link( $post ) . '">4 years</a> ago...', $output );
		$this->assertStringContainsString( '<li><a href="' . get_permalink( $post ) . '">Four years back</a></li>', $output );

		// A second render is served from the transient.
		$this->assertSame( $output, $this->render( $widget, array( 'title' => 'Memories' ) ) );
	}

	public function test_thisweek_widget() {
		$date = $this->same_week_years_ago( 4 );
		$post = self::factory()->post->create(
			array(
				'post_title' => 'Same week',
				'post_date'  => $date->format( 'Y-m-d H:i:s' ),
			)
		);
		self::factory()->post->create( array( 'post_title' => 'This year' ) );

		$widget = new Tempus_ThisWeek_Widget();
		$widget->_set( 1 );
		$output = $this->render( $widget, array( 'title' => 'Weeks' ) );

		$this->assertStringContainsString( '<h2><a href="' . Tempus_This_Week::get_link() . '">Weeks</a></h2>', $output );
		$this->assertStringContainsString( '<div id="tempus-thisweek">', $output );
		$this->assertStringContainsString( '<a href="' . tempus_get_post_week_link( $post ) . '">', $output );
		$this->assertStringContainsString( 'Same week', $output );
		$this->assertStringNotContainsString( 'This year', $output );
	}

	public function test_thisweek_widget_without_posts() {
		$widget = new Tempus_ThisWeek_Widget();
		$widget->_set( 1 );
		$output = $this->render( $widget, array() );
		$this->assertStringContainsString( '<div id="tempus-thisweek">There were no posts on this week in previous years</div>', $output );
	}

	/**
	 * @dataProvider data_post_titles
	 *
	 * @param array  $post     Post fields.
	 * @param string $expected Expected link text.
	 */
	public function test_widget_post_title_fallbacks( $post, $expected ) {
		// Allow a post with no title, excerpt, or content, as Post Kinds does for some kinds.
		add_filter( 'wp_insert_post_empty_content', '__return_false' );
		$post   = self::factory()->post->create( $post + array( 'post_date' => '2020-06-15 14:30:00' ) );
		$widget = new Tempus_OnThisDay_Widget();
		$this->assertSame( $expected, $widget->get_the_title( $post ) );
	}

	public function data_post_titles() {
		return array(
			'title'   => array(
				array( 'post_title' => ' A title ' ),
				'A title',
			),
			'excerpt' => array(
				array(
					'post_title'   => '',
					'post_excerpt' => 'An excerpt',
				),
				'An excerpt',
			),
			'content' => array(
				array(
					'post_title'   => '',
					'post_excerpt' => '',
					'post_content' => '<p>Content that is quite a bit longer than forty characters.</p>',
				),
				'Content that is quite a bit longer th...',
			),
			'date'    => array(
				array(
					'post_title'   => '',
					'post_excerpt' => '',
					'post_content' => '',
				),
				'2020 2:30 pm',
			),
		);
	}

	public function test_widget_post_title_filter() {
		$post = self::factory()->post->create( array( 'post_title' => 'Original' ) );
		add_filter(
			'tempus_widget_post_title',
			function ( $title, $filtered_post ) use ( $post ) {
				return $filtered_post->ID === $post ? 'Filtered' : $title;
			},
			10,
			2
		);
		$widget = new Tempus_OnThisDay_Widget();
		$this->assertSame( 'Filtered', $widget->get_the_title( $post ) );
	}

	public function test_widget_update_uses_defaults_for_missing_fields() {
		$widget = new Tempus_OnThisDay_Widget();
		$this->assertSame(
			array(
				'title'     => '',
				'number'    => 5,
				'nonefound' => '',
				'taxonomy'  => '',
				'term'      => '',
			),
			$widget->update( array(), array() )
		);
	}
}
