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
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Map Height', 'clear-map' ),
						'param_name'  => 'height',
						'value'       => '60vh',
						'description' => __( 'Height of the map container (e.g., 60vh, 500px, 100%).', 'clear-map' ),
						'admin_label' => true,
						'group'       => __( 'General', 'clear-map' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Center Latitude', 'clear-map' ),
						'param_name'  => 'center_lat',
						'value'       => '40.7451',
						'description' => __( 'Latitude for the initial map center.', 'clear-map' ),
						'group'       => __( 'Map Position', 'clear-map' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Center Longitude', 'clear-map' ),
						'param_name'  => 'center_lng',
						'value'       => '-74.0011',
						'description' => __( 'Longitude for the initial map center.', 'clear-map' ),
						'group'       => __( 'Map Position', 'clear-map' ),
					),
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Initial Zoom Level', 'clear-map' ),
						'param_name'  => 'zoom',
						'value'       => array(
							__( 'Street Level (18)', 'clear-map' )      => '18',
							__( 'Block Level (17)', 'clear-map' )       => '17',
							__( 'Buildings (16)', 'clear-map' )         => '16',
							__( 'Neighborhood (15)', 'clear-map' )      => '15',
							__( 'District (14)', 'clear-map' )          => '14',
							__( 'City Area (13)', 'clear-map' )         => '13',
							__( 'City (12)', 'clear-map' )              => '12',
							__( 'Metro Area (11)', 'clear-map' )        => '11',
							__( 'Region (10)', 'clear-map' )            => '10',
							__( 'State (9)', 'clear-map' )              => '9',
							__( 'Wide State (8)', 'clear-map' )         => '8',
							__( 'Multi-State (7)', 'clear-map' )        => '7',
							__( 'Country Region (6)', 'clear-map' )     => '6',
							__( 'Country (5)', 'clear-map' )            => '5',
							__( 'Continent (4)', 'clear-map' )          => '4',
							__( 'Wide View (3)', 'clear-map' )          => '3',
						),
						'std'         => '14',
						'description' => __( 'How zoomed in the map should be initially.', 'clear-map' ),
						'group'       => __( 'Map Position', 'clear-map' ),
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
