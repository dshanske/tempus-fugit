<?php
/**
 * Tests for the archive feature classes: hooks, rules, links, and titles.
 *
 * @package TempusFugit
 */

/**
 * Archive class tests.
 */
class Test_Tempus_Archive_Classes extends WP_UnitTestCase {
	use Tempus_Known_Bugs;

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
	}

	public function test_day_of_year_registers_hooks_and_rules() {
		global $wp_rewrite;
		new Tempus_Day_Of_Year();
		$this->assertSame( 10, has_filter( 'post_link', array( 'Tempus_Day_Of_Year', 'post_link' ) ) );
		$this->assertSame( 10, has_filter( 'post_type_link', array( 'Tempus_Day_Of_Year', 'post_link' ) ) );
		$this->assertSame( 10, has_filter( 'query_vars', array( 'Tempus_Day_Of_Year', 'query_vars' ) ) );
		Tempus_Day_Of_Year::plugins_loaded();
		$this->assertContains( '%dayofyear%', $wp_rewrite->rewritecode );
		$this->assertSame( '%year%/%dayofyear%', $wp_rewrite->extra_permastructs['dayofyear']['struct'] );
	}

	public function test_week_of_year_registers_hooks_and_rules() {
		global $wp_rewrite;
		new Tempus_Week_Of_Year();
		$this->assertSame( 10, has_filter( 'post_link', array( 'Tempus_Week_Of_Year', 'post_link' ) ) );
		$this->assertSame( 10, has_filter( 'query_vars', array( 'Tempus_Week_Of_Year', 'query_vars' ) ) );
		Tempus_Week_Of_Year::plugins_loaded();
		$this->assertContains( '%week%', $wp_rewrite->rewritecode );
		$this->assertSame( '%year%/W%week%', $wp_rewrite->extra_permastructs['week']['struct'] );
	}

	public function test_permalink_tags_are_listed() {
		$tags = apply_filters( 'available_permalink_structure_tags', array() );
		$this->assertArrayHasKey( 'dayofyear', $tags );
		$this->assertArrayHasKey( 'week', $tags );
	}

	public function test_permalinks_without_tags_are_unchanged() {
		$post = self::factory()->post->create();
		$this->assertSame( 'http://example.org/x/', Tempus_Day_Of_Year::post_link( 'http://example.org/x/', $post ) );
		$this->assertSame( 'http://example.org/x/', Tempus_Week_Of_Year::post_link( 'http://example.org/x/', $post ) );
	}

	public function test_day_of_year_titles() {
		$this->set_permalink_structure( '/%year%/%dayofyear%/%postname%/' );
		self::factory()->post->create( array( 'post_date' => '2024-03-15 12:00:00' ) );
		$this->go_to( home_url( '/2024/075/' ) );
		$this->assertSame( 'Day: <span>March 15, 2024</span>', get_the_archive_title() );
		$this->assertStringStartsWith( 'March 15, 2024', wp_get_document_title() );

		add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
		$this->assertSame( 'March 15, 2024', get_the_archive_title() );
	}

	public function test_week_titles() {
		$this->set_permalink_structure( '/%year%/W%week%/%postname%/' );
		self::factory()->post->create( array( 'post_date' => '2024-03-15 12:00:00' ) );
		$this->go_to( home_url( '/2024/W11/' ) );
		$this->assertSame( 'Week <span>11, 2024</span>', get_the_archive_title() );
		$this->assertStringStartsWith( '11 ', wp_get_document_title() );

		add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
		$this->assertSame( '11, 2024', get_the_archive_title() );
	}

	public function test_onthisday_registers_hooks_and_rules() {
		global $wp_rewrite;
		new Tempus_On_This_Day();
		$this->assertSame( 10, has_filter( 'pre_get_posts', array( 'Tempus_On_This_Day', 'pre_get_posts' ) ) );
		Tempus_On_This_Day::plugins_loaded();
		$rules = $wp_rewrite->extra_rules_top;
		$this->assertSame( 'index.php?onthisday=1&monthnum=$matches[1]&day=$matches[2]&paged=$matches[3]', $rules['onthisday/([0-9]{2})/([0-9]{2})/page/?([0-9]{1,})/?$'] );
		$this->assertSame( 'index.php?feed=' . get_default_feed() . '&onthisday=1', $rules['onthisday/feed/?$'] );
		$this->assertSame( 'index.php?onthisday=1&paged=$matches[1]', $rules['onthisday/page/?([0-9]{1,})/?$'] );
	}

	public function test_thisweek_registers_hooks_and_rules() {
		global $wp_rewrite;
		new Tempus_This_Week();
		$this->assertSame( 10, has_filter( 'pre_get_posts', array( 'Tempus_This_Week', 'pre_get_posts' ) ) );
		Tempus_This_Week::plugins_loaded();
		$rules = $wp_rewrite->extra_rules_top;
		$this->assertSame( 'index.php?w=$matches[1]&paged=$matches[2]&thisweek=1', $rules['thisweek/([0-9]{2})/page/?([0-9]{1,})/?$'] );
		$this->assertSame( 'index.php?w=$matches[1]&thisweek=1', $rules['thisweek/([0-9]{2})/?$'] );
		$this->assertSame( 'index.php?paged=$matches[1]&thisweek=1', $rules['thisweek/page/?([0-9]{1,})/?$'] );
	}

	public function test_slugs_are_filterable() {
		add_filter(
			'tempus_fugit_onthisday_slug',
			function () {
				return 'today';
			}
		);
		add_filter(
			'tempus_fugit_thisweek_slug',
			function () {
				return 'week';
			}
		);
		$this->assertSame( 'today', Tempus_On_This_Day::get_slug() );
		$this->assertSame( 'week', Tempus_This_Week::get_slug() );

		Tempus_On_This_Day::rewrite_rules();
		Tempus_This_Week::rewrite_rules();
		$this->assertArrayHasKey( 'today/?$', $GLOBALS['wp_rewrite']->extra_rules_top );
		$this->assertArrayHasKey( 'week/?$', $GLOBALS['wp_rewrite']->extra_rules_top );
	}

	/**
	 * @dataProvider data_filtered_slug_links
	 *
	 * @param string $structure Permalink structure.
	 * @param string $prefix    Expected path before the slug.
	 */
	public function test_links_use_filtered_slugs( $structure, $prefix ) {
		$this->set_permalink_structure( $structure );
		add_filter(
			'tempus_fugit_onthisday_slug',
			function () {
				return 'today';
			}
		);
		add_filter(
			'tempus_fugit_thisweek_slug',
			function () {
				return 'week';
			}
		);
		$this->assertSame( home_url( $prefix . 'today' ), Tempus_On_This_Day::get_link() );
		$this->assertSame( home_url( $prefix . 'week' ), Tempus_This_Week::get_link() );
	}

	public function data_filtered_slug_links() {
		return array(
			'pretty permalinks' => array( '/%year%/%monthnum%/%postname%/', '' ),
			'index permalinks'  => array( '/index.php/%year%/%postname%/', 'index.php/' ),
		);
	}

	/**
	 * @dataProvider data_links
	 *
	 * @param string $structure Permalink structure.
	 * @param string $onthisday Expected On This Day link, relative to the site.
	 * @param string $thisweek  Expected This Week link, relative to the site.
	 */
	public function test_links( $structure, $onthisday, $thisweek ) {
		$this->set_permalink_structure( $structure );
		$this->assertSame( home_url( $onthisday ), Tempus_On_This_Day::get_link() );
		$this->assertSame( home_url( $thisweek ), Tempus_This_Week::get_link() );
	}

	public function data_links() {
		return array(
			'pretty permalinks' => array( '/%year%/%monthnum%/%postname%/', 'onthisday', 'thisweek' ),
			'index permalinks'  => array( '/index.php/%year%/%postname%/', 'index.php/onthisday', 'index.php/thisweek' ),
			'plain permalinks'  => array( '', '/index.php?onthisday=1', '/index.php?thisweek=1' ),
		);
	}

	public function test_onthisday_titles() {
		self::factory()->post->create( array( 'post_date' => '2019-03-15 12:00:00' ) );
		$this->go_to( home_url( '/onthisday/03/15/' ) );
		$this->assertSame( 'On This Day: <span>March 15</span>', get_the_archive_title() );
		$this->assertStringStartsWith( 'March 15, 2019', wp_get_document_title() );

		add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
		$this->assertSame( 'March 15', get_the_archive_title() );
	}

	public function test_thisweek_titles() {
		self::factory()->post->create( array( 'post_date' => '2019-03-15 12:00:00' ) );
		$this->go_to( home_url( '/thisweek/11/' ) );
		$this->assertTrue( Tempus_This_Week::is_thisweek() );
		$this->assertSame( 'Week: <span>11</span>', get_the_archive_title() );
		$this->assertStringStartsWith( 'Week: 11', wp_get_document_title() );

		add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
		$this->assertSame( '11', get_the_archive_title() );
	}

	public function test_thisweek_feed_and_pagination_routes() {
		$this->go_to( home_url( '/thisweek/feed/' ) );
		$this->assertTrue( is_feed() );
		$this->assertSame( '1', get_query_var( 'thisweek' ) );

		$this->go_to( home_url( '/onthisday/feed/atom/' ) );
		$this->assertSame( 'atom', get_query_var( 'feed' ) );
		$this->assertSame( '1', get_query_var( 'onthisday' ) );
	}
}
