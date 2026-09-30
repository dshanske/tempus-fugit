<?php
/**
 * Tests for the minor update checkbox.
 *
 * @package TempusFugit
 */

/**
 * Minor update tests.
 */
class Test_Tempus_Minor_Update extends WP_UnitTestCase {

	/**
	 * The last-modified date the tests start from.
	 *
	 * @var string
	 */
	const OLD_MODIFIED = '2020-01-01 10:00:00';

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	private $post;

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->post = $this->create_post_modified_at( self::OLD_MODIFIED );
	}

	public function tear_down() {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Creates a published post with a given last-modified date.
	 *
	 * @param string $modified Last-modified date.
	 * @return int Post ID.
	 */
	private function create_post_modified_at( $modified ) {
		global $wpdb;
		$post = self::factory()->post->create( array( 'post_date' => '2019-06-01 12:00:00' ) );
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => $modified,
				'post_modified_gmt' => $modified,
			),
			array( 'ID' => $post )
		);
		clean_post_cache( $post );
		return $post;
	}

	/**
	 * Simulates submitting the edit form for a post.
	 *
	 * @param int  $post_id Post ID in the form.
	 * @param bool $minor   Whether the checkbox is checked.
	 * @param bool $nonce   Whether to include a valid nonce.
	 */
	private function submit_form( $post_id, $minor = true, $nonce = true ) {
		$_POST = array( 'post_ID' => $post_id );
		if ( $minor ) {
			$_POST[ Tempus_Minor_Update::FIELD ] = '1';
		}
		$_POST[ Tempus_Minor_Update::NONCE ] = $nonce ? wp_create_nonce( 'tempus_minor_update_' . $post_id ) : 'invalid';
	}

	/**
	 * Updates a post's title, as saving the edit form would.
	 *
	 * @param int $post_id Post ID.
	 * @return string The post's last-modified date afterwards.
	 */
	private function update_post( $post_id ) {
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Fixed a typo',
			)
		);
		return get_post( $post_id )->post_modified;
	}

	public function test_hooks_are_registered() {
		new Tempus_Minor_Update();
		$this->assertSame( 10, has_action( 'post_submitbox_misc_actions', array( 'Tempus_Minor_Update', 'checkbox' ) ) );
		$this->assertSame( 10, has_filter( 'wp_insert_post_data', array( 'Tempus_Minor_Update', 'keep_modified_date' ) ) );
	}

	public function test_checkbox_on_published_posts() {
		ob_start();
		Tempus_Minor_Update::checkbox( get_post( $this->post ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( '<input type="checkbox" name="tempus_minor_update" value="1" />', $html );
		$this->assertStringContainsString( 'Minor update (keep the last updated date)', $html );
		$this->assertStringContainsString( 'name="tempus_minor_update_nonce"', $html );
	}

	public function test_no_checkbox_on_drafts() {
		ob_start();
		Tempus_Minor_Update::checkbox( get_post( self::factory()->post->create( array( 'post_status' => 'draft' ) ) ) );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_minor_update_keeps_the_modified_date() {
		$this->submit_form( $this->post );
		$this->assertSame( self::OLD_MODIFIED, $this->update_post( $this->post ) );
		$this->assertSame( self::OLD_MODIFIED, get_post( $this->post )->post_modified_gmt );
		$this->assertSame( 'Fixed a typo', get_the_title( $this->post ) );
	}

	public function test_normal_update_changes_the_modified_date() {
		$this->submit_form( $this->post, false );
		$this->assertNotSame( self::OLD_MODIFIED, $this->update_post( $this->post ) );
	}

	public function test_invalid_nonce_changes_the_modified_date() {
		$this->submit_form( $this->post, true, false );
		$this->assertNotSame( self::OLD_MODIFIED, $this->update_post( $this->post ) );
	}

	public function test_user_who_cannot_edit_changes_the_modified_date() {
		$this->submit_form( $this->post );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertNotSame( self::OLD_MODIFIED, $this->update_post( $this->post ) );
	}

	public function test_only_the_submitted_post_is_affected() {
		$other = $this->create_post_modified_at( self::OLD_MODIFIED );
		$this->submit_form( $this->post );
		$this->assertNotSame( self::OLD_MODIFIED, $this->update_post( $other ) );
	}

	public function test_new_posts_are_not_affected() {
		$this->submit_form( $this->post );
		$data = Tempus_Minor_Update::keep_modified_date( array( 'post_modified' => '2026-01-01 00:00:00' ), array( 'post_title' => 'New' ) );
		$this->assertSame( '2026-01-01 00:00:00', $data['post_modified'] );
	}

	public function test_minor_update_keeps_position_in_updated_archive() {
		$newer = $this->create_post_modified_at( '2021-01-01 10:00:00' );
		$this->submit_form( $this->post );
		$this->update_post( $this->post );

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		$this->go_to( home_url( '/updated/' ) );
		$this->assertSame( array( $newer, $this->post ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}
}
