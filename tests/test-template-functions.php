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
		$this->assertSame( home_url( '/2025/W01/' ), tempus_get_post_week_link( $post ) );
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
		$this->assertSame( '2024-02-01 00:00:00', tempus_get_archive_datetime()->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * @dataProvider data_archive_periods
	 *
	 * @param string $structure Permalink structure.
	 * @param string $post_date Date of a post in the archive.
	 * @param string $archive   Archive path.
	 * @param string $type      Expected period type.
	 * @param string $start     Expected start.
	 * @param string $end       Expected end.
	 */
	public function test_archive_period( $structure, $post_date, $archive, $type, $start, $end ) {
		$this->set_permalink_structure( $structure );
		self::factory()->post->create( array( 'post_date' => $post_date ) );
		$this->go_to( home_url( $archive ) );
		$period = tempus_get_archive_period();
		$this->assertSame( $type, $period['type'] );
		$this->assertSame( $start, $period['start']->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( $end, $period['end']->format( 'Y-m-d H:i:s' ) );
	}

	public function data_archive_periods() {
		$dates = '/%year%/%monthnum%/%day%/%postname%/';
		return array(
			'day'         => array( $dates, '2024-03-15 12:00:00', '/2024/03/15/', 'day', '2024-03-15 00:00:00', '2024-03-15 23:59:59' ),
			'month'       => array( $dates, '2024-02-10 12:00:00', '/2024/02/', 'month', '2024-02-01 00:00:00', '2024-02-29 23:59:59' ),
			'year'        => array( $dates, '2024-02-10 12:00:00', '/2024/', 'year', '2024-01-01 00:00:00', '2024-12-31 23:59:59' ),
			'week'        => array( '/%year%/W%week%/%postname%/', '2024-12-31 12:00:00', '/2025/W01/', 'week', '2024-12-30 00:00:00', '2025-01-05 23:59:59' ),
			'day of year' => array( '/%year%/%dayofyear%/%postname%/', '2024-03-15 12:00:00', '/2024/075/', 'dayofyear', '2024-03-15 00:00:00', '2024-03-15 23:59:59' ),
		);
	}

	/**
	 * Adjacent links skip periods without posts: there are posts in 2019, 2020, and 2022, but not 2021.
	 *
	 * @dataProvider data_adjacent_date_links
	 *
	 * @param string $structure Permalink structure.
	 * @param string $archive   Archive path.
	 * @param string $previous  Expected previous link.
	 * @param string $next      Expected next link.
	 */
	public function test_adjacent_date_links_skip_empty_periods( $structure, $archive, $previous, $next ) {
		$this->set_permalink_structure( $structure );
		self::factory()->post->create( array( 'post_date' => '2019-11-20 12:00:00' ) );
		self::factory()->post->create( array( 'post_date' => '2020-06-15 09:00:00' ) );
		self::factory()->post->create( array( 'post_date' => '2020-06-15 18:00:00' ) );
		self::factory()->post->create( array( 'post_date' => '2022-03-01 12:00:00' ) );
		$this->go_to( home_url( $archive ) );
		$this->assertSame( $previous, tempus_get_adjacent_date_link() );
		$this->assertSame( $next, tempus_get_adjacent_date_link( false ) );
	}

	public function data_adjacent_date_links() {
		$dates = '/%year%/%monthnum%/%day%/%postname%/';
		return array(
			'day'         => array( $dates, '/2020/06/15/', '<a href="http://example.org/2019/11/20/" rel="prev">November 20, 2019</a>', '<a href="http://example.org/2022/03/01/" rel="next">March 1, 2022</a>' ),
			'month'       => array( $dates, '/2020/06/', '<a href="http://example.org/2019/11/" rel="prev">November 2019</a>', '<a href="http://example.org/2022/03/" rel="next">March 2022</a>' ),
			'year'        => array( $dates, '/2020/', '<a href="http://example.org/2019/" rel="prev">2019</a>', '<a href="http://example.org/2022/" rel="next">2022</a>' ),
			'week'        => array( '/%year%/W%week%/%postname%/', '/2020/W25/', '<a href="http://example.org/2019/W47/" rel="prev">Week 47, 2019</a>', '<a href="http://example.org/2022/W09/" rel="next">Week 09, 2022</a>' ),
			'day of year' => array( '/%year%/%dayofyear%/%postname%/', '/2020/167/', '<a href="http://example.org/2019/324/" rel="prev">November 20, 2019</a>', '<a href="http://example.org/2022/060/" rel="next">March 1, 2022</a>' ),
		);
	}

	public function test_no_links_past_the_oldest_and_newest_posts() {
		self::factory()->post->create( array( 'post_date' => '2020-06-15 12:00:00' ) );
		$this->go_to( home_url( '/2020/06/' ) );
		$this->assertSame( '', tempus_get_adjacent_date_link() );
		$this->assertSame( '', tempus_get_adjacent_date_link( false ) );
	}

	public function test_no_next_link_to_scheduled_posts() {
		$today = new DateTimeImmutable( 'now', wp_timezone() );
		self::factory()->post->create( array( 'post_date' => $today->modify( '-1 minute' )->format( 'Y-m-d H:i:s' ) ) );
		self::factory()->post->create(
			array(
				'post_date'   => $today->modify( '+1 year' )->format( 'Y-m-d H:i:s' ),
				'post_status' => 'future',
			)
		);
		$this->go_to( home_url( $today->format( '/Y/' ) ) );
		$this->assertSame( '', tempus_get_adjacent_date_link( false ) );
	}

	public function test_no_navigation_without_a_full_period() {
		self::factory()->post->create( array( 'post_date' => '2019-03-15 12:00:00' ) );
		$this->go_to( home_url( '/onthisday/03/15/' ) );
		$this->assertFalse( tempus_get_archive_period() );
		$this->assertFalse( tempus_get_adjacent_date_link() );
		$this->go_to( home_url( '/thisweek/11/' ) );
		$this->assertFalse( tempus_get_archive_period() );

		// Time archives are date archives, but not day, month, or year archives.
		$this->go_to( home_url( '/?year=2019&hour=12' ) );
		$this->assertTrue( is_time() );
		$this->assertFalse( tempus_get_archive_period() );
	}

	public function test_adjacent_link_text() {
		self::factory()->post->create( array( 'post_date' => '2019-11-20 12:00:00' ) );
		self::factory()->post->create( array( 'post_date' => '2020-06-15 12:00:00' ) );
		$this->go_to( home_url( '/2020/' ) );
		$this->assertSame(
			'<a href="http://example.org/2019/" rel="prev"><span class="meta-nav">Previous:</span> 2019</a>',
			tempus_get_adjacent_date_link( true, '<span class="meta-nav">Previous:</span> %title' )
		);
		$this->assertSame(
			'<a href="http://example.org/2019/" rel="prev">Earlier alert(1)</a>',
			tempus_get_adjacent_date_link( true, 'Earlier <script>alert(1)</script>' )
		);
	}

	public function test_day_of_year_link() {
		$date = new DateTimeImmutable( '2024-03-15 12:00:00', wp_timezone() );
		$this->set_permalink_structure( '/%year%/%dayofyear%/%postname%/' );
		$this->assertSame( home_url( '/2024/075/' ), tempus_get_day_of_year_link( $date ) );

		$this->set_permalink_structure( '' );
		$this->assertSame( home_url( '?m=20240315' ), tempus_get_day_of_year_link( $date ) );
	}

	public function test_date_navigation_markup() {
		self::factory()->post->create( array( 'post_date' => '2019-06-15 12:00:00' ) );
		self::factory()->post->create( array( 'post_date' => '2020-06-15 12:00:00' ) );
		self::factory()->post->create( array( 'post_date' => '2021-06-15 12:00:00' ) );
		$this->go_to( home_url( '/2020/' ) );
		$nav = tempus_get_the_date_navigation(
			array(
				'class'     => 'my-nav',
				'prev_text' => '&larr; %title',
				'next_text' => '%title &rarr;',
			)
		);
		$this->assertStringContainsString( '<nav class="navigation my-nav" aria-label="Date navigation">', $nav );
		$this->assertStringContainsString( '<h2 class="screen-reader-text">Date navigation</h2>', $nav );
		$this->assertStringContainsString( '<div class="nav-previous"><a href="http://example.org/2019/" rel="prev">&larr; 2019</a></div>', $nav );
		$this->assertStringContainsString( '<div class="nav-next"><a href="http://example.org/2021/" rel="next">2021 &rarr;</a></div>', $nav );
	}

	public function test_date_navigation_without_links() {
		$this->go_to( home_url( '/' ) );
		$nav = tempus_get_the_date_navigation();
		$this->assertStringContainsString( '<div class="nav-links"></div>', $nav );
	}

	public function test_date_navigation_labels() {
		$this->go_to( home_url( '/' ) );
		$nav = tempus_get_the_date_navigation( array( 'screen_reader_text' => 'Years' ) );
		$this->assertStringContainsString( 'aria-label="Years"', $nav );
		$this->assertStringContainsString( '<h2 class="screen-reader-text">Years</h2>', $nav );

		$nav = tempus_get_the_date_navigation(
			array(
				'screen_reader_text' => 'Years',
				'aria_label'         => 'Year archives',
			)
		);
		$this->assertStringContainsString( 'aria-label="Year archives"', $nav );
		$this->assertStringContainsString( '<h2 class="screen-reader-text">Years</h2>', $nav );
	}
}
