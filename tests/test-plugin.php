<?php
/**
 * Tests for plugin bootstrap, query vars, rewrite rules, and sorting.
 *
 * @package TempusFugit
 */

/**
 * Plugin bootstrap tests.
 */
class Test_Tempus_Plugin extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
	}

	public function test_query_vars_are_registered() {
		$query_vars = apply_filters( 'query_vars', array() );
		foreach ( array( 'dayofyear', 'tempus_week', 'tempus_sort', 'onthisday', 'thisweek' ) as $var ) {
			$this->assertContains( $var, $query_vars );
		}
		$this->assertNotContains( 'sort', $query_vars );
		$this->assertNotContains( 'week', $query_vars );
	}

	public function test_rewrite_rules_include_plugin_archives() {
		$rules = get_option( 'rewrite_rules' );
		$this->assertArrayHasKey( 'onthisday/?$', $rules );
		$this->assertArrayHasKey( 'thisweek/?$', $rules );
		$this->assertArrayHasKey( '(updated|oldest|random)/?$', $rules );
		$this->assertSame( 'index.php?tempus_sort=$matches[1]', $rules['(updated|oldest|random)/?$'] );
	}

	public function test_activation_and_deactivation_reset_rewrite_rules() {
		update_option( 'rewrite_rules', array( 'stale' => 'index.php' ) );
		Tempus_Fugit_Plugin::activate();
		$this->assertFalse( get_option( 'rewrite_rules' ) );

		update_option( 'rewrite_rules', array( 'stale' => 'index.php' ) );
		Tempus_Fugit_Plugin::deactivate();
		$this->assertFalse( get_option( 'rewrite_rules' ) );
	}

	public function test_updated_archive_orders_by_modified() {
		self::factory()->post->create();
		$this->go_to( home_url( '/updated/' ) );
		$this->assertTrue( is_archive() );
		$this->assertSame( 'updated', get_query_var( 'tempus_sort' ) );
		$this->assertSame( 'modified', $GLOBALS['wp_query']->get( 'orderby' ) );
		$this->assertSame( 'Last Updated', Tempus_Order_By::archive_title( 'Archives' ) );
	}

	public function test_oldest_archive_lists_oldest_first() {
		$old = self::factory()->post->create( array( 'post_date' => '2010-01-01 00:00:00' ) );
		$new = self::factory()->post->create( array( 'post_date' => '2020-01-01 00:00:00' ) );
		$this->go_to( home_url( '/oldest/' ) );
		$this->assertSame( 'ASC', $GLOBALS['wp_query']->get( 'order' ) );
		$this->assertSame( array( $old, $new ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	public function test_dated_archives_list_oldest_first() {
		$first  = self::factory()->post->create( array( 'post_date' => '2020-02-01 00:00:00' ) );
		$second = self::factory()->post->create( array( 'post_date' => '2020-06-01 00:00:00' ) );
		$this->go_to( home_url( '/2020/' ) );
		$this->assertTrue( is_year() );
		$this->assertSame( array( $first, $second ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}
}
