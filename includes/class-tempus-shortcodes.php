<?php
/**
 * Shortcodes.
 *
 * @package TempusFugit
 * @since 1.2.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the `[tempus_onthisday]` and `[tempus_thisweek]` shortcodes.
 *
 * They show the same list as the On This Day and This Week widgets, for themes and pages
 * without widget areas. Attributes match the widget settings:
 *
 *     [tempus_onthisday title="On this day" number="5" taxonomy="category" term="travel"]
 *
 * @since 1.2.1
 */
class Tempus_Shortcodes {

	/**
	 * Registers the shortcodes.
	 *
	 * @since 1.2.1
	 */
	public static function register() {
		add_shortcode( 'tempus_onthisday', array( __CLASS__, 'onthisday' ) );
		add_shortcode( 'tempus_thisweek', array( __CLASS__, 'thisweek' ) );
	}

	/**
	 * Renders the `[tempus_onthisday]` shortcode.
	 *
	 * @since 1.2.1
	 *
	 * @param array|string $atts Shortcode attributes. See render().
	 * @return string Shortcode HTML.
	 */
	public static function onthisday( $atts ) {
		return self::render( new Tempus_OnThisDay_Widget(), $atts, 'tempus_onthisday' );
	}

	/**
	 * Renders the `[tempus_thisweek]` shortcode.
	 *
	 * @since 1.2.1
	 *
	 * @param array|string $atts Shortcode attributes. See render().
	 * @return string Shortcode HTML.
	 */
	public static function thisweek( $atts ) {
		return self::render( new Tempus_ThisWeek_Widget(), $atts, 'tempus_thisweek' );
	}

	/**
	 * Renders a widget's output for a shortcode.
	 *
	 * The attributes are sanitized the same way as widget settings.
	 *
	 * @since 1.2.1
	 *
	 * @param Tempus_OnThisDay_Widget $widget Widget to render.
	 * @param array|string            $atts {
	 *     Shortcode attributes.
	 *
	 *     @type string $title     Heading, linked to the archive. Default none.
	 *     @type int    $number    Number of posts. Default 5.
	 *     @type string $taxonomy  Taxonomy to limit posts to, such as 'category' or 'post_tag'.
	 *                             Default none.
	 *     @type string $term      Slug of the term to limit posts to. Default none.
	 *     @type string $nonefound Text shown when there are no posts. Default is the widget's text.
	 * }
	 * @param string                  $tag    Shortcode tag.
	 * @return string Shortcode HTML.
	 */
	private static function render( $widget, $atts, $tag ) {
		$atts     = shortcode_atts(
			array(
				'title'     => '',
				'number'    => 5,
				'taxonomy'  => '',
				'term'      => '',
				'nonefound' => null,
			),
			$atts,
			$tag
		);
		$instance = $widget->update( $atts, array() );
		// Use the widget's default text unless the shortcode sets its own.
		if ( null === $atts['nonefound'] ) {
			unset( $instance['nonefound'] );
		}

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '<div class="tempus-shortcode ' . esc_attr( $tag ) . '">',
				'after_widget'  => '</div>',
				'before_title'  => '<h2 class="tempus-shortcode-title">',
				'after_title'   => '</h2>',
			),
			$instance
		);
		return ob_get_clean();
	}
}
