<?php
/**
 * Tests for widget cache invalidation and uninstalling.
 *
 * @package TempusFugit
 */

require_once dirname( __DIR__ ) . '/includes/uninstall-functions.php';

/**
 * Cache and uninstall tests.
 */
class Test_Tempus_Cache_And_Uninstall extends WP_UnitTestCase {

	/**
	 * Returns the widget cache version.
	 *
	 * @return int Cache version.
	 */
	private function cache_version() {
		return (int) get_option( Tempus_OnThisDay_Widget::CACHE_VERSION_OPTION, 0 );
	}

	/**
	 * Counts rows in the options table whose name starts with a prefix.
	 *
	 * @param string $prefix Option name prefix.
	 * @return int Number of options.
	 */
	private function count_options( $prefix ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );
	}

	public function test_cache_hooks_are_registered() {
		Tempus_OnThisDay_Widget::register_cache_hooks();
		$this->assertSame( 10, has_action( 'transition_post_status', array( 'Tempus_OnThisDay_Widget', 'transition_post_status' ) ) );
		$this->assertSame( 10, has_action( 'deleted_post', array( 'Tempus_OnThisDay_Widget', 'deleted_post' ) ) );
		$this->assertSame( 10, has_action( 'set_object_terms', array( 'Tempus_OnThisDay_Widget', 'set_object_terms' ) ) );
	}

	public function test_published_post_changes_invalidate_the_cache() {
		$version = $this->cache_version();
		$post    = self::factory()->post->create();
		$this->assertGreaterThan( $version, $this->cache_version(), 'Publishing.' );

		$version = $this->cache_version();
		wp_update_post(
			array(
				'ID'         => $post,
				'post_title' => 'Updated',
			)
		);
		$this->assertGreaterThan( $version, $this->cache_version(), 'Updating.' );

		$version = $this->cache_version();
		wp_set_object_terms( $post, 'new-tag', 'post_tag' );
		$this->assertGreaterThan( $version, $this->cache_version(), 'Changing terms.' );

		$version = $this->cache_version();
		wp_trash_post( $post );
		$this->assertGreaterThan( $version, $this->cache_version(), 'Trashing.' );

		$published = self::factory()->post->create();
		$version   = $this->cache_version();
		wp_delete_post( $published, true );
		$this->assertGreaterThan( $version, $this->cache_version(), 'Deleting without the trash.' );
	}

	public function test_draft_changes_keep_the_cache() {
		$version = $this->cache_version();
		$draft   = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		wp_set_object_terms( $draft, 'new-tag', 'post_tag' );
		wp_delete_post( $draft, true );
		$this->assertSame( $version, $this->cache_version() );
	}

	public function test_widget_shows_new_posts_immediately() {
		$widget = new Tempus_OnThisDay_Widget();
		$widget->_set( 1 );
		$args = array(
			'before_widget' => '',
			'after_widget'  => '',
			'before_title'  => '',
			'after_title'   => '',
		);

		ob_start();
		$widget->widget( $args, array() );
		$this->assertStringContainsString( 'There were no posts on this day', ob_get_clean() );

		$date = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '-4 years' )->setTime( 12, 0 );
		self::factory()->post->create(
			array(
				'post_title' => 'Newly published',
				'post_date'  => $date->format( 'Y-m-d H:i:s' ),
			)
		);
		ob_start();
		$widget->widget( $args, array() );
		$this->assertStringContainsString( 'Newly published', ob_get_clean() );
	}

	public function test_uninstall_removes_plugin_data() {
		update_option( 'widget_tempus_onthisday_widget', array( 2 => array( 'title' => 'Memories' ) ) );
		update_option( 'widget_tempus_thisweek_widget', array( 2 => array( 'title' => 'Weeks' ) ) );
		Tempus_OnThisDay_Widget::flush_cache();
		set_transient( 'tempus_widget_abc', array( 1 ), HOUR_IN_SECONDS );
		set_transient( 'onthisday_widget09-30', array( 1 ), HOUR_IN_SECONDS );
		set_transient( 'thisweek_widget3', array( 1 ), HOUR_IN_SECONDS );
		set_transient( 'another_plugin', array( 1 ), HOUR_IN_SECONDS );

		tempus_fugit_uninstall();

		$this->assertFalse( get_option( 'widget_tempus_onthisday_widget' ) );
		$this->assertFalse( get_option( 'widget_tempus_thisweek_widget' ) );
		$this->assertFalse( get_option( Tempus_OnThisDay_Widget::CACHE_VERSION_OPTION ) );
		$this->assertFalse( get_option( 'rewrite_rules' ) );
		foreach ( array( 'tempus_widget_', 'onthisday_widget', 'thisweek_widget' ) as $prefix ) {
			$this->assertSame( 0, $this->count_options( '_transient_' . $prefix ), $prefix );
			$this->assertSame( 0, $this->count_options( '_transient_timeout_' . $prefix ), $prefix );
		}
		$this->assertSame( 1, $this->count_options( '_transient_another_plugin' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_uninstall_file() {
		update_option( 'widget_tempus_onthisday_widget', array( 2 => array( 'title' => 'Memories' ) ) );
		define( 'WP_UNINSTALL_PLUGIN', 'tempus-fugit/tempus-fugit.php' );
		require dirname( __DIR__ ) . '/uninstall.php';
		$this->assertFalse( get_option( 'widget_tempus_onthisday_widget' ) );
	}

	public function test_uninstall_every_site_on_multisite() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Needs multisite.' );
		}
		$site = self::factory()->blog->create();
		update_option( 'widget_tempus_onthisday_widget', array( 2 => array( 'title' => 'Main' ) ) );
		switch_to_blog( $site );
		update_option( 'widget_tempus_onthisday_widget', array( 2 => array( 'title' => 'Other' ) ) );
		restore_current_blog();

		tempus_fugit_uninstall();

		$this->assertFalse( get_option( 'widget_tempus_onthisday_widget' ) );
		switch_to_blog( $site );
		$this->assertFalse( get_option( 'widget_tempus_onthisday_widget' ) );
		restore_current_blog();
	}
}
