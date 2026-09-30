<?php
/**
 * Shortcodes.
 *
 * @package TempusFugit
 * @since 1.2.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the `[tempus_onthisday]`, `[tempus_thisweek]`, and `[tempus_random]` shortcodes.
 *
 * They show the same list as the On This Day, This Week, and Random Memory widgets, for themes
 * and pages without widget areas. Attributes match the widget settings:
 *
 *     [tempus_onthisday title="On this day" number="5" taxonomy="category" term="travel"]
 *     [tempus_random period="week" taxonomy="post_tag" term="family"]
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
		add_shortcode( 'tempus_random', array( __CLASS__, 'random' ) );
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
	 * Renders the `[tempus_random]` shortcode.
	 *
	 * Takes a `period` attribute ('all', 'day', or 'week'; default 'all') in addition to the
	 * attributes in render(). `number` defaults to 1.
	 *
	 * @since 1.2.1
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Shortcode HTML.
	 */
	public static function random( $atts ) {
		return self::render( new Tempus_Random_Widget(), $atts, 'tempus_random' );
	}

	/**
	 * Renders a widget's output for a shortcode.
	 *
	 * The attributes and their defaults come from the widget's settings, and are sanitized the
	 * same way.
	 *
	 * @since 1.2.1
	 *
	 * @param Tempus_OnThisDay_Widget $widget Widget to render.
	 * @param array|string            $atts {
	 *     Shortcode attributes.
	 *
	 *     @type string $title     Heading, linked to the archive. Default none.
	 *     @type int    $number    Number of posts. Default is the widget's: 5, or 1 for random.
	 *     @type string $taxonomy  Taxonomy to limit posts to, such as 'category' or 'post_tag'.
	 *                             Default none.
	 *     @type string $term      Slug of the term to limit posts to. Default none.
	 *     @type string $nonefound Text shown when there are no posts. Default is the widget's text.
	 * }
	 * @param string                  $tag    Shortcode tag.
	 * @return string Shortcode HTML.
	 */
	private static function render( $widget, $atts, $tag ) {
		$defaults              = $widget->defaults( array() );
		$defaults['nonefound'] = null;
		$atts                  = shortcode_atts( $defaults, $atts, $tag );
		$instance              = $widget->update( $atts, array() );
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
