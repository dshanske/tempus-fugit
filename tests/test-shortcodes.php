<?php
/**
 * Tests for the On This Day and This Week shortcodes.
 *
 * @package TempusFugit
 */

/**
 * Shortcode tests.
 */
class Test_Tempus_Shortcodes extends WP_UnitTestCase {

	/**
	 * Today's date in the site timezone.
	 *
	 * @var DateTimeImmutable
	 */
	private $today;

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		$this->today = new DateTimeImmutable( 'now', wp_timezone() );
	}

	/**
	 * Creates a post on today's date a number of years ago.
	 *
	 * @param string $title Post title.
	 * @param int    $years Years ago.
	 * @param array  $args  Optional. More post fields.
	 * @return int Post ID.
	 */
	private function create_post( $title, $years, $args = array() ) {
		return self::factory()->post->create(
			$args + array(
				'post_title' => $title,
				'post_date'  => $this->today->modify( "-{$years} years" )->setTime( 12, 0 )->format( 'Y-m-d H:i:s' ),
			)
		);
	}

	public function test_shortcodes_are_registered() {
		Tempus_Shortcodes::register();
		$this->assertTrue( shortcode_exists( 'tempus_onthisday' ) );
		$this->assertTrue( shortcode_exists( 'tempus_thisweek' ) );
	}

	public function test_onthisday_shortcode() {
		$this->create_post( 'Four years back', 4 );
		$output = do_shortcode( '[tempus_onthisday title="Memories"]' );
		$this->assertStringStartsWith( '<div class="tempus-shortcode tempus_onthisday">', $output );
		$this->assertStringContainsString( '<h2 class="tempus-shortcode-title"><a href="' . Tempus_On_This_Day::get_link() . '">Memories</a></h2>', $output );
		$this->assertStringContainsString( 'Four years back', $output );
		$this->assertStringEndsWith( '</div>', $output );
	}

	public function test_shortcode_without_title_has_no_heading() {
		$this->create_post( 'Four years back', 4 );
		$this->assertStringNotContainsString( '<h2', do_shortcode( '[tempus_onthisday]' ) );
	}

	public function test_shortcode_number() {
		$this->create_post( 'Four years back', 4 );
		$this->create_post( 'Five years back', 5 );
		$output = do_shortcode( '[tempus_onthisday number="1"]' );
		$this->assertSame( 1, substr_count( $output, 'years back' ) );
	}

	public function test_shortcode_limited_to_term() {
		$this->create_post( 'Tagged', 4, array( 'tags_input' => array( 'foo' ) ) );
		$this->create_post( 'Untagged', 4 );
		$output = do_shortcode( '[tempus_onthisday taxonomy="post_tag" term="foo"]' );
		$this->assertStringContainsString( 'Tagged', $output );
		$this->assertStringNotContainsString( 'Untagged', $output );
	}

	public function test_shortcode_ignores_unknown_taxonomy() {
		$this->create_post( 'Untagged', 4 );
		$this->assertStringContainsString( 'Untagged', do_shortcode( '[tempus_onthisday taxonomy="nope" term="foo"]' ) );
	}

	public function test_shortcode_no_posts_text() {
		$this->assertStringContainsString( 'There were no posts on this day in previous years', do_shortcode( '[tempus_onthisday]' ) );
		$this->assertStringContainsString( 'Nothing yet', do_shortcode( '[tempus_onthisday nonefound="Nothing yet"]' ) );
		$this->assertStringContainsString( 'There were no posts on this week in previous years', do_shortcode( '[tempus_thisweek]' ) );
	}

	public function test_thisweek_shortcode() {
		$week = (int) $this->today->format( 'W' );
		$year = (int) $this->today->format( 'o' ) - 4;
		while ( (int) $this->today->setISODate( $year, $week, 3 )->format( 'W' ) !== $week ) {
			--$year;
		}
		self::factory()->post->create(
			array(
				'post_title' => 'Same week',
				'post_date'  => $this->today->setISODate( $year, $week, 3 )->setTime( 12, 0 )->format( 'Y-m-d H:i:s' ),
			)
		);
		$output = do_shortcode( '[tempus_thisweek title="Weeks"]' );
		$this->assertStringStartsWith( '<div class="tempus-shortcode tempus_thisweek">', $output );
		$this->assertStringContainsString( '<a href="' . Tempus_This_Week::get_link() . '">Weeks</a>', $output );
		$this->assertStringContainsString( 'Same week', $output );
	}

	public function test_shortcode_title_is_sanitized() {
		$this->create_post( 'Four years back', 4 );
		$output = do_shortcode( '[tempus_onthisday title="<script>alert(1)</script>Memories"]' );
		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( '>Memories</a>', $output );
	}
}
