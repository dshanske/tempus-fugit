<?php
/**
 * Tests for On This Day and This Week on category, tag, and custom taxonomy archives.
 *
 * @package TempusFugit
 */

/**
 * Taxonomy archive tests.
 */
class Test_Tempus_Taxonomy_Archives extends WP_UnitTestCase {

	/**
	 * Today's date in the site timezone.
	 *
	 * @var DateTimeImmutable
	 */
	private $today;

	/**
	 * Post IDs, keyed by description.
	 *
	 * @var int[]
	 */
	private $posts = array();

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		// Core registers the category and tag rules when its taxonomies are created.
		create_initial_taxonomies();
		register_taxonomy(
			'topic',
			'post',
			array(
				'public'  => true,
				'rewrite' => array( 'slug' => 'topic' ),
			)
		);
		Tempus_On_This_Day::taxonomy_rewrite_rules();
		Tempus_This_Week::taxonomy_rewrite_rules();
		flush_rewrite_rules( false );

		$this->today = new DateTimeImmutable( 'now', wp_timezone() );
		$day         = $this->today->modify( '-4 years' )->setTime( 12, 0 )->format( 'Y-m-d H:i:s' );
		$news        = self::factory()->category->create(
			array(
				'name' => 'News',
				'slug' => 'news',
			)
		);
		$local       = self::factory()->category->create(
			array(
				'name'   => 'Local',
				'slug'   => 'local',
				'parent' => $news,
			)
		);
		$travel      = self::factory()->term->create(
			array(
				'taxonomy' => 'topic',
				'name'     => 'Travel',
				'slug'     => 'travel',
			)
		);

