<?php
/**
 * Date formats.
 *
 * @package TempusFugit
 * @since 1.0.9
 */

/**
 * Changes the default date format on date archives to suit the archive type.
 *
 * Not currently loaded by the plugin.
 *
 * @since 1.0.9
 */
class Tempus_Formats {
	/**
	 * Registers the date format hooks.
	 *
	 * @since 1.0.9
	 */
	public function __construct() {
		add_filter( 'option_date_format', array( __CLASS__, 'date_format' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
	}

	/**
	 * Placeholder for admin settings. Currently does nothing.
	 *
	 * Hooked to `admin_init`.
	 *
	 * @since 1.0.9
	 */
	public static function admin_init() {
	}

	/**
	 * Returns a date format suited to the date archive being viewed.
	 *
	 * Hooked to `option_date_format`. Leaves the format unchanged in the admin and outside
	 * date archives.
	 *
	 * @since 1.0.9
	 *
	 * @param string $value The site's date format.
	 * @return string Date format to use.
	 */
	public static function date_format( $value ) {
		$old_value = $value;

		if ( is_admin() ) {
			return $value;
		}

		// This is only used for date archives.
		if ( ! is_date() ) {
			return $value;
		}
		// If this is a On This Week archive.
		if ( empty( get_query_var( 'year' ) ) && empty( get_query_var( 'monthnum' ) ) && ! empty( get_query_var( 'w' ) ) ) {
			return $value;
			// Otherwise, if this is a day archive.
		} elseif ( is_day() ) {
			// If this is an On This Day Archive.
			if ( empty( get_query_var( 'year' ) ) ) {
				$value = 'Y';
			} else {
				$value = get_option( 'time_format' );
			}
		} elseif ( is_year() ) {
			$value = get_option( 'month_format', 'M d' );
		} elseif ( is_month() ) {
			$value = get_option( 'month_format', 'M d' );
		}
		return $value;
	}


	/**
	 * Outputs radio buttons for choosing a date format on a settings screen.
	 *
	 * @since 1.0.9
	 *
	 * @param array $args {
	 *     Field arguments.
	 *
	 *     @type string   $label_for    Name of the option being set.
	 *     @type string[] $date_formats Date formats to offer.
	 * }
	 */
	public static function date_format_callback( $args ) {
		?>
			<?php
				$custom = true;
			foreach ( $args['date_formats'] as $format ) {
				echo "\t<label><input type='radio' name='" . esc_attr( $args['label_for'] ) . "' value='" . esc_attr( $format ) . "'";
				if ( get_option( $args['label_for'] ) === $format ) { // checked() uses "==" rather than "===".
					echo " checked='checked'";
					$custom = false;
				}
				echo ' /> <span class="date-time-text format-i18n">' . esc_html( date_i18n( $format ) ) . '</span><code>' . esc_html( $format ) . "</code></label><br />\n";
			}
			?>
		<?php
	}
}


