<?php
/**
 * Tests for the Random Memory widget and shortcode.
 *
 * @package TempusFugit
 */

/**
 * Random memory tests.
 */
class Test_Tempus_Random_Memory extends WP_UnitTestCase {

	/**
	 * Today's date in the site timezone.
	 *
	 * @var DateTimeImmutable
	 */
	private $today;

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		create_initial_taxonomies();
		$this->today = new DateTimeImmutable( 'now', wp_timezone() );
	}

	/**
	 * Creates a post.
	 *
	 * @param string            $title Post title.
	 * @param DateTimeImmutable $date  Post date.
	 * @param array             $args  Optional. More post fields.
	 * @return int Post ID.
	 */
	private function create_post( $title, $date, $args = array() ) {
		return self::factory()->post->create(
			$args + array(
				'post_title' => $title,
				'post_date'  => $date->format( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Returns midday on the Wednesday of this ISO week number, at least a number of years ago.
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
	 * Renders the widget and returns its output.
	 *
	 * @param array $instance Widget settings.
	 * @return string Widget HTML.
	 */
	private function render( $instance ) {
		$widget = new Tempus_Random_Widget();
		$widget->_set( 1 );
		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '<h2>',
				'after_title'   => '</h2>',
			),
			$instance
		);
		return ob_get_clean();
	}

	public function test_widget_is_registered() {
		$this->assertArrayHasKey( 'Tempus_Random_Widget', $GLOBALS['wp_widget_factory']->widgets );
	}

	public function test_shows_one_random_post_by_default() {
		foreach ( array( 'Alpha', 'Bravo', 'Charlie' ) as $i => $title ) {
			$this->create_post( $title, new DateTimeImmutable( ( 2015 + $i ) . '-05-10 12:00:00', wp_timezone() ) );
		}
		$output = $this->render( array() );
		$this->assertSame( 1, preg_match_all( '/>(Alpha|Bravo|Charlie)</', $output ) );
		$this->assertStringContainsString( '<div id="tempus-random">', $output );
	}

	public function test_picks_a_new_post_on_each_view() {
		foreach ( array( 'Alpha', 'Bravo', 'Charlie' ) as $i => $title ) {
			$this->create_post( $title, new DateTimeImmutable( ( 2015 + $i ) . '-05-10 12:00:00', wp_timezone() ) );
		}
		$seen = array();
		for ( $i = 0; $i < 60; $i++ ) {
			preg_match( '/>(Alpha|Bravo|Charlie)</', $this->render( array() ), $match );
			$seen[ $match[1] ] = true;
		}
		// The chance of missing one of three posts in 60 picks is about 3 × (2/3)^60, under 1 in 10^10.
		$this->assertCount( 3, $seen );
	}

	public function test_number_of_posts() {
		foreach ( array( 'Alpha', 'Bravo', 'Charlie' ) as $i => $title ) {
			$this->create_post( $title, new DateTimeImmutable( ( 2015 + $i ) . '-05-10 12:00:00', wp_timezone() ) );
		}
		$this->assertSame( 2, preg_match_all( '/>(Alpha|Bravo|Charlie)</', $this->render( array( 'number' => 2 ) ) ) );
		// Asking for more posts than exist shows each post once.
		$this->assertSame( 3, preg_match_all( '/>(Alpha|Bravo|Charlie)</', $this->render( array( 'number' => 10 ) ) ) );
	}

	public function test_does_not_sort_the_table_randomly() {
		$this->create_post( 'Alpha', new DateTimeImmutable( '2015-05-10 12:00:00', wp_timezone() ) );
		$queries = array();
		$logger  = function ( $sql ) use ( &$queries ) {
			$queries[] = $sql;
			return $sql;
		};
		add_filter( 'query', $logger );
		$this->render( array() );
		remove_filter( 'query', $logger );
		$this->assertNotEmpty( $queries );
		foreach ( $queries as $sql ) {
			$this->assertStringNotContainsStringIgnoringCase( 'RAND(', $sql );
		}
	}

	public function test_this_day_in_previous_years() {
		$this->create_post( 'Past day', $this->today->modify( '-4 years' )->setTime( 12, 0 ) );
		$this->create_post( 'This year', $this->today->modify( '-1 minute' ) );
		$this->create_post( 'Other day', $this->today->modify( '-4 years -3 days' )->setTime( 12, 0 ) );
		$output = $this->render(
			array(
				'period' => 'day',
				'number' => 5,
			)
		);
		$this->assertStringContainsString( 'Past day', $output );
		$this->assertStringNotContainsString( 'This year', $output );
		$this->assertStringNotContainsString( 'Other day', $output );
	}

	public function test_this_week_in_previous_years() {
		$this->create_post( 'Past week', $this->same_week_years_ago( 4 ) );
		$this->create_post( 'This year', $this->today->modify( '-1 minute' ) );
		$this->create_post( 'Other week', $this->same_week_years_ago( 4 )->modify( '+14 days' ) );
		$output = $this->render(
			array(
				'period' => 'week',
				'number' => 5,
			)
		);
		$this->assertStringContainsString( 'Past week', $output );
		$this->assertStringNotContainsString( 'This year', $output );
		$this->assertStringNotContainsString( 'Other week', $output );
	}

	public function test_limited_to_a_term() {
		$date = new DateTimeImmutable( '2015-05-10 12:00:00', wp_timezone() );
		$this->create_post( 'Tagged', $date, array( 'tags_input' => array( 'foo' ) ) );
		$this->create_post( 'Untagged', $date );
		$output = $this->render(
			array(
				'number'   => 5,
				'taxonomy' => 'post_tag',
				'term'     => 'foo',
			)
		);
		$this->assertStringContainsString( 'Tagged', $output );
		$this->assertStringNotContainsString( 'Untagged', $output );
	}

	public function test_no_posts() {
		$this->assertStringContainsString( 'There are no posts to remember yet', $this->render( array() ) );
		$this->create_post( 'Alpha', new DateTimeImmutable( '2015-05-10 12:00:00', wp_timezone() ) );
		$this->assertStringContainsString(
			'Nothing here',
			$this->render(
				array(
					'taxonomy'  => 'post_tag',
					'term'      => 'deleted',
					'nonefound' => 'Nothing here',
				)
			)
		);
	}

	public function test_new_posts_are_included_right_away() {
		$this->assertStringContainsString( 'There are no posts to remember yet', $this->render( array() ) );
		$this->create_post( 'Alpha', new DateTimeImmutable( '2015-05-10 12:00:00', wp_timezone() ) );
		$this->assertStringContainsString( 'Alpha', $this->render( array() ) );
	}

	/**
	 * @dataProvider data_title_links
	 *
	 * @param string $period Period.
	 * @param bool   $term   Whether the widget is limited to a term.
	 * @param string $path   Expected link path.
	 */
	public function test_title_links_to_the_archive( $period, $term, $path ) {
		$this->create_post( 'Alpha', new DateTimeImmutable( '2015-05-10 12:00:00', wp_timezone() ), array( 'tags_input' => array( 'foo' ) ) );
		$instance = array(
			'title'  => 'Memory',
			'period' => $period,
		);
		if ( $term ) {
			$instance += array(
				'taxonomy' => 'post_tag',
				'term'     => 'foo',
			);
		}
		$this->assertStringContainsString( '<h2><a href="' . home_url( $path ) . '">Memory</a></h2>', $this->render( $instance ) );
	}

	public function data_title_links() {
		return array(
			'all time'           => array( 'all', false, '/random/' ),
			'this day'           => array( 'day', false, 'onthisday' ),
			'this week'          => array( 'week', false, 'thisweek' ),
			'all time with term' => array( 'all', true, '/tag/foo/' ),
			'this day with term' => array( 'day', true, '/tag/foo/onthisday/' ),
			'unknown period'     => array( 'someday', false, '/random/' ),
		);
	}

	public function test_update_sanitizes_settings() {
		$widget = new Tempus_Random_Widget();
		$this->assertSame(
			array(
				'title'     => '',
				'number'    => 1,
				'nonefound' => '',
				'taxonomy'  => '',
				'term'      => '',
				'period'    => 'all',
			),
			$widget->update( array( 'period' => 'someday' ), array() )
		);
		$instance = $widget->update(
			array(
				'period' => 'week',
				'number' => '3',
			),
			array()
		);
		$this->assertSame( 'week', $instance['period'] );
		$this->assertSame( 3, $instance['number'] );
	}

	public function test_form_includes_period() {
		$widget = new Tempus_Random_Widget();
		$widget->_set( 4 );
		ob_start();
		$widget->form( array( 'period' => 'day' ) );
		$form = ob_get_clean();
		$this->assertStringContainsString( 'name="widget-tempus_random_widget[4][period]"', $form );
		$this->assertMatchesRegularExpression( '/<option value="day"\s+selected(=\'selected\')?>This day in previous years</', $form );
		$this->assertStringContainsString( 'name="widget-tempus_random_widget[4][taxonomy]"', $form );
	}

	public function test_shortcode() {
		$this->create_post( 'Past day', $this->today->modify( '-4 years' )->setTime( 12, 0 ) );
		$this->create_post( 'Other day', $this->today->modify( '-4 years -3 days' )->setTime( 12, 0 ) );
		$output = do_shortcode( '[tempus_random period="day" title="A memory"]' );
		$this->assertStringStartsWith( '<div class="tempus-shortcode tempus_random">', $output );
		$this->assertStringContainsString( '<a href="' . Tempus_On_This_Day::get_link() . '">A memory</a>', $output );
		$this->assertStringContainsString( 'Past day', $output );
		$this->assertStringNotContainsString( 'Other day', $output );
		$this->assertTrue( shortcode_exists( 'tempus_random' ) );
	}

	/**
	 * @dataProvider data_sorted_archive_links
	 *
	 * @param string $structure Permalink structure.
	 * @param string $sort      Sort type.
	 * @param string $expected  Expected URL, relative to the site.
	 */
	public function test_sorted_archive_links( $structure, $sort, $expected ) {
		$this->set_permalink_structure( $structure );
		$this->assertSame( $expected ? home_url( $expected ) : '', Tempus_Order_By::get_link( $sort ) );
	}

	public function data_sorted_archive_links() {
		return array(
			'pretty'  => array( '/%year%/%postname%/', 'random', '/random/' ),
			'index'   => array( '/index.php/%year%/%postname%/', 'updated', '/index.php/updated/' ),
			'plain'   => array( '', 'oldest', '/?tempus_sort=oldest' ),
			'unknown' => array( '/%year%/%postname%/', 'newest', '' ),
		);
	}
}
