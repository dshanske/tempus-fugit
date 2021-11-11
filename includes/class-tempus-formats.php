<?php
/**
 * Time Formats
 *
 * This Allows for Automatic Changing of the Date and Time Functionality Default Format Based on Various Factors.
 */
class Tempus_Formats {
	public function __construct() {
		add_filter( 'option_date_format', array( __CLASS__, 'date_format' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
	}

	public static function admin_init() {
	}

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
			// If this is a day archive.
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


