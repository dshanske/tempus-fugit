<?php
/**
 * Tests for the plugin bootstrap class and sorted archives.
 *
 * @package TempusFugit
 */

/**
 * Bootstrap and sorting tests.
 */
class Test_Tempus_Bootstrap extends WP_UnitTestCase {
	use Tempus_Known_Bugs;

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
	}

	public function tear_down() {
		unset( $GLOBALS['current_screen'] );
		parent::tear_down();
	}

	public function test_load_hooks_are_registered() {
		$this->assertSame( 10, has_action( 'plugins_loaded', array( 'Tempus_Fugit_Plugin', 'plugins_loaded' ) ) );
		$this->assertSame( 10, has_action( 'init', array( 'Tempus_Fugit_Plugin', 'init' ) ) );
		$this->assertSame( 10, has_action( 'upgrader_process_complete', array( 'Tempus_Fugit_Plugin', 'upgrader_process_complete' ) ) );
		$basename = plugin_basename( dirname( __DIR__ ) . '/tempus-fugit.php' );
		$this->assertSame( 10, has_action( 'activate_' . $basename, array( 'Tempus_Fugit_Plugin', 'activate' ) ) );
		$this->assertSame( 10, has_action( 'deactivate_' . $basename, array( 'Tempus_Fugit_Plugin', 'deactivate' ) ) );
	}

	public function test_plugins_loaded_registers_sorting_and_widgets() {
		global $wp_filter, $wp_widget_factory;
		$before = $wp_filter['widgets_init']->callbacks[10];
		Tempus_Fugit_Plugin::plugins_loaded();
		$this->assertSame( 10, has_filter( 'pre_get_posts', array( 'Tempus_Fugit_Plugin', 'date_sort' ) ) );

		// The widgets are already registered at load, so run the new callback on a fresh factory.
		$added = array_diff_key( $wp_filter['widgets_init']->callbacks[10], $before );
		$this->assertCount( 1, $added );
		$factory           = $wp_widget_factory;
		$wp_widget_factory = new WP_Widget_Factory();
		call_user_func( reset( $added )['function'] );
		$widgets           = $wp_widget_factory->widgets;
		$wp_widget_factory = $factory;
		$this->assertArrayHasKey( 'Tempus_OnThisDay_Widget', $widgets );
		$this->assertArrayHasKey( 'Tempus_ThisWeek_Widget', $widgets );
	}

	public function test_init_loads_features_and_rewrite_rules() {
		global $wp_rewrite;
		Tempus_Fugit_Plugin::init();
		$this->assertTrue( function_exists( 'tempus_get_the_date_navigation' ) );
		$this->assertContains( '%dayofyear%', $wp_rewrite->rewritecode );
		$this->assertContains( '%week%', $wp_rewrite->rewritecode );
		$this->assertArrayHasKey( 'onthisday/?$', $wp_rewrite->extra_rules_top );
		$this->assertArrayHasKey( 'thisweek/?$', $wp_rewrite->extra_rules_top );
		$this->assertArrayHasKey( '(updated|oldest|random)/?$', $wp_rewrite->extra_rules_top );
	}

	/**
	 * @dataProvider data_upgrader_process_complete
	 *
	 * @param array $options   Upgrade details.
	 * @param bool  $resets    Whether the rewrite rules should be reset.
	 */
	public function test_upgrader_process_complete( $options, $resets ) {
		if ( isset( $options['plugins'] ) && in_array( 'this-plugin', $options['plugins'], true ) ) {
			$options['plugins'] = array( 'another/plugin.php', plugin_basename( dirname( __DIR__ ) . '/tempus-fugit.php' ) );
		}
		update_option( 'rewrite_rules', array( 'stale' => 'index.php' ) );
		Tempus_Fugit_Plugin::upgrader_process_complete( null, $options );
		if ( $resets ) {
			$this->assertFalse( get_option( 'rewrite_rules' ) );
		} else {
			$this->assertSame( array( 'stale' => 'index.php' ), get_option( 'rewrite_rules' ) );
		}
	}

	public function data_upgrader_process_complete() {
		return array(
			'this plugin updated'  => array(
				array(
					'action'  => 'update',
					'type'    => 'plugin',
					'plugins' => array( 'this-plugin' ),
				),
				true,
			),
			'other plugin updated' => array(
				array(
					'action'  => 'update',
					'type'    => 'plugin',
					'plugins' => array( 'another/plugin.php' ),
				),
				false,
			),
			'theme updated'        => array(
				array(
					'action' => 'update',
					'type'   => 'theme',
				),
				false,
			),
		);
	}

	public function test_series_archives_list_oldest_first() {
		register_taxonomy( 'series', 'post', array( 'public' => true ) );
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		$term   = self::factory()->term->create( array( 'taxonomy' => 'series' ) );
		$first  = self::factory()->post->create( array( 'post_date' => '2020-01-01 00:00:00' ) );
		$second = self::factory()->post->create( array( 'post_date' => '2021-01-01 00:00:00' ) );
		wp_set_object_terms( $first, $term, 'series' );
		wp_set_object_terms( $second, $term, 'series' );

		$this->go_to( get_term_link( $term, 'series' ) );
		$this->assertTrue( is_tax( 'series' ) );
		$this->assertSame( array( $first, $second ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
		unregister_taxonomy( 'series' );
	}

	public function test_query_filters_skip_admin_and_secondary_queries() {
		$query = new WP_Query();
		$query->set( 'tempus_sort', 'oldest' );
		$this->assertNull( Tempus_Fugit_Plugin::date_sort( $query ), 'Secondary query.' );

		set_current_screen( 'edit.php' );
		$this->assertNull( Tempus_Fugit_Plugin::date_sort( $query ) );
		$this->assertNull( Tempus_Order_By::order_by( $query ) );
		$this->assertNull( Tempus_Day_Of_Year::day_of_year( $query ) );
		$this->assertNull( Tempus_Week_Of_Year::week_of_year( $query ) );
		$this->assertNull( Tempus_On_This_Day::pre_get_posts( $query ) );
		$this->assertNull( Tempus_This_Week::pre_get_posts( $query ) );
		$this->assertSame( '', $query->get( 'order' ) );
	}

	public function test_order_by_registers_hooks_and_rules() {
		global $wp_rewrite;
		new Tempus_Order_By();
		$this->assertSame( 10, has_filter( 'pre_get_posts', array( 'Tempus_Order_By', 'order_by' ) ) );
		$this->assertSame( 10, has_filter( 'document_title_parts', array( 'Tempus_Order_By', 'title_parts' ) ) );
		Tempus_Order_By::plugins_loaded();
		$this->assertSame( 'index.php?tempus_sort=$matches[1]&paged=$matches[2]', $wp_rewrite->extra_rules_top['(updated|oldest|random)/page/?([0-9]{1,})/?$'] );
	}

	public function test_random_archive() {
		self::factory()->post->create();
		$this->go_to( home_url( '/random/' ) );
		$this->assertSame( 'rand', $GLOBALS['wp_query']->get( 'orderby' ) );
		$this->assertSame( 'Random Posts', get_the_archive_title() );
		$this->assertStringStartsWith( 'Random Posts', wp_get_document_title() );
	}

	public function test_oldest_archive_titles() {
		self::factory()->post->create();
		$this->go_to( home_url( '/oldest/page/1/' ) );
		$this->assertSame( 'Oldest Posts', get_the_archive_title() );
		$this->assertStringStartsWith( 'Oldest Posts', wp_get_document_title() );
	}

	public function test_sorted_archive_feed() {
		self::factory()->post->create();
		$this->go_to( home_url( '/updated/feed/rss2/' ) );
		$this->assertTrue( is_feed() );
		$this->assertSame( 'updated', get_query_var( 'tempus_sort' ) );
	}

	public function test_sorted_archive_short_feed_url() {
		self::factory()->post->create();
		$this->go_to( home_url( '/updated/rss2/' ) );
		$this->assert_same_or_known_bug(
			array( 'rss2', 'updated' ),
			array( get_query_var( 'feed' ), get_query_var( 'tempus_sort' ) ),
			'the /updated/rss2/ style feed rule contains a double slash and never matches.'
		);
	}

	public function test_unknown_sort_keeps_title() {
		$this->assertSame( '', Tempus_Order_By::title( 'unknown' ) );
		$this->go_to( add_query_arg( 'tempus_sort', 'unknown', home_url( '/' ) ) );
		$this->assertSame( 'Archives', Tempus_Order_By::archive_title( 'Archives' ) );
	}
}
