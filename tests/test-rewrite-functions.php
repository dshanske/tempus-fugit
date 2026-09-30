<?php
/**
 * Tests for the rewrite helper functions.
 *
 * @package TempusFugit
 */

/**
 * Rewrite helper tests.
 */
class Test_Tempus_Rewrite_Functions extends WP_UnitTestCase {

	public function test_get_feeds_returns_registered_feeds() {
		$this->assertSame( $GLOBALS['wp_rewrite']->feeds, tempus_get_feeds() );
		$this->assertContains( 'rss2', tempus_get_feeds() );
	}

	public function test_feed_regex_with_base() {
		$feeds = implode( '|', $GLOBALS['wp_rewrite']->feeds );
		$this->assertSame( 'feed/(' . $feeds . ')/?$', tempus_get_feed_regex() );
	}

	public function test_feed_regex_without_base() {
		$feeds = implode( '|', $GLOBALS['wp_rewrite']->feeds );
		$this->assertSame( '/(' . $feeds . ')/?$', tempus_get_feed_regex( false ) );
	}

	public function test_pagination_regex() {
		$this->assertSame( 'page/?([0-9]{1,})/?$', tempus_get_pagination_regex() );
	}

	public function test_generate_permastruct() {
		$this->assertSame( 'a/b/?$', tempus_generate_permastruct( array( 'a', 'b' ) ) );
		$this->assertSame( '', tempus_generate_permastruct( array() ) );
		$this->assertSame( '', tempus_generate_permastruct( 'not an array' ) );
	}
}
