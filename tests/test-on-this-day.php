<?php
/**
 * Tests for On This Day and This Week archives and widgets.
 *
 * @package TempusFugit
 */

/**
 * On This Day and This Week tests.
 */
class Test_Tempus_On_This_Day extends WP_UnitTestCase {

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
	 * Creates a post on today's month and day, a number of years ago.
	 *
	 * @param int $years_ago Years before this year. 0 creates a post dated now.
	 * @return int Post ID.
	 */
	private function create_post_years_ago( $years_ago ) {
		$date = $years_ago ? $this->today->modify( "-{$years_ago} years" )->setTime( 12, 0 ) : $this->today->modify( '-1 minute' );
		return self::factory()->post->create(
			array(
				'post_title' => "Post from {$years_ago} years ago",
				'post_date'  => $date->format( 'Y-m-d H:i:s' ),
			)
		);
	}

	public function test_onthisday_archive_lists_previous_years_only() {
		$past    = $this->create_post_years_ago( 4 );
		$current = $this->create_post_years_ago( 0 );

		$this->go_to( home_url( '/onthisday/' ) );
		$this->assertTrue( is_archive() );
		$this->assertTrue( Tempus_On_This_Day::is_onthisday() );
		$ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		$this->assertContains( $past, $ids );
		$this->assertNotContains( $current, $ids );
	}

	public function test_onthisday_specific_date() {
		$match = self::factory()->post->create( array( 'post_date' => '2019-03-15 12:00:00' ) );
		self::factory()->post->create( array( 'post_date' => '2019-03-16 12:00:00' ) );

		$this->go_to( home_url( '/onthisday/03/15/' ) );
		$this->assertSame( array( $match ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	public function test_thisweek_archive_lists_previous_years() {
		$date = $this->same_week_years_ago( 4 );
		$past = self::factory()->post->create( array( 'post_date' => $date->format( 'Y-m-d H:i:s' ) ) );

		$this->go_to( home_url( '/thisweek/' ) );
		$this->assertTrue( is_archive() );
		$this->assertTrue( Tempus_This_Week::is_thisweek() );
		$this->assertContains( $past, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	/**
	 * @dataProvider data_start_of_week
	 *
	 * @param int $start_of_week The site's "Week starts on" setting.
	 */
	public function test_thisweek_specific_week_uses_iso_weeks( $start_of_week ) {
		update_option( 'start_of_week', $start_of_week );
		$in  = array(
			self::factory()->post->create( array( 'post_date' => '2024-12-30 12:00:00' ) ), // Monday of 2025-W01.
			self::factory()->post->create( array( 'post_date' => '2021-01-04 12:00:00' ) ), // Monday of 2021-W01.
			self::factory()->post->create( array( 'post_date' => '2021-01-10 12:00:00' ) ), // Sunday of 2021-W01.
		);
		$out = array(
			self::factory()->post->create( array( 'post_date' => '2024-12-29 12:00:00' ) ), // Sunday of 2024-W52.
			self::factory()->post->create( array( 'post_date' => '2021-01-03 12:00:00' ) ), // Sunday of 2020-W53.
			self::factory()->post->create( array( 'post_date' => '2021-01-11 12:00:00' ) ), // Monday of 2021-W02.
		);

		$this->go_to( home_url( '/thisweek/01/' ) );
		$this->assertTrue( Tempus_This_Week::is_thisweek() );
		$ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		$this->assertEqualsCanonicalizing( $in, $ids );
		foreach ( $out as $post ) {
			$this->assertNotContains( $post, $ids );
		}
	}

	public function data_start_of_week() {
		return array(
			'weeks start on Monday' => array( 1 ),
			'weeks start on Sunday' => array( 0 ),
		);
	}

	public function test_thisweek_week_53() {
		$posts = array(
			self::factory()->post->create( array( 'post_date' => '2021-01-01 12:00:00' ) ), // Friday of 2020-W53.
			self::factory()->post->create( array( 'post_date' => '2015-12-31 12:00:00' ) ), // Thursday of 2015-W53.
		);
		self::factory()->post->create( array( 'post_date' => '2021-01-04 12:00:00' ) ); // Monday of 2021-W01.
		$this->go_to( home_url( '/thisweek/53/' ) );
		$this->assertEqualsCanonicalizing( $posts, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	public function test_core_week_queries_are_not_this_week() {
		self::factory()->post->create( array( 'post_date' => '2019-03-15 12:00:00' ) );
		$this->go_to( add_query_arg( 'w', 11, home_url( '/' ) ) );
		$this->assertTrue( is_date() );
		$this->assertFalse( Tempus_This_Week::is_thisweek() );
	}

	public function test_onthisday_specific_date_lists_previous_years_only() {
		$year     = (int) $this->today->format( 'Y' );
		$current  = self::factory()->post->create( array( 'post_date' => $year . '-01-01 00:00:01' ) );
		$previous = self::factory()->post->create( array( 'post_date' => ( $year - 3 ) . '-01-01 12:00:00' ) );

		$this->go_to( home_url( '/onthisday/01/01/' ) );
		$this->assertSame( array( $previous ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
		$this->assertNotContains( $current, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	public function test_thisweek_specific_week_lists_previous_years_only() {
		$year     = (int) $this->today->format( 'o' );
		$current  = self::factory()->post->create( array( 'post_date' => $this->today->setISODate( $year, 1, 1 )->setTime( 0, 0, 1 )->format( 'Y-m-d H:i:s' ) ) );
		$previous = self::factory()->post->create( array( 'post_date' => $this->today->setISODate( $year - 3, 1, 3 )->setTime( 12, 0 )->format( 'Y-m-d H:i:s' ) ) );

		$this->go_to( home_url( '/thisweek/01/' ) );
		$this->assertSame( array( $previous ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
		$this->assertNotContains( $current, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	public function test_onthisday_widget_shows_previous_years_only() {
		$this->create_post_years_ago( 4 );
		$this->create_post_years_ago( 0 );

		$widget = new Tempus_OnThisDay_Widget();
		$widget->_set( 1 );
		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			),
			array(
				'title'  => 'On This Day',
				'number' => 5,
			)
		);
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Post from 4 years ago', $output );
		$this->assertStringNotContainsString( 'Post from 0 years ago', $output );
	}

	public function test_widget_update_sanitizes_settings() {
		$widget   = new Tempus_OnThisDay_Widget();
		$instance = $widget->update(
			array(
				'title'     => '<script>alert(1)</script>Hello <b>World</b>',
				'number'    => '-3abc',
				'nonefound' => "Line one\n<i>none</i>",
				'unknown'   => 'dropped',
			),
			array()
		);

		$this->assertSame(
			array(
				'title'     => 'Hello World',
				'number'    => 3,
				'nonefound' => "Line one\nnone",
				'taxonomy'  => '',
				'term'      => '',
			),
			$instance
		);
	}

	public function test_widget_form_renders() {
		$widget = new Tempus_ThisWeek_Widget();
		$widget->_set( 2 );
		ob_start();
		$widget->form( array() );
		$form = ob_get_clean();
		$this->assertStringContainsString( 'name="widget-tempus_thisweek_widget[2][title]" id="widget-tempus_thisweek_widget-2-title"', $form );
	}
}
