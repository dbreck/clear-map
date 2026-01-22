<?php
/**
 * Frontend Display Handler
 *
 * Registers shortcodes and handles frontend map rendering.
 *
 * @package Clear_Map
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clear_Map_Frontend
 *
 * Handles shortcode registration and frontend display.
 *
 * @since 1.0.0
 */
class Clear_Map_Frontend {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_shortcode( 'clear_map', array( $this, 'render_map_shortcode' ) );
		// Backwards compatibility with old shortcode name.
		add_shortcode( 'the_andrea_map', array( $this, 'render_map_shortcode' ) );
	}

	/**
	 * Render the map shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered map HTML.
	 */
	public function render_map_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				// General.
				'height'                   => '',
				'el_id'                    => '',
				'el_class'                 => '',
				'css'                      => '',
				// Map Position.
				'center_lat'               => '',
				'center_lng'               => '',
				'zoom'                     => '',
				// Map Display.
				'cluster_distance'         => '',
				'cluster_min_points'       => '',
				'zoom_threshold'           => '',
				'show_subway_lines'        => '',
				// Filter Panel.
				'show_filters'             => '',
				'filters_width'            => '',
				'filters_height'           => '',
				'filters_bg_color'         => '',
				'filters_bg_transparent'   => '',
				'filters_show_header'      => '',
				'filters_style'            => '',
				'filters_pill_border'      => '',
				'filters_pill_border_color' => '',
				'filters_pill_bg'          => '',
				'filters_pill_bg_color'    => '',
				'filters_show_items'       => '',
			),
			$atts
		);

		// Check if required API keys are set.
		if ( empty( get_option( 'clear_map_mapbox_token' ) ) ) {
			return '<p>' . esc_html__( 'Map configuration required. Please set Mapbox token in admin.', 'clear-map' ) . '</p>';
		}

		wp_enqueue_script( 'mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js', array(), '2.15.0', true );
		wp_enqueue_style( 'mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css', array(), '2.15.0' );

		$renderer = new Clear_Map_Renderer();
		return $renderer->render( $atts );
	}
}