		$this->posts = array(
			'tagged today'     => self::factory()->post->create(
				array(
					'post_title'    => 'Tagged today',
					'post_date'     => $day,
					'tags_input'    => array( 'foo' ),
					'post_category' => array( $news ),
				)
			),
			'untagged today'   => self::factory()->post->create(
				array(
					'post_title' => 'Untagged today',
					'post_date'  => $day,
				)
			),
			'local today'      => self::factory()->post->create(
				array(
					'post_date'     => $day,
					'post_category' => array( $local ),
				)
			),
			'tagged march 15'  => self::factory()->post->create(
				array(
					'post_date'  => '2019-03-15 12:00:00',
					'tags_input' => array( 'foo' ),
				)
			),
			'tagged this week' => self::factory()->post->create(
				array(
					'post_title' => 'Tagged this week',
					'post_date'  => $this->same_week_years_ago( 4 )->format( 'Y-m-d H:i:s' ),
					'tags_input' => array( 'foo' ),
				)
			),
			'untagged week'    => self::factory()->post->create(
				array(
					'post_title' => 'Untagged this week',
					'post_date'  => $this->same_week_years_ago( 4 )->format( 'Y-m-d H:i:s' ),
				)
			),
		);
		wp_set_object_terms( $this->posts['tagged today'], $travel, 'topic' );
	}

	public function tear_down() {
		unregister_taxonomy( 'topic' );
		parent::tear_down();
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
	 * Returns the IDs of the posts in the main query.
	 *
	 * @return int[] Post IDs.
	 */
	private function query_ids() {
		return wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
	}

	public function test_rules_are_registered_late_on_init() {
		$this->assertSame( 99, has_action( 'init', array( 'Tempus_On_This_Day', 'taxonomy_rewrite_rules' ) ) );
		$this->assertSame( 99, has_action( 'init', array( 'Tempus_This_Week', 'taxonomy_rewrite_rules' ) ) );
	}

	public function test_taxonomy_archive_regexes() {
		$regexes = tempus_get_taxonomy_archive_regexes();
		$this->assertSame( 'tag', $regexes['tag/([^/]+)'] );
		$this->assertSame( 'category_name', $regexes['category/(.+?)'] );
		$this->assertSame( 'topic', $regexes['topic/([^/]+)'] );
	}

	public function test_taxonomy_archive_regexes_use_custom_bases() {
		update_option( 'tag_base', 'keyword' );
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		create_initial_taxonomies();
		$this->assertArrayHasKey( 'keyword/([^/]+)', tempus_get_taxonomy_archive_regexes() );
	}

	public function test_no_taxonomy_regexes_with_plain_permalinks() {
		$this->set_permalink_structure( '' );
		$this->assertSame( array(), tempus_get_taxonomy_archive_regexes() );
	}

	/**
	 * @dataProvider data_onthisday_archives
	 *
	 * @param string   $path     Archive path.
	 * @param string[] $expected Keys of the posts the archive lists.
	 */
	public function test_onthisday_term_archives( $path, $expected ) {
		$this->go_to( home_url( $path ) );
		$this->assertTrue( Tempus_On_This_Day::is_onthisday() );
		$this->assertEqualsCanonicalizing( array_map( array( $this, 'post_id' ), $expected ), $this->query_ids() );
	}

	public function data_onthisday_archives() {
		return array(
			'tag'                   => array( '/tag/foo/onthisday/', array( 'tagged today' ) ),
			'tag, paged'            => array( '/tag/foo/onthisday/page/1/', array( 'tagged today' ) ),
			'category and children' => array( '/category/news/onthisday/', array( 'tagged today', 'local today' ) ),
			'child category'        => array( '/category/news/local/onthisday/', array( 'local today' ) ),
			'custom taxonomy'       => array( '/topic/travel/onthisday/', array( 'tagged today' ) ),
			'specific date'         => array( '/tag/foo/onthisday/03/15/', array( 'tagged march 15' ) ),
			'specific date, paged'  => array( '/tag/foo/onthisday/03/15/page/1/', array( 'tagged march 15' ) ),
		);
	}

	/**
	 * Returns a post ID by its key.
	 *
	 * @param string $key Post key.
	 * @return int Post ID.
	 */
	public function post_id( $key ) {
		return $this->posts[ $key ];
	}

	public function test_specific_date_term_archive_lists_previous_years_only() {
		$year     = (int) $this->today->format( 'Y' );
		$current  = self::factory()->post->create(
			array(
				'post_date'  => $year . '-01-01 00:00:01',
				'tags_input' => array( 'foo' ),
			)
		);
		$previous = self::factory()->post->create(
			array(
				'post_date'  => ( $year - 3 ) . '-01-01 12:00:00',
				'tags_input' => array( 'foo' ),
			)
		);
		$this->go_to( home_url( '/tag/foo/onthisday/01/01/' ) );
		$this->assertContains( $previous, $this->query_ids() );
		$this->assertNotContains( $current, $this->query_ids() );
	}

	public function test_thisweek_term_archive() {
		$this->go_to( home_url( '/tag/foo/thisweek/' ) );
		$this->assertTrue( Tempus_This_Week::is_thisweek() );
		$this->assertContains( $this->posts['tagged this week'], $this->query_ids() );
		$this->assertNotContains( $this->posts['untagged week'], $this->query_ids() );
	}

	public function test_thisweek_term_archive_for_specific_week() {
		$this->go_to( home_url( '/tag/foo/thisweek/11/' ) );
		$this->assertContains( $this->posts['tagged march 15'], $this->query_ids() );

		$this->go_to( home_url( '/tag/foo/thisweek/11/page/1/' ) );
		$this->assertContains( $this->posts['tagged march 15'], $this->query_ids() );

		$this->go_to( home_url( '/tag/foo/thisweek/page/1/' ) );
		$this->assertContains( $this->posts['tagged this week'], $this->query_ids() );
	}

	public function test_term_archive_feeds() {
		$this->go_to( home_url( '/tag/foo/onthisday/feed/' ) );
		$this->assertTrue( is_feed() );
		$this->assertSame( 'foo', get_query_var( 'tag' ) );
		$this->assertSame( '1', get_query_var( 'onthisday' ) );

		$this->go_to( home_url( '/tag/foo/thisweek/feed/atom/' ) );
		$this->assertSame( 'atom', get_query_var( 'feed' ) );
		$this->assertSame( '1', get_query_var( 'thisweek' ) );

		$this->go_to( home_url( '/tag/foo/onthisday/feed/rss2/' ) );
		$this->assertSame( 'rss2', get_query_var( 'feed' ) );

		$this->go_to( home_url( '/tag/foo/thisweek/feed/' ) );
		$this->assertTrue( is_feed() );
	}

	public function test_onthisday_term_titles() {
		$this->go_to( home_url( '/topic/travel/onthisday/' ) );
		$date = $this->today->format( 'F j' );
		$this->assertSame( 'On This Day in Travel: <span>' . $date . '</span>', get_the_archive_title() );
		$this->assertStringStartsWith( 'On This Day in Travel: ' . $date, wp_get_document_title() );
	}

	public function test_thisweek_term_titles() {
		$this->go_to( home_url( '/tag/foo/thisweek/' ) );
		$week = $this->today->format( 'W' );
		$this->assertSame( 'Week in foo: <span>' . $week . '</span>', get_the_archive_title() );
		$this->assertStringStartsWith( 'Week in foo: ' . $week, wp_get_document_title() );
	}

	public function test_queried_term_name() {
		$this->go_to( home_url( '/' ) );
		$this->assertSame( '', tempus_get_queried_term_name() );
		$this->go_to( home_url( '/category/news/' ) );
		$this->assertSame( 'News', tempus_get_queried_term_name() );
	}

	public function test_term_archive_links() {
		$tag   = get_term_by( 'slug', 'foo', 'post_tag' );
		$local = get_term_by( 'slug', 'local', 'category' );

		$this->assertSame( home_url( '/tag/foo/onthisday/' ), Tempus_On_This_Day::get_term_archive_link( $tag ) );
		$this->assertSame( home_url( '/tag/foo/thisweek/' ), Tempus_This_Week::get_term_archive_link( $tag ) );
		$this->assertSame( home_url( '/tag/foo/onthisday/' ), Tempus_On_This_Day::get_term_archive_link( $tag->term_id ) );
		$this->assertSame( home_url( '/tag/foo/onthisday/' ), Tempus_On_This_Day::get_term_archive_link( 'foo', 'post_tag' ) );
		$this->assertSame( home_url( '/category/news/local/onthisday/' ), Tempus_On_This_Day::get_term_archive_link( $local ) );
		$this->assertSame( home_url( '/topic/travel/thisweek/' ), Tempus_This_Week::get_term_archive_link( 'travel', 'topic' ) );
	}

	public function test_term_archive_links_resolve() {
		$this->go_to( Tempus_On_This_Day::get_term_archive_link( 'travel', 'topic' ) );
		$this->assertSame( array( $this->posts['tagged today'] ), $this->query_ids() );
	}

	public function test_term_archive_links_with_plain_permalinks() {
		$this->set_permalink_structure( '' );
		$tag = get_term_by( 'slug', 'foo', 'post_tag' );
		$this->assertSame( home_url( '/?tag=foo&onthisday=1' ), Tempus_On_This_Day::get_term_archive_link( $tag ) );
		$this->assertSame( home_url( '/?tag=foo&thisweek=1' ), Tempus_This_Week::get_term_archive_link( $tag ) );
	}

	public function test_term_archive_links_for_missing_terms() {
		$this->assertSame( '', Tempus_On_This_Day::get_term_archive_link( 'missing', 'post_tag' ) );
		$this->assertSame( '', Tempus_This_Week::get_term_archive_link( 999999 ) );
		$this->assertSame( '', Tempus_On_This_Day::get_term_archive_link( 'foo', 'no_such_taxonomy' ) );
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
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '<h2>',
				'after_title'   => '</h2>',
			),
			$instance
		);
		return ob_get_clean();
	}

	public function test_onthisday_widget_limited_to_term() {
		$widget = new Tempus_OnThisDay_Widget();
		$widget->_set( 1 );
		$output = $this->render(
			$widget,
			array(
				'title'    => 'Travel',
				'taxonomy' => 'topic',
				'term'     => 'travel',
			)
		);
		$this->assertStringContainsString( '<h2><a href="' . home_url( '/topic/travel/onthisday/' ) . '">Travel</a></h2>', $output );
		$this->assertStringContainsString( 'Tagged today', $output );
		$this->assertStringNotContainsString( 'Untagged today', $output );
	}

	public function test_widgets_cache_separately() {
		$limited = new Tempus_OnThisDay_Widget();
		$limited->_set( 1 );
		$all = new Tempus_OnThisDay_Widget();
		$all->_set( 2 );

		$this->assertStringNotContainsString(
			'Untagged today',
			$this->render(
				$limited,
				array(
					'taxonomy' => 'post_tag',
					'term'     => 'foo',
				)
			)
		);
		$this->assertStringContainsString( 'Untagged today', $this->render( $all, array() ) );
	}

	public function test_widget_with_missing_term_shows_no_posts() {
		$widget = new Tempus_OnThisDay_Widget();
		$widget->_set( 1 );
		$output = $this->render(
			$widget,
			array(
				'taxonomy' => 'post_tag',
				'term'     => 'deleted',
			)
		);
		$this->assertStringContainsString( 'There were no posts on this day in previous years', $output );
	}

	public function test_thisweek_widget_limited_to_term() {
		$widget = new Tempus_ThisWeek_Widget();
		$widget->_set( 1 );
		$output = $this->render(
			$widget,
			array(
				'title'    => 'Foo',
				'taxonomy' => 'post_tag',
				'term'     => 'foo',
			)
		);
		$this->assertStringContainsString( '<h2><a href="' . home_url( '/tag/foo/thisweek/' ) . '">Foo</a></h2>', $output );
		$this->assertStringContainsString( 'Tagged this week', $output );
		$this->assertStringNotContainsString( 'Untagged this week', $output );
	}

	public function test_widget_update_sanitizes_taxonomy_settings() {
		$widget = new Tempus_OnThisDay_Widget();

		$instance = $widget->update(
			array(
				'taxonomy' => 'post_tag',
				'term'     => 'Foo Bar',
			),
			array()
		);
		$this->assertSame( 'post_tag', $instance['taxonomy'] );
		$this->assertSame( 'foo-bar', $instance['term'] );

		$instance = $widget->update(
			array(
				'taxonomy' => 'no_such_taxonomy',
				'term'     => 'foo',
			),
			array()
		);
		$this->assertSame( '', $instance['taxonomy'] );
		$this->assertSame( '', $instance['term'] );
	}

	public function test_widget_form_includes_taxonomy_settings() {
		$widget = new Tempus_OnThisDay_Widget();
		$widget->_set( 3 );
		ob_start();
		$widget->form(
			array(
				'taxonomy' => 'topic',
				'term'     => 'travel',
			)
		);
		$form = ob_get_clean();
		$this->assertStringContainsString( '<option value="">All posts</option>', $form );
		// WordPress prints selected='selected'; ClassicPress prints the HTML5 boolean attribute.
		$this->assertMatchesRegularExpression( '/<option value="topic"\s+selected(=\'selected\')?>/', $form );
		$this->assertStringContainsString( 'name="widget-tempus_onthisday_widget[3][term]" id="widget-tempus_onthisday_widget-3-term" value="travel"', $form );
	}
}
