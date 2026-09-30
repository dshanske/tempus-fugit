<?php
/**
 * Tests for Tempus_Formats, which the plugin does not currently load.
 *
 * @package TempusFugit
 */

require_once dirname( __DIR__ ) . '/includes/class-tempus-formats.php';

/**
 * Date format tests.
 */
class Test_Tempus_Formats extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
	}

	public function tear_down() {
		remove_filter( 'option_date_format', array( 'Tempus_Formats', 'date_format' ) );
		remove_action( 'admin_init', array( 'Tempus_Formats', 'admin_init' ) );
		unset( $GLOBALS['current_screen'] );
		parent::tear_down();
	}

	public function test_registers_hooks() {
		new Tempus_Formats();
		$this->assertSame( 10, has_filter( 'option_date_format', array( 'Tempus_Formats', 'date_format' ) ) );
		$this->assertSame( 10, has_action( 'admin_init', array( 'Tempus_Formats', 'admin_init' ) ) );
		$this->assertNull( Tempus_Formats::admin_init() );
	}

	public function test_unchanged_outside_date_archives() {
		$this->go_to( home_url( '/' ) );
		$this->assertSame( 'F j, Y', Tempus_Formats::date_format( 'F j, Y' ) );

		set_current_screen( 'edit.php' );
		$this->assertSame( 'F j, Y', Tempus_Formats::date_format( 'F j, Y' ) );
	}

	/**
	 * @dataProvider data_formats
	 *
	 * @param string $url      Archive URL path.
	 * @param string $expected Expected date format.
	 */
	public function test_format_by_archive_type( $url, $expected ) {
		self::factory()->post->create( array( 'post_date' => '2019-03-15 12:00:00' ) );
		$this->go_to( home_url( $url ) );
		$this->assertSame( $expected, Tempus_Formats::date_format( 'F j, Y' ) );
	}

	public function data_formats() {
		return array(
			'day archive'   => array( '/2019/03/15/', 'g:i a' ),
			'on this day'   => array( '/onthisday/03/15/', 'Y' ),
			'month archive' => array( '/2019/03/', 'M d' ),
			'year archive'  => array( '/2019/', 'M d' ),
			'this week'     => array( '/thisweek/11/', 'F j, Y' ),
		);
	}

	public function test_date_format_callback() {
		update_option( 'my_date_format', 'Y-m-d' );
		ob_start();
		Tempus_Formats::date_format_callback(
			array(
				'label_for'    => 'my_date_format',
				'date_formats' => array( 'F j, Y', 'Y-m-d' ),
			)
		);
		$html = ob_get_clean();
		$this->assertSame( 2, substr_count( $html, "type='radio' name='my_date_format'" ) );
		$this->assertStringContainsString( "value='Y-m-d' checked='checked'", $html );
		$this->assertStringNotContainsString( "value='F j, Y' checked='checked'", $html );
	}
}
