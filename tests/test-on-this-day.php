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
		$week = (int) $this->today->format( 'W' );
		if ( 1 === $week || $week >= 52 ) {
			// Near a year boundary, ISO weeks and MySQL WEEK() numbering can differ.
			$this->markTestSkipped( 'Week numbering differs at year boundaries.' );
		}
		$date = $this->today->setISODate( (int) $this->today->format( 'o' ) - 4, $week, 3 )->setTime( 12, 0 );
		$past = self::factory()->post->create( array( 'post_date' => $date->format( 'Y-m-d H:i:s' ) ) );

		$this->go_to( home_url( '/thisweek/' ) );
		$this->assertTrue( is_archive() );
		$this->assertTrue( Tempus_This_Week::is_thisweek() );
		$this->assertContains( $past, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
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
