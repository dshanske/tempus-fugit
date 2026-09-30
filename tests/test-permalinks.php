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

	public function test_post_day_link() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		$post = self::factory()->post->create( array( 'post_date' => '2024-03-15 12:00:00' ) );
		$this->assertSame( home_url( '/2024/03/15/' ), tempus_get_post_day_link( $post ) );
	}
}
