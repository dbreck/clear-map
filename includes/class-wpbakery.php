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
						'type'        => 'textfield',
						'heading'     => __( 'Map Height', 'clear-map' ),
						'param_name'  => 'height',
						'value'       => '',
						'description' => __( 'Height of the map container (e.g., 60vh, 500px, 100%). Leave blank for default (60vh).', 'clear-map' ),
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
						'type'        => 'textfield',
						'heading'     => __( 'Center Latitude', 'clear-map' ),
						'param_name'  => 'center_lat',
						'value'       => '',
						'description' => __( 'Latitude for the initial map center. Leave blank for default (40.7451).', 'clear-map' ),
						'group'       => __( 'Map Position', 'clear-map' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Center Longitude', 'clear-map' ),
						'param_name'  => 'center_lng',
						'value'       => '',
						'description' => __( 'Longitude for the initial map center. Leave blank for default (-74.0011).', 'clear-map' ),
						'group'       => __( 'Map Position', 'clear-map' ),
					),
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Initial Zoom Level', 'clear-map' ),
						'param_name'  => 'zoom',
						'value'       => array(
							__( 'Use Global Setting', 'clear-map' )    => '',
							__( 'Street Level (18)', 'clear-map' )    => '18',
							__( 'Block Level (17)', 'clear-map' )     => '17',
							__( 'Buildings (16)', 'clear-map' )       => '16',
							__( 'Neighborhood (15)', 'clear-map' )    => '15',
							__( 'District (14)', 'clear-map' )        => '14',
							__( 'City Area (13)', 'clear-map' )       => '13',
							__( 'City (12)', 'clear-map' )            => '12',
							__( 'Metro Area (11)', 'clear-map' )      => '11',
							__( 'Region (10)', 'clear-map' )          => '10',
							__( 'State (9)', 'clear-map' )            => '9',
							__( 'Wide State (8)', 'clear-map' )       => '8',
							__( 'Multi-State (7)', 'clear-map' )      => '7',
							__( 'Country Region (6)', 'clear-map' )   => '6',
							__( 'Country (5)', 'clear-map' )          => '5',
							__( 'Continent (4)', 'clear-map' )        => '4',
							__( 'Wide View (3)', 'clear-map' )        => '3',
						),
						'std'         => '',
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
							__( 'Use Global Setting', 'clear-map' ) => '',
							__( 'Yes', 'clear-map' )                => '1',
							__( 'No', 'clear-map' )                 => '0',
						),
						'std'         => '',
						'description' => __( 'Display NYC subway lines on the map.', 'clear-map' ),
						'group'       => __( 'Map Display', 'clear-map' ),
					),

					// =====================
					// Filter Panel Group
					// =====================
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Show Filter Panel', 'clear-map' ),
						'param_name'  => 'show_filters',
						'value'       => array(
							__( 'Use Global Setting', 'clear-map' ) => '',
							__( 'Yes', 'clear-map' )                => '1',
							__( 'No', 'clear-map' )                 => '0',
						),
						'std'         => '',
						'description' => __( 'Show or hide the filter panel on the map.', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Panel Width', 'clear-map' ),
						'param_name'  => 'filters_width',
						'value'       => '',
						'description' => __( 'Width of filter panel (e.g., 320px, 25%, 20vw). Leave blank for global setting.', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Panel Height', 'clear-map' ),
						'param_name'  => 'filters_height',
						'value'       => '',
						'description' => __( 'Height of filter panel (e.g., 400px, 50%, 50vh, or auto). Leave blank for global setting.', 'clear-map' ),
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
						'type'        => 'dropdown',
						'heading'     => __( 'Transparent Background', 'clear-map' ),
						'param_name'  => 'filters_bg_transparent',
						'value'       => array(
							__( 'Use Global Setting', 'clear-map' ) => '',
							__( 'Yes', 'clear-map' )                => '1',
							__( 'No', 'clear-map' )                 => '0',
						),
						'std'         => '',
						'description' => __( 'Make the filter panel background transparent.', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Frosted Glass Effect', 'clear-map' ),
						'param_name'  => 'filters_frosted',
						'value'       => array(
							__( 'Use Global Setting', 'clear-map' ) => '',
							__( 'Yes', 'clear-map' )                => '1',
							__( 'No', 'clear-map' )                 => '0',
						),
						'std'         => '',
						'description' => __( 'Apply frosted glass blur effect to the panel.', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Show Header', 'clear-map' ),
						'param_name'  => 'filters_show_header',
						'value'       => array(
							__( 'Use Global Setting', 'clear-map' ) => '',
							__( 'Yes', 'clear-map' )                => '1',
							__( 'No', 'clear-map' )                 => '0',
						),
						'std'         => '',
						'description' => __( 'Show the "The Area" header with collapse toggle.', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Button Style', 'clear-map' ),
						'param_name'  => 'filters_style',
						'value'       => array(
							__( 'Use Global Setting', 'clear-map' ) => '',
							__( 'List', 'clear-map' )               => 'list',
							__( 'Rounded Pills', 'clear-map' )      => 'pills',
						),
						'std'         => '',
						'description' => __( 'Display style for category buttons.', 'clear-map' ),
						'group'       => __( 'Filter Panel', 'clear-map' ),
					),
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Pill Border Color', 'clear-map' ),
						'param_name'  => 'filters_pill_border',
						'value'       => array(
							__( 'Use Global Setting', 'clear-map' )  => '',
							__( 'Use Category Color', 'clear-map' ) => 'category',
							__( 'Custom Color', 'clear-map' )       => 'custom',
						),
						'std'         => '',
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
						'heading'     => __( 'Show Individual Items', 'clear-map' ),
						'param_name'  => 'filters_show_items',
						'value'       => array(
							__( 'Use Global Setting', 'clear-map' ) => '',
							__( 'Yes', 'clear-map' )                => '1',
							__( 'No', 'clear-map' )                 => '0',
						),
						'std'         => '',
						'description' => __( 'Show expandable POI lists under each category.', 'clear-map' ),
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
