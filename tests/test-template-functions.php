<?php
/**
 * Tests for the theme-facing template functions and date navigation.
 *
 * @package TempusFugit
 */

/**
 * Template function tests.
 */
class Test_Tempus_Template_Functions extends WP_UnitTestCase {
	use Tempus_Known_Bugs;

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
	}

	public function test_post_day_link_with_plain_permalinks() {
		$this->set_permalink_structure( '' );
		$post = self::factory()->post->create( array( 'post_date' => '2024-03-15 12:00:00' ) );
		$this->assertSame( home_url( '?m=20240315' ), tempus_get_post_day_link( $post ) );
	}

	public function test_post_day_link_uses_current_post() {
		$post = self::factory()->post->create( array( 'post_date' => '2024-03-15 12:00:00' ) );
		$this->go_to( get_permalink( $post ) );
		the_post();
		$this->assertSame( home_url( '/2024/03/15/' ), tempus_get_post_day_link() );
	}

	public function test_post_week_link() {
		$post = self::factory()->post->create( array( 'post_date' => '2024-03-15 12:00:00' ) );
		$this->assertSame( home_url( '/2024/W11/' ), tempus_get_post_week_link( $post ) );
	}

	public function test_post_week_link_uses_iso_year_at_year_boundary() {
		// December 30, 2024 is in ISO week 1 of 2025.
		$post = self::factory()->post->create( array( 'post_date' => '2024-12-30 12:00:00' ) );
		$this->assert_same_or_known_bug(
			home_url( '/2025/W01/' ),
			tempus_get_post_week_link( $post ),
			'week links combine the ISO week number with the calendar year instead of the ISO year.'
		);
	}

	public function test_post_week_link_with_plain_permalinks() {
		$this->set_permalink_structure( '' );
		$post = self::factory()->post->create( array( 'post_date' => '2024-03-15 12:00:00' ) );
		$this->assert_same_or_known_bug(
			home_url( '?year=2024&tempus_week=11' ),
			tempus_get_post_week_link( $post ),
			'tempus_get_post_week_link() ignores plain permalinks and always returns a pretty URL.'
		);
	}

	public function test_archive_date_functions_outside_date_archives() {
		$this->go_to( home_url( '/' ) );
		$this->assertFalse( tempus_get_archive_date_query() );
		$this->assertFalse( tempus_get_archive_datetime() );
		$this->assertFalse( tempus_get_adjacent_date_link() );
	}

	public function test_date_functions_on_archives_without_a_full_date() {
		self::factory()->post->create( array( 'post_date' => '2019-03-15 12:00:00' ) );

		// On This Day archives have no year.
		$this->go_to( home_url( '/onthisday/03/15/' ) );
		$this->assertFalse( tempus_get_archive_datetime() );
		$this->assertFalse( tempus_get_adjacent_date_link() );

		// Week number archives are date archives, but not day, month, or year archives.
		$this->go_to( home_url( '/thisweek/11/' ) );
		$this->assertTrue( is_date() );
		$this->assertFalse( tempus_get_archive_datetime() );
	}

	public function test_archive_date_query_on_day_archive() {
		self::factory()->post->create( array( 'post_date' => '2024-03-15 12:00:00' ) );
		$this->go_to( home_url( '/2024/03/15/' ) );
		$this->assertSame(
			array(
				'day'      => 15,
				'monthnum' => 3,
				'year'     => 2024,
			),
			tempus_get_archive_date_query()
		);
		$this->assertSame( '2024-03-15', tempus_get_archive_datetime()->format( 'Y-m-d' ) );
	}

	public function test_archive_date_query_includes_date_query() {
		$this->set_permalink_structure( '/%year%/%dayofyear%/%postname%/' );
		self::factory()->post->create( array( 'post_date' => '2024-03-15 12:00:00' ) );
		$this->go_to( home_url( '/2024/075/' ) );
		$date = tempus_get_archive_date_query();
		$this->assertSame( 75, $date['dayofyear'] );
		$this->assertSame( 2024, $date['year'] );
	}

	public function test_archive_datetime_on_year_archive() {
		self::factory()->post->create( array( 'post_date' => '2024-02-10 12:00:00' ) );
		$this->go_to( home_url( '/2024/' ) );
		$this->assertSame( '2024', tempus_get_archive_datetime()->format( 'Y' ) );
	}

	public function test_archive_datetime_starts_at_beginning_of_period() {
		self::factory()->post->create( array( 'post_date' => '2024-02-10 12:00:00' ) );
		$this->go_to( home_url( '/2024/02/' ) );
		$this->assert_same_or_known_bug(
			'2024-02-01',
			tempus_get_archive_datetime()->format( 'Y-m-d' ),
			'month and year archive dates take the missing day and month from the current date.'
		);
	}

	/**
	 * @dataProvider data_adjacent_date_links
	 *
	 * @param string $archive  Archive path.
	 * @param string $previous Expected previous link.
	 * @param string $next     Expected next link.
	 */
	public function test_adjacent_date_links( $archive, $previous, $next ) {
		self::factory()->post->create( array( 'post_date' => '2020-06-15 12:00:00' ) );
		$this->go_to( home_url( $archive ) );
		$this->assertSame( $previous, tempus_get_adjacent_date_link() );
		$this->assertSame( $next, tempus_get_adjacent_date_link( false ) );
	}

	public function data_adjacent_date_links() {
		return array(
			'day'   => array( '/2020/06/15/', '<a href="http://example.org/2020/06/14/" rel="prev">June 14, 2020</a>', '<a href="http://example.org/2020/06/16/" rel="next">June 16, 2020</a>' ),
			'month' => array( '/2020/06/', '<a href="http://example.org/2020/05/" rel="prev">May 2020</a>', '<a href="http://example.org/2020/07/" rel="next">July 2020</a>' ),
			'year'  => array( '/2020/', '<a href="http://example.org/2019/" rel="prev">2019</a>', '<a href="http://example.org/2021/" rel="next">2021</a>' ),
		);
	}

	public function test_no_next_link_for_future_dates() {
		$today = new DateTimeImmutable( 'now', wp_timezone() );
		self::factory()->post->create( array( 'post_date' => $today->modify( '-1 minute' )->format( 'Y-m-d H:i:s' ) ) );
		$this->go_to( home_url( $today->format( '/Y/' ) ) );
		$this->assertSame( '', tempus_get_adjacent_date_link( false ) );
	}

	public function test_date_navigation_markup() {
		self::factory()->post->create( array( 'post_date' => '2020-06-15 12:00:00' ) );
		$this->go_to( home_url( '/2020/' ) );
		$nav = tempus_get_the_date_navigation( array( 'class' => 'my-nav' ) );
		$this->assertStringContainsString( '<nav class="navigation my-nav" aria-label="Date navigation">', $nav );
		$this->assertStringContainsString( '<div class="nav-previous"><a href="http://example.org/2019/" rel="prev">2019</a></div>', $nav );
		$this->assertStringContainsString( '<div class="nav-next"><a href="http://example.org/2021/" rel="next">2021</a></div>', $nav );
	}

	public function test_date_navigation_without_links() {
		$this->go_to( home_url( '/' ) );
		$nav = tempus_get_the_date_navigation();
		$this->assertStringContainsString( '<div class="nav-links"></div>', $nav );
	}

	public function test_date_navigation_with_screen_reader_text_only() {
		self::factory()->post->create( array( 'post_date' => '2020-06-15 12:00:00' ) );
		$this->go_to( home_url( '/2020/' ) );
		$nav = tempus_get_the_date_navigation( array( 'screen_reader_text' => 'Years' ) );
		$this->assertStringStartsWith( '<nav class="navigation date-navigation" aria-label="', trim( $nav ) );
		$this->assertStringContainsString( 'rel="prev">2019</a>', $nav );
	}

	public function test_date_navigation_accepts_labels() {
		self::factory()->post->create( array( 'post_date' => '2020-06-15 12:00:00' ) );
		$this->go_to( home_url( '/2020/' ) );
		$nav = tempus_get_the_date_navigation( array( 'screen_reader_text' => 'Years' ) );
		$this->assert_same_or_known_bug(
			1,
			substr_count( $nav, 'aria-label="Years"' ),
			'tempus_get_the_date_navigation() ignores the screen_reader_text and aria_label arguments.'
		);
	}
}
