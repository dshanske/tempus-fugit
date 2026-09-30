<?php
/**
 * Tests for the %dayofyear% and %week% permalink tags and their archives.
 *
 * @package TempusFugit
 */

/**
 * Permalink tag tests.
 */
class Test_Tempus_Permalinks extends WP_UnitTestCase {

	/**
	 * The day of the year is ordinal: January 1 is 001 and December 31 of a leap year is 366.
	 *
	 * @dataProvider data_dayofyear
	 *
	 * @param string $date     Post date.
	 * @param string $expected Expected day-of-year segment.
	 */
	public function test_dayofyear_permalink_is_ordinal( $date, $expected ) {
		$this->set_permalink_structure( '/%year%/%dayofyear%/%postname%/' );
		$post = self::factory()->post->create( array( 'post_date' => $date ) );
		$this->assertStringContainsString( '/' . substr( $date, 0, 4 ) . '/' . $expected . '/', get_permalink( $post ) );
	}

	public function data_dayofyear() {
		return array(
			'January 1'           => array( '2024-01-01 12:00:00', '001' ),
			'March 15, leap year' => array( '2024-03-15 12:00:00', '075' ),
			'December 31, leap'   => array( '2024-12-31 12:00:00', '366' ),
			'December 31'         => array( '2023-12-31 12:00:00', '365' ),
		);
	}

	public function test_dayofyear_archive_matches_permalink() {
		$this->set_permalink_structure( '/%year%/%dayofyear%/%postname%/' );
		$post  = self::factory()->post->create( array( 'post_date' => '2024-03-15 12:00:00' ) );
		$other = self::factory()->post->create( array( 'post_date' => '2024-03-14 12:00:00' ) );

		$this->go_to( home_url( '/2024/075/' ) );
		$this->assertTrue( Tempus_Day_Of_Year::is_dayofyear() );
		$this->assertSame( array( $post ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );

		$this->go_to( home_url( '/2024/074/' ) );
		$this->assertSame( array( $other ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	public function test_week_permalink_and_archive() {
		$this->set_permalink_structure( '/%year%/W%week%/%postname%/' );
		$post = self::factory()->post->create( array( 'post_date' => '2024-03-15 12:00:00' ) );
		$this->assertStringContainsString( '/2024/W11/', get_permalink( $post ) );

		$this->go_to( home_url( '/2024/W11/' ) );
		$this->assertTrue( Tempus_Week_Of_Year::is_week() );
		$this->assertSame( '11', get_query_var( 'tempus_week' ) );
		$this->assertContains( $post, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	/**
	 * @dataProvider data_iso_weeks
	 *
	 * @param string $date Post date.
	 * @param string $path Expected week archive path.
	 */
	public function test_week_permalink_uses_iso_year( $date, $path ) {
		$this->set_permalink_structure( '/%year%/W%week%/%postname%/' );
		$post = self::factory()->post->create( array( 'post_date' => $date ) );
		$this->assertStringContainsString( $path, get_permalink( $post ) );
		$this->assertSame( home_url( $path ), tempus_get_post_week_link( $post ) );

		// The week archive for the link lists the post.
		$this->go_to( home_url( $path ) );
		$this->assertContains( $post, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	public function data_iso_weeks() {
		return array(
			'mid-year'                     => array( '2024-03-15 12:00:00', '/2024/W11/' ),
			'December in next year W01'    => array( '2024-12-30 12:00:00', '/2025/W01/' ),
			'January in previous year W53' => array( '2021-01-03 12:00:00', '/2020/W53/' ),
			'December in week 53'          => array( '2020-12-31 12:00:00', '/2020/W53/' ),
		);
	}

	/**
	 * @dataProvider data_start_of_week
	 *
	 * @param int $start_of_week The site's "Week starts on" setting.
	 */
	public function test_week_archive_covers_monday_to_sunday( $start_of_week ) {
		update_option( 'start_of_week', $start_of_week );
		$this->set_permalink_structure( '/%year%/W%week%/%postname%/' );
		self::factory()->post->create( array( 'post_date' => '2024-12-29 23:59:59' ) );
		$monday = self::factory()->post->create( array( 'post_date' => '2024-12-30 00:00:00' ) );
		$sunday = self::factory()->post->create( array( 'post_date' => '2025-01-05 23:59:59' ) );
		self::factory()->post->create( array( 'post_date' => '2025-01-06 00:00:00' ) );

		$this->go_to( home_url( '/2025/W01/' ) );
		$this->assertTrue( Tempus_Week_Of_Year::is_week() );
		$this->assertEqualsCanonicalizing( array( $monday, $sunday ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
		$this->assertSame( 'Week <span>01, 2025</span>', get_the_archive_title() );
	}

	public function data_start_of_week() {
		return array(
			'weeks start on Monday' => array( 1 ),
			'weeks start on Sunday' => array( 0 ),
		);
	}

	/**
	 * @dataProvider data_invalid_weeks
	 *
	 * @param string $path Week archive path.
	 */
	public function test_weeks_outside_the_year_are_empty( $path ) {
		$this->set_permalink_structure( '/%year%/W%week%/%postname%/' );
		self::factory()->post->create( array( 'post_date' => '2025-12-29 12:00:00' ) ); // 2026-W01.
		self::factory()->post->create( array( 'post_date' => '2024-12-23 12:00:00' ) ); // 2024-W52.
		$this->go_to( home_url( $path ) );
		$this->assertSame( array(), $GLOBALS['wp_query']->posts );
		$this->assertTrue( is_404() );
	}

	public function data_invalid_weeks() {
		return array(
			'week 53 in a 52-week year' => array( '/2025/W53/' ),
			'week 00'                   => array( '/2025/W00/' ),
		);
	}

	public function test_post_day_link() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		$post = self::factory()->post->create( array( 'post_date' => '2024-03-15 12:00:00' ) );
		$this->assertSame( home_url( '/2024/03/15/' ), tempus_get_post_day_link( $post ) );
	}
}
