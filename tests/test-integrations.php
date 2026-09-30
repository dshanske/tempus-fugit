<?php
/**
 * Tests for the optional Simple Location and Post Kinds integrations.
 *
 * Each test defines stand-in classes for the other plugins, so it runs in its own process to
 * keep those classes from affecting other tests.
 *
 * @package TempusFugit
 */

/**
 * Integration tests.
 */
class Test_Tempus_Integrations extends WP_UnitTestCase {
	use Tempus_Known_Bugs;

	/**
	 * Defines stand-in classes so class_exists() checks for other plugins pass.
	 *
	 * @param string[] $classes Class names.
	 */
	private function define_classes( $classes ) {
		foreach ( $classes as $class ) {
			if ( ! class_exists( $class, false ) ) {
				class_alias( 'stdClass', $class );
			}
		}
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_simple_location_and_post_kinds_rules() {
		global $wp_rewrite;
		$this->define_classes( array( 'Simple_Location_Plugin', 'Post_Kinds_Plugin' ) );
		Tempus_On_This_Day::rewrite_rules();
		Tempus_This_Week::rewrite_rules();
		$rules = $wp_rewrite->extra_rules_top;

		$this->assertSame( 'index.php?monthnum=$matches[1]&day=$matches[2]&paged=$matches[3]&map=1', $rules['onthisday/([0-9]{2})/([0-9]{2})/map/page/?([0-9]{1,})/?$'] );
		$this->assertSame( 'index.php?monthnum=$matches[1]&day=$matches[2]&map=1', $rules['onthisday/([0-9]{2})/([0-9]{2})/map/?$'] );
		$this->assertSame( 'index.php?onthisday=1&paged=$matches[1]&map=1', $rules['onthisday/map/page/?([0-9]{1,})/?$'] );
		$this->assertSame( 'index.php?onthisday=1&map=1', $rules['onthisday/map/?$'] );
		$this->assertSame( 'index.php?onthisday=1&kind_photos=1', $rules['onthisday/photos/?$'] );

		$this->assertSame( 'index.php?w=$matches[1]&paged=$matches[2]&map=1', $rules['thisweek/([0-9]{2})/map/page/?([0-9]{1,})/?$'] );
		$this->assertSame( 'index.php?w=$matches[1]&map=1', $rules['thisweek/([0-9]{2})/map/?$'] );
		$this->assertSame( 'index.php?thisweek=1&map=1', $rules['thisweek/map/?$'] );
		$this->assertSame( 'index.php?thisweek=1&kind_photos=1', $rules['thisweek/photos/?$'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_paginated_map_and_photo_rules_keep_page_number() {
		global $wp_rewrite;
		$this->define_classes( array( 'Simple_Location_Plugin', 'Post_Kinds_Plugin' ) );
		Tempus_On_This_Day::rewrite_rules();
		Tempus_This_Week::rewrite_rules();
		$rules = $wp_rewrite->extra_rules_top;

		$this->assert_same_or_known_bug(
			array(
				'index.php?onthisday=1&kind_photos=1&paged=$matches[1]',
				'index.php?thisweek=1&map=1&paged=$matches[1]',
				'index.php?thisweek=1&kind_photos=1&paged=$matches[1]',
			),
			array(
				$rules['onthisday/photos/page/?([0-9]{1,})/?$'],
				$rules['thisweek/map/page/?([0-9]{1,})/?$'],
				$rules['thisweek/photos/page/?([0-9]{1,})/?$'],
			),
			'some paginated map and photo rules drop the page number.'
		);
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_photo_rules_without_simple_location() {
		global $wp_rewrite;
		$this->define_classes( array( 'Post_Kinds_Plugin' ) );
		Tempus_On_This_Day::rewrite_rules();
		$this->assert_same_or_known_bug(
			true,
			isset( $wp_rewrite->extra_rules_top['onthisday/photos/?$'] ),
			'the Post Kinds photo rules are only registered when Simple Location is also active.'
		);
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_kind_permastructs() {
		global $wp_rewrite;
		$this->define_classes( array( 'Kind_Taxonomy' ) );
		Tempus_Day_Of_Year::rewrite_rules();
		Tempus_Week_Of_Year::rewrite_rules();
		$this->assertSame( 'kind/%kind%/%year%/%dayofyear%/', $wp_rewrite->extra_permastructs['kind_dayofyear']['struct'] );
		$this->assertSame( 'kind/%kind%/%year%/W%week%/', $wp_rewrite->extra_permastructs['kind_week']['struct'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_widgets_skipped_when_post_kinds_widget_exists() {
		global $wp_filter;
		$this->define_classes( array( 'Kind_OnThisDay_Widget' ) );
		$before = count( $wp_filter['widgets_init']->callbacks[10] );
		Tempus_Fugit_Plugin::plugins_loaded();
		$this->assertCount( $before, $wp_filter['widgets_init']->callbacks[10] );
	}
}
