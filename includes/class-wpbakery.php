<?php
/**
 * WPBakery Page Builder Integration
 *
 * Registers Clear Map as a WPBakery element for easy page building.
 *
 * @package Clear_Map
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clear_Map_WPBakery
 *
 * Integrates Clear Map with WPBakery Page Builder.
 *
 * @since 1.3.0
 */
class Clear_Map_WPBakery {

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {
		add_action( 'vc_before_init', array( $this, 'register_element' ) );
		add_action( 'vc_before_init', array( $this, 'register_custom_params' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin assets for WPBakery custom params.
	 *
	 * @since 1.7.0
	 *
	 * @param string $hook The current admin page.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on post edit screens where WPBakery might be active.
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'clear-map-wpbakery',
			CLEAR_MAP_PLUGIN_URL . 'assets/css/wpbakery-admin.css',
			array(),
			CLEAR_MAP_VERSION
		);

		wp_enqueue_script(
			'clear-map-wpbakery',
			CLEAR_MAP_PLUGIN_URL . 'assets/js/wpbakery-admin.js',
			array( 'jquery' ),
			CLEAR_MAP_VERSION,
			true
		);
	}

	/**
	 * Register custom WPBakery param types.
	 *
	 * @since 1.7.0
	 *
	 * @return void
	 */
	public function register_custom_params() {
		if ( ! function_exists( 'vc_add_shortcode_param' ) ) {
			return;
		}

		vc_add_shortcode_param( 'responsive_textfield', array( $this, 'responsive_textfield_param' ) );
		vc_add_shortcode_param( 'responsive_dropdown', array( $this, 'responsive_dropdown_param' ) );
	}

	/**
	 * Render responsive textfield param.
	 *
	 * @since 1.7.0
	 *
	 * @param array  $settings Param settings.
	 * @param string $value    Current value (pipe-separated: desktop|tablet|mobile).
	 * @return string HTML output.
	 */
	public function responsive_textfield_param( $settings, $value ) {
		$values  = explode( '|', $value );
		$desktop = isset( $values[0] ) ? $values[0] : '';
		$tablet  = isset( $values[1] ) ? $values[1] : '';
		$mobile  = isset( $values[2] ) ? $values[2] : '';

		$output = '<div class="clear-map-responsive-field" data-param-name="' . esc_attr( $settings['param_name'] ) . '">';
		$output .= '<div class="device-toggles">';
		$output .= '<button type="button" class="device-btn active" data-device="desktop" title="' . esc_attr__( 'Desktop', 'clear-map' ) . '">';
		$output .= '<span class="dashicons dashicons-desktop"></span>';
		$output .= '</button>';
		$output .= '<button type="button" class="device-btn" data-device="tablet" title="' . esc_attr__( 'Tablet', 'clear-map' ) . '">';
		$output .= '<span class="dashicons dashicons-tablet"></span>';
		$output .= '</button>';
		$output .= '<button type="button" class="device-btn" data-device="mobile" title="' . esc_attr__( 'Mobile', 'clear-map' ) . '">';
		$output .= '<span class="dashicons dashicons-smartphone"></span>';
		$output .= '</button>';
		$output .= '</div>';

		$output .= '<input type="text" class="wpb_vc_param_value device-input" data-device="desktop" value="' . esc_attr( $desktop ) . '" />';
		$output .= '<input type="text" class="device-input" data-device="tablet" value="' . esc_attr( $tablet ) . '" style="display:none;" />';
		$output .= '<input type="text" class="device-input" data-device="mobile" value="' . esc_attr( $mobile ) . '" style="display:none;" />';
		$output .= '<input type="hidden" name="' . esc_attr( $settings['param_name'] ) . '" class="responsive-combined-value wpb_vc_param_value ' . esc_attr( $settings['param_name'] ) . ' ' . esc_attr( $settings['type'] ) . '" value="' . esc_attr( $value ) . '" />';

		$output .= '</div>';

		return $output;
	}

	/**
	 * Render responsive dropdown param.
	 *
	 * @since 1.7.0
	 *
	 * @param array  $settings Param settings.
	 * @param string $value    Current value (pipe-separated: desktop|tablet|mobile).
	 * @return string HTML output.
	 */
	public function responsive_dropdown_param( $settings, $value ) {
		$values  = explode( '|', $value );
		$desktop = isset( $values[0] ) ? $values[0] : '';
		$tablet  = isset( $values[1] ) ? $values[1] : '';
		$mobile  = isset( $values[2] ) ? $values[2] : '';

		$options = isset( $settings['value'] ) ? $settings['value'] : array();

		$output = '<div class="clear-map-responsive-field" data-param-name="' . esc_attr( $settings['param_name'] ) . '">';
		$output .= '<div class="device-toggles">';
		$output .= '<button type="button" class="device-btn active" data-device="desktop" title="' . esc_attr__( 'Desktop', 'clear-map' ) . '">';
		$output .= '<span class="dashicons dashicons-desktop"></span>';
		$output .= '</button>';
		$output .= '<button type="button" class="device-btn" data-device="tablet" title="' . esc_attr__( 'Tablet', 'clear-map' ) . '">';
		$output .= '<span class="dashicons dashicons-tablet"></span>';
		$output .= '</button>';
		$output .= '<button type="button" class="device-btn" data-device="mobile" title="' . esc_attr__( 'Mobile', 'clear-map' ) . '">';
		$output .= '<span class="dashicons dashicons-smartphone"></span>';
		$output .= '</button>';
		$output .= '</div>';

		// Desktop select.
		$output .= '<select class="device-input" data-device="desktop">';
		foreach ( $options as $label => $opt_value ) {
			$selected = ( $opt_value === $desktop ) ? ' selected="selected"' : '';
			$output .= '<option value="' . esc_attr( $opt_value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
		}
		$output .= '</select>';

		// Tablet select.
		$output .= '<select class="device-input" data-device="tablet" style="display:none;">';
		foreach ( $options as $label => $opt_value ) {
			$selected = ( $opt_value === $tablet ) ? ' selected="selected"' : '';
			$output .= '<option value="' . esc_attr( $opt_value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
		}
		$output .= '</select>';

		// Mobile select.
		$output .= '<select class="device-input" data-device="mobile" style="display:none;">';
		foreach ( $options as $label => $opt_value ) {
			$selected = ( $opt_value === $mobile ) ? ' selected="selected"' : '';
			$output .= '<option value="' . esc_attr( $opt_value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
		}
		$output .= '</select>';

		$output .= '<input type="hidden" name="' . esc_attr( $settings['param_name'] ) . '" class="responsive-combined-value wpb_vc_param_value ' . esc_attr( $settings['param_name'] ) . ' ' . esc_attr( $settings['type'] ) . '" value="' . esc_attr( $value ) . '" />';

		$output .= '</div>';

		return $output;
	}

	/**
	 * Get POI options for the "Center On" dropdown.
	 *
	 * @since 2.0.0
	 *
	 * @return array Options array with label => value format.
	 */
	private function get_center_on_options() {
		$options = array(
			__( 'Custom Coordinates', 'clear-map' ) => '',
		);

		$categories = get_option( 'clear_map_categories', array() );
		$pois       = get_option( 'clear_map_pois', array() );

		foreach ( $pois as $category_key => $category_pois ) {
			$category_name = isset( $categories[ $category_key ]['name'] )
				? $categories[ $category_key ]['name']
				: $category_key;

			foreach ( $category_pois as $index => $poi ) {
				// Only include POIs that have coordinates.
				if ( empty( $poi['lat'] ) || empty( $poi['lng'] ) ) {
					continue;
				}

				$label = $poi['name'] . ' (' . $category_name . ')';
				$value = $category_key . '|' . $index;

				$options[ $label ] = $value;
			}
		}

		return $options;
	}

	/**
	 * Register the Clear Map element with WPBakery.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_element() {
		// Check if vc_map function exists (WPBakery is active).
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}

		vc_map(
			array(
				'name'        => __( 'Clear Map', 'clear-map' ),
				'base'        => 'clear_map',
				'description' => __( 'Interactive map with POI filtering', 'clear-map' ),
				'category'    => __( 'Content', 'clear-map' ),
				'icon'        => 'icon-wpb-map-pin',
				'params'      => array(
					// =====================
					// General Group
					// =====================
					array(
						'type'        => 'responsive_textfield',
						'heading'     => __( 'Map Height', 'clear-map' ),
						'param_name'  => 'height',
						'value'       => '',
						'description' => __( 'Height of the map container (e.g., 60vh, 500px, 100%). Use device icons to set per breakpoint.', 'clear-map' ),
						'admin_label' => true,
						'group'       => __( 'General', 'clear-map' ),
					),
					array(
						'type'        => 'el_id',
						'heading'     => __( 'Element ID', 'clear-map' ),
						'param_name'  => 'el_id',
						'description' => __( 'Unique identifier for this element (optional).', 'clear-map' ),
						'group'       => __( 'General', 'clear-map' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Extra CSS Class', 'clear-map' ),
						'param_name'  => 'el_class',
						'description' => __( 'Add custom CSS class(es) to the map container.', 'clear-map' ),
						'group'       => __( 'General', 'clear-map' ),
					),

					// =====================
					// Map Position Group
					// =====================
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Center On', 'clear-map' ),
						'param_name'  => 'center_on',
						'value'       => $this->get_center_on_options(),
						'std'         => '',
						'description' => __( 'Center the map on a specific POI, or use custom coordinates below.', 'clear-map' ),
						'group'       => __( 'Map Position', 'clear-map' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Center Latitude', 'clear-map' ),
						'param_name'  => 'center_lat',
						'value'       => '',
						'description' => __( 'Latitude for the initial map center. Leave blank for default (40.7451).', 'clear-map' ),
						'dependency'  => array(
							'element'  => 'center_on',
							'is_empty' => true,
						),
						'group'       => __( 'Map Position', 'clear-map' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Center Longitude', 'clear-map' ),
						'param_name'  => 'center_lng',
						'value'       => '',
						'description' => __( 'Longitude for the initial map center. Leave blank for default (-74.0011).', 'clear-map' ),
						'dependency'  => array(
							'element'  => 'center_on',
							'is_empty' => true,
						),
						'group'       => __( 'Map Position', 'clear-map' ),
					),
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Initial Zoom Level', 'clear-map' ),
						'param_name'  => 'zoom',
						'value'       => array(
							__( 'Street Level (18)', 'clear-map' )  => '18',
							__( 'Block Level (17)', 'clear-map' )   => '17',
							__( 'Buildings (16)', 'clear-map' )     => '16',
							__( 'Neighborhood (15)', 'clear-map' )  => '15',
							__( 'District (14)', 'clear-map' )      => '14',
							__( 'City Area (13)', 'clear-map' )     => '13',
							__( 'City (12)', 'clear-map' )          => '12',
							__( 'Metro Area (11)', 'clear-map' )    => '11',
							__( 'Region (10)', 'clear-map' )        => '10',
							__( 'State (9)', 'clear-map' )          => '9',
							__( 'Wide State (8)', 'clear-map' )     => '8',
							__( 'Multi-State (7)', 'clear-map' )    => '7',
							__( 'Country Region (6)', 'clear-map' ) => '6',
							__( 'Country (5)', 'clear-map' )        => '5',
							__( 'Continent (4)', 'clear-map' )      => '4',
							__( 'Wide View (3)', 'clear-map' )      => '3',
						),
						'std'         => '14',
						'description' => __( 'How zoomed in the map should be initially.', 'clear-map' ),
						'group'       => __( 'Map Position', 'clear-map' ),
					),

					// =====================
					// Map Display Group
					// =====================
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Cluster Distance', 'clear-map' ),
						'param_name'  => 'cluster_distance',
						'value'       => '',
						'description' => __( 'Distance in pixels for clustering POIs. Leave blank for global setting (default: 50).', 'clear-map' ),
						'group'       => __( 'Map Display', 'clear-map' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Cluster Minimum Points', 'clear-map' ),
						'param_name'  => 'cluster_min_points',
						'value'       => '',
						'description' => __( 'Minimum POIs needed to form a cluster. Leave blank for global setting (default: 3).', 'clear-map' ),
						'group'       => __( 'Map Display', 'clear-map' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Zoom Threshold', 'clear-map' ),
						'param_name'  => 'zoom_threshold',
						'value'       => '',
						'description' => __( 'Zoom level at which clusters expand. Leave blank for global setting (default: 15).', 'clear-map' ),
						'group'       => __( 'Map Display', 'clear-map' ),
					),
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Show Subway Lines', 'clear-map' ),
						'param_name'  => 'show_subway_lines',
						'value'       => array(
							__( 'No', 'clear-map' )  => '0',
							__( 'Yes', 'clear-map' ) => '1',
						),
						'std'         => '0',
						'description' => __( 'Display NYC subway lines on the map.', 'clear-map' ),
						'group'       => __( 'Map Display', 'clear-map' ),
					),

					// =====================
					// Filter Panel Group
					// =====================
					array(
						'type'        => 'responsive_dropdown',
						'heading'     => __( 'Show Filter Panel', 'clear-map' ),
						'param_name'  => 'show_filters',
						'value'       => array(
							__( 'Yes', 'clear-map' ) => '1',
							__( 'No', 'clear-map' )  => '0',
						),
						'std'         => '1',
						'description' => __( 'Show or hide the filter panel. Use device icons to set per breakpoint.', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'responsive_textfield',
						'heading'     => __( 'Panel Width', 'clear-map' ),
						'param_name'  => 'filters_width',
						'value'       => '',
						'description' => __( 'Width of filter panel (e.g., 320px, 25%, 100%). Use device icons to set per breakpoint.', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'responsive_textfield',
						'heading'     => __( 'Panel Height', 'clear-map' ),
						'param_name'  => 'filters_height',
						'value'       => '',
						'description' => __( 'Height of filter panel (e.g., 400px, auto). Use device icons to set per breakpoint.', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'colorpicker',
						'heading'     => __( 'Background Color', 'clear-map' ),
						'param_name'  => 'filters_bg_color',
						'value'       => '',
						'description' => __( 'Background color for the filter panel. Leave blank for global setting.', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'responsive_dropdown',
						'heading'     => __( 'Transparent Background', 'clear-map' ),
						'param_name'  => 'filters_bg_transparent',
						'value'       => array(
							__( 'No', 'clear-map' )  => '0',
							__( 'Yes', 'clear-map' ) => '1',
						),
						'std'         => '0',
						'description' => __( 'Make the filter panel background transparent.', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'responsive_dropdown',
						'heading'     => __( 'Show Header', 'clear-map' ),
						'param_name'  => 'filters_show_header',
						'value'       => array(
							__( 'Yes', 'clear-map' ) => '1',
							__( 'No', 'clear-map' )  => '0',
						),
						'std'         => '1',
						'description' => __( 'Show the "The Area" header with collapse toggle.', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'responsive_dropdown',
						'heading'     => __( 'Button Style', 'clear-map' ),
						'param_name'  => 'filters_style',
						'value'       => array(
							__( 'List', 'clear-map' )          => 'list',
							__( 'Rounded Pills', 'clear-map' ) => 'pills',
						),
						'std'         => 'list',
						'description' => __( 'Display style for category buttons.', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Pill Border Color', 'clear-map' ),
						'param_name'  => 'filters_pill_border',
						'value'       => array(
							__( 'Use Category Color', 'clear-map' ) => 'category',
							__( 'Custom Color', 'clear-map' )       => 'custom',
						),
						'std'         => 'category',
						'description' => __( 'How to color pill borders (only applies to Pills style).', 'clear-map' ),
						'dependency'  => array(
							'element' => 'filters_style',
							'value'   => array( 'pills' ),
						),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'colorpicker',
						'heading'     => __( 'Custom Pill Border Color', 'clear-map' ),
						'param_name'  => 'filters_pill_border_color',
						'value'       => '',
						'description' => __( 'Border color for all pill buttons.', 'clear-map' ),
						'dependency'  => array(
							'element' => 'filters_pill_border',
							'value'   => array( 'custom' ),
						),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Pill Background', 'clear-map' ),
						'param_name'  => 'filters_pill_bg',
						'value'       => array(
							__( 'Transparent', 'clear-map' )   => 'transparent',
							__( 'Solid Color', 'clear-map' )   => 'color',
							__( 'Frosted Glass', 'clear-map' ) => 'frosted',
						),
						'std'         => 'transparent',
						'description' => __( 'Background style for pill buttons.', 'clear-map' ),
						'dependency'  => array(
							'element' => 'filters_style',
							'value'   => array( 'pills' ),
						),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'colorpicker',
						'heading'     => __( 'Pill Background Color', 'clear-map' ),
						'param_name'  => 'filters_pill_bg_color',
						'value'       => '',
						'description' => __( 'Background color for pill buttons.', 'clear-map' ),
						'dependency'  => array(
							'element' => 'filters_pill_bg',
							'value'   => array( 'color' ),
						),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'responsive_dropdown',
						'heading'     => __( 'Show Individual Items', 'clear-map' ),
						'param_name'  => 'filters_show_items',
						'value'       => array(
							__( 'Yes', 'clear-map' ) => '1',
							__( 'No', 'clear-map' )  => '0',
						),
						'std'         => '1',
						'description' => __( 'Show expandable POI lists under each category.', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Mobile Filter Display', 'clear-map' ),
						'param_name'  => 'mobile_filters',
						'value'       => array(
							__( 'Below Map', 'clear-map' )   => 'below',
							__( 'Above Map', 'clear-map' )   => 'above',
							__( 'Bottom Drawer', 'clear-map' ) => 'drawer',
							__( 'Hidden', 'clear-map' )      => 'hidden',
						),
						'std'         => 'below',
						'description' => __( 'How the filter panel appears on mobile devices (768px and below).', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Mobile Filter Style', 'clear-map' ),
						'param_name'  => 'mobile_filters_style',
						'value'       => array(
							__( 'Inherit from Desktop', 'clear-map' ) => 'inherit',
							__( 'List', 'clear-map' )                 => 'list',
							__( 'Rounded Pills', 'clear-map' )        => 'pills',
						),
						'std'         => 'inherit',
						'description' => __( 'Override the button style specifically for mobile devices.', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),

					// =====================
					// Design Options Group
					// =====================
					array(
						'type'       => 'css_editor',
						'heading'    => __( 'CSS', 'clear-map' ),
						'param_name' => 'css',
						'group'      => __( 'Design Options', 'clear-map' ),
					),
				),
			)
		);
	}
}
