<?php
/**
 * Minor updates.
 *
 * @package TempusFugit
 * @since 1.2.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds a "Minor update" checkbox to the classic editor, which saves a post without changing its
 * last-modified date.
 *
 * Useful for typo fixes that shouldn't move a post up the `/updated` archive or change its
 * "last updated" date. The checkbox is in the Publish box of the classic editor (ClassicPress, or
 * WordPress with the Classic Editor plugin); the block editor doesn't show it.
 *
 * @since 1.2.1
 */
class Tempus_Minor_Update {
	/**
	 * Name of the checkbox field.
	 *
	 * @since 1.2.1
	 *
	 * @var string
	 */
	const FIELD = 'tempus_minor_update';

	/**
	 * Name of the nonce field.
	 *
	 * @since 1.2.1
	 *
	 * @var string
	 */
	const NONCE = 'tempus_minor_update_nonce';

	/**
	 * Registers the hooks for minor updates.
	 *
	 * @since 1.2.1
	 */
	public function __construct() {
		add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'checkbox' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'keep_modified_date' ), 10, 2 );
	}

	/**
	 * Outputs the "Minor update" checkbox in the classic editor's Publish box.
	 *
	 * Only shown for published and private posts, since only their last-modified date is shown
	 * to visitors.
	 *
	 * Hooked to `post_submitbox_misc_actions`.
	 *
	 * @since 1.2.1
	 *
	 * @param WP_Post $post The post being edited.
	 */
	public static function checkbox( $post ) {
		if ( ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
			return;
		}
		wp_nonce_field( self::nonce_action( $post->ID ), self::NONCE );
		?>
		<div class="misc-pub-section misc-pub-tempus-minor-update">
			<label><input type="checkbox" name="<?php echo esc_attr( self::FIELD ); ?>" value="1" /> <?php esc_html_e( 'Minor update (keep the last updated date)', 'tempus-fugit' ); ?></label>
		</div>
		<?php
	}

	/**
	 * Keeps a post's last-modified date when it is saved as a minor update.
	 *
	 * Only applies to the post submitted from the edit form, when the checkbox is checked, the
	 * nonce is valid, and the user can edit the post. Revisions and other posts saved in the same
	 * request are unaffected.
	 *
	 * Hooked to `wp_insert_post_data`.
	 *
	 * @since 1.2.1
	 *
	 * @param array $data    Sanitized post data about to be saved.
	 * @param array $postarr Post data passed to wp_insert_post().
	 * @return array Post data.
	 */
	public static function keep_modified_date( $data, $postarr ) {
		$post_id = empty( $postarr['ID'] ) ? 0 : (int) $postarr['ID'];
		if ( ! $post_id || empty( $_POST[ self::FIELD ] ) || empty( $_POST['post_ID'] ) || (int) $_POST['post_ID'] !== $post_id ) {
			return $data;
		}
		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::nonce_action( $post_id ) ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return $data;
		}
		$post = get_post( $post_id );
		if ( $post ) {
			$data['post_modified']     = $post->post_modified;
			$data['post_modified_gmt'] = $post->post_modified_gmt;
		}
		return $data;
	}

	/**
	 * Returns the nonce action for a post.
	 *
	 * @since 1.2.1
	 *
	 * @param int $post_id Post ID.
	 * @return string Nonce action.
	 */
	private static function nonce_action( $post_id ) {
		return 'tempus_minor_update_' . $post_id;
	}
}
