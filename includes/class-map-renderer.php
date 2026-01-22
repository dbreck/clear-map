<?php
/**
 * Map Renderer
 *
 * Renders the map HTML and passes data to JavaScript.
 *
 * @package Clear_Map
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clear_Map_Renderer
 *
 * Handles map HTML output and JavaScript data localization.
 *
 * @since 1.0.0
 */
class Clear_Map_Renderer {

	/**
	 * Get setting value with shortcode override.
	 *
	 * Returns shortcode attribute if set, otherwise falls back to global option.
	 *
	 * @since 1.6.0
	 *
	 * @param array  $atts         Shortcode attributes.
	 * @param string $attr_name    Shortcode attribute name.
	 * @param string $option_name  WordPress option name.
	 * @param mixed  $default      Default value if neither is set.
	 * @return mixed The setting value.
	 */
	private function get_setting( $atts, $attr_name, $option_name, $default = '' ) {
		// If shortcode attribute is set and not empty, use it.
		if ( isset( $atts[ $attr_name ] ) && '' !== $atts[ $attr_name ] ) {
			return $atts[ $attr_name ];
		}
		// If no global option name provided (WPBakery-only settings), use default.
		if ( empty( $option_name ) ) {
			return $default;
		}
		// Otherwise fall back to global option.
		return get_option( $option_name, $default );
	}

	/**
	 * Parse responsive value from pipe-separated string.
	 *
	 * WPBakery responsive fields store values as "desktop|tablet|mobile".
	 * This method parses that into an array with inheritance fallback.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value   The pipe-separated responsive value.
	 * @param mixed  $default Default value if parsing fails.
	 * @return array Array with 'desktop', 'tablet', 'mobile' keys.
	 */
	private function parse_responsive_value( $value, $default = '' ) {
		// If empty or not a string with pipes, return default for all.
		if ( empty( $value ) || ! is_string( $value ) || strpos( $value, '|' ) === false ) {
			return array(
				'desktop' => $value ? $value : $default,
				'tablet'  => '',
				'mobile'  => '',
			);
		}

		$parts   = explode( '|', $value );
		$desktop = isset( $parts[0] ) ? $parts[0] : $default;
		$tablet  = isset( $parts[1] ) ? $parts[1] : '';
		$mobile  = isset( $parts[2] ) ? $parts[2] : '';

		return array(
			'desktop' => $desktop,
			'tablet'  => $tablet,
			'mobile'  => $mobile,
		);
	}

	/**
	 * Render the map.
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered map HTML.
	 */
	public function render( $atts ) {
		$map_id      = 'clear-map-' . uniqid();
		$js_var_name = str_replace( '-', '_', $map_id ); // Sanitize for JavaScript.

		// Get all data.
		$categories       = get_option( 'clear_map_categories', array() );
		$pois             = get_option( 'clear_map_pois', array() );
		$building_address = get_option( 'clear_map_building_address', '' );

		// Get stored building coordinates (geocoded separately via admin).
		$building_lat = get_option( 'clear_map_building_lat', null );
		$building_lng = get_option( 'clear_map_building_lng', null );

		$building_coords = null;
		if ( $building_lat && $building_lng ) {
			$building_coords = array(
				'lat' => floatval( $building_lat ),
				'lng' => floatval( $building_lng ),
			);
		}

		// Get settings with shortcode overrides.
		$height_raw          = $this->get_setting( $atts, 'height', '', '60vh' );
		$height              = $this->parse_responsive_value( $height_raw, '60vh' );

		// Handle "Center On" POI option.
		$center_on = $this->get_setting( $atts, 'center_on', '', '' );
		if ( ! empty( $center_on ) && strpos( $center_on, '|' ) !== false ) {
			// Format: category_key|index.
			$center_parts = explode( '|', $center_on );
			if ( count( $center_parts ) === 2 ) {
				$cat_key   = $center_parts[0];
				$poi_index = intval( $center_parts[1] );

				if ( isset( $pois[ $cat_key ][ $poi_index ] ) ) {
					$center_poi = $pois[ $cat_key ][ $poi_index ];
					$center_lat = ! empty( $center_poi['lat'] ) ? $center_poi['lat'] : 40.7451;
					$center_lng = ! empty( $center_poi['lng'] ) ? $center_poi['lng'] : -74.0011;
				} else {
					// POI not found, fall back to manual coordinates.
					$center_lat = $this->get_setting( $atts, 'center_lat', '', 40.7451 );
					$center_lng = $this->get_setting( $atts, 'center_lng', '', -74.0011 );
				}
			} else {
				$center_lat = $this->get_setting( $atts, 'center_lat', '', 40.7451 );
				$center_lng = $this->get_setting( $atts, 'center_lng', '', -74.0011 );
			}
		} else {
			// Use manual coordinates.
			$center_lat = $this->get_setting( $atts, 'center_lat', '', 40.7451 );
			$center_lng = $this->get_setting( $atts, 'center_lng', '', -74.0011 );
		}

		$zoom                = $this->get_setting( $atts, 'zoom', '', 14 );
		// Display settings (WPBakery only, no global fallback).
		$cluster_distance    = $this->get_setting( $atts, 'cluster_distance', '', 50 );
		$cluster_min_points  = $this->get_setting( $atts, 'cluster_min_points', '', 3 );
		$zoom_threshold      = $this->get_setting( $atts, 'zoom_threshold', '', 15 );
		$show_subway_lines   = $this->get_setting( $atts, 'show_subway_lines', '', 0 );

		// Filter panel appearance settings (responsive values from WPBakery).
		$filters_width_raw          = $this->get_setting( $atts, 'filters_width', '', '320px' );
		$filters_height_raw         = $this->get_setting( $atts, 'filters_height', '', 'auto' );
		$filters_bg_color           = $this->get_setting( $atts, 'filters_bg_color', '', '#FBF8F1' );
		$filters_bg_transparent_raw = $this->get_setting( $atts, 'filters_bg_transparent', '', 0 );
		$filters_show_header_raw    = $this->get_setting( $atts, 'filters_show_header', '', 1 );
		$filters_style_raw          = $this->get_setting( $atts, 'filters_style', '', 'list' );
		$filters_pill_border        = $this->get_setting( $atts, 'filters_pill_border', '', 'category' );
		$filters_pill_border_color  = $this->get_setting( $atts, 'filters_pill_border_color', '', '#666666' );
		$filters_pill_bg            = $this->get_setting( $atts, 'filters_pill_bg', '', 'transparent' );
		$filters_pill_bg_color      = $this->get_setting( $atts, 'filters_pill_bg_color', '', '#ffffff' );
		$filters_show_items_raw     = $this->get_setting( $atts, 'filters_show_items', '', 1 );
		$show_filters_raw           = $this->get_setting( $atts, 'show_filters', '', 1 );
		$frosted_glass_raw          = $this->get_setting( $atts, 'frosted_glass', '', 'none' );

		// Parse responsive values.
		$filters_width          = $this->parse_responsive_value( $filters_width_raw, '320px' );
		$filters_height         = $this->parse_responsive_value( $filters_height_raw, 'auto' );
		$filters_bg_transparent = $this->parse_responsive_value( $filters_bg_transparent_raw, '0' );
		$filters_show_header    = $this->parse_responsive_value( $filters_show_header_raw, '1' );
		$filters_style          = $this->parse_responsive_value( $filters_style_raw, 'list' );
		$filters_show_items     = $this->parse_responsive_value( $filters_show_items_raw, '1' );
		$show_filters           = $this->parse_responsive_value( $show_filters_raw, '1' );
		$frosted_glass          = $this->parse_responsive_value( $frosted_glass_raw, 'none' );

		// Mobile settings (WPBakery only, no global fallback).
		$mobile_filters       = $this->get_setting( $atts, 'mobile_filters', '', 'below' );
		$mobile_filters_style = $this->get_setting( $atts, 'mobile_filters_style', '', 'inherit' );

		// Convert string values to proper types for boolean comparisons (desktop values for backward compat).
		$show_subway_lines             = 1 === (int) $show_subway_lines;
		$show_filters_desktop          = 1 === (int) $show_filters['desktop'];
		// Render filter panel if ANY breakpoint shows it (JS will handle show/hide dynamically).
		$show_filters_any_breakpoint   = $show_filters_desktop
			|| 1 === (int) $show_filters['tablet']
			|| 1 === (int) $show_filters['mobile'];
		$filters_bg_transparent_desktop = 1 === (int) $filters_bg_transparent['desktop'];
		$filters_show_header_desktop   = 1 === (int) $filters_show_header['desktop'];
		$filters_show_items_desktop    = 1 === (int) $filters_show_items['desktop'];

		// Prepare data for JS.
		$map_data = array(
			'mapboxToken'         => get_option( 'clear_map_mapbox_token' ),
			'centerLat'           => floatval( $center_lat ),
			'centerLng'           => floatval( $center_lng ),
			'zoom'                => intval( $zoom ),
			'buildingCoords'      => $building_coords,
			'buildingAddress'     => get_option( 'clear_map_building_address', $building_address ),
			'buildingIconWidth'   => get_option( 'clear_map_building_icon_width', '40px' ),
			'buildingIconSVG'     => get_option( 'clear_map_building_icon_svg', '' ),
			'buildingIconPNG'     => get_option( 'clear_map_building_icon_png', '' ),
			'buildingPhone'       => get_option( 'clear_map_building_phone', '' ),
			'buildingEmail'       => get_option( 'clear_map_building_email', '' ),
			'buildingDescription' => get_option( 'clear_map_building_description', '' ),
			'categories'          => $categories,
			'pois'                => $pois,
			'clusterDistance'     => intval( $cluster_distance ),
			'clusterMinPoints'    => intval( $cluster_min_points ),
			'zoomThreshold'       => intval( $zoom_threshold ),
			'showSubwayLines'     => $show_subway_lines,
			'subwayDataUrl'       => CLEAR_MAP_PLUGIN_URL . 'assets/data/nyc-subway-lines.geojson',
			// Filter panel settings for JS (responsive).
			'showFilters'         => $show_filters,
			'filtersWidth'        => $filters_width,
			'filtersHeight'       => $filters_height,
			'filtersBgTransparent' => $filters_bg_transparent,
			'filtersShowHeader'   => $filters_show_header,
			'filtersStyle'        => $filters_style,
			'filtersShowItems'    => $filters_show_items,
			'pillBorderMode'      => $filters_pill_border,
			'pillBorderColor'     => $filters_pill_border_color,
			// Mobile settings for JS.
			'mobileFilters'       => $mobile_filters,
			'mobileFiltersStyle'  => $mobile_filters_style,
			// Map height (responsive).
			'mapHeight'           => $height,
			// Frosted glass (responsive).
			'frostedGlass'        => $frosted_glass,
		);

		// Enqueue assets.
		wp_enqueue_script(
			'clear-map-frontend',
			CLEAR_MAP_PLUGIN_URL . 'assets/js/map.js',
			array( 'jquery', 'mapbox-gl' ),
			CLEAR_MAP_VERSION,
			true
		);

		wp_localize_script( 'clear-map-frontend', 'clearMapData_' . $js_var_name, $map_data );

		wp_enqueue_style(
			'clear-map-frontend',
			CLEAR_MAP_PLUGIN_URL . 'assets/css/map.css',
			array( 'mapbox-gl' ),
			CLEAR_MAP_VERSION
		);

		// Build wrapper classes.
		$wrapper_classes = array( 'clear-map-container' );

		// Add WPBakery custom class.
		if ( ! empty( $atts['el_class'] ) ) {
			$wrapper_classes[] = sanitize_html_class( $atts['el_class'] );
		}

		// Add WPBakery CSS editor class.
		if ( ! empty( $atts['css'] ) && function_exists( 'vc_shortcode_custom_css_class' ) ) {
			$wrapper_classes[] = vc_shortcode_custom_css_class( $atts['css'], ' ' );
		}

		$wrapper_class = implode( ' ', array_filter( $wrapper_classes ) );

		// Build wrapper ID.
		$wrapper_id = '';
		if ( ! empty( $atts['el_id'] ) ) {
			$wrapper_id = ' id="' . esc_attr( $atts['el_id'] ) . '"';
		}

		// Build filter panel classes (use desktop values for initial render).
		$filter_classes   = array( 'clear-map-filters' );
		$filter_classes[] = 'filter-style-' . sanitize_html_class( $filters_style['desktop'] );

		if ( ! $filters_show_header_desktop ) {
			$filter_classes[] = 'no-header';
		}

		if ( ! $filters_show_items_desktop ) {
			$filter_classes[] = 'no-items';
		}

		// Add class for transparent background (to remove shadow).
		if ( $filters_bg_transparent_desktop ) {
			$filter_classes[] = 'bg-transparent';
		}

		// Add frosted glass classes based on setting.
		$frosted_desktop = $frosted_glass['desktop'];
		if ( 'panel' === $frosted_desktop || 'both' === $frosted_desktop ) {
			$filter_classes[] = 'filters-frosted';
		}
		if ( 'buttons' === $frosted_desktop || 'both' === $frosted_desktop ) {
			$filter_classes[] = 'pills-frosted';
		}

		$filter_class = implode( ' ', $filter_classes );

		// Build filter panel inline styles (desktop values for initial render).
		$filter_inline_style = '';

		// Width.
		if ( $filters_width['desktop'] ) {
			$filter_inline_style .= 'width: ' . esc_attr( $filters_width['desktop'] ) . ';';
		}

		// Height.
		if ( $filters_height['desktop'] && 'auto' !== $filters_height['desktop'] ) {
			$filter_inline_style .= 'height: ' . esc_attr( $filters_height['desktop'] ) . '; max-height: ' . esc_attr( $filters_height['desktop'] ) . ';';
		}

		// Background color.
		if ( $filters_bg_transparent_desktop ) {
			$filter_inline_style .= 'background-color: transparent;';
		} elseif ( $filters_bg_color ) {
			$filter_inline_style .= 'background-color: ' . esc_attr( $filters_bg_color ) . ';';
		}

		ob_start();
		?>
		<div<?php echo $wrapper_id; ?> class="<?php echo esc_attr( $wrapper_class ); ?>" data-map-id="<?php echo esc_attr( $map_id ); ?>" data-js-var="<?php echo esc_attr( $js_var_name ); ?>">
			<div id="<?php echo esc_attr( $map_id ); ?>" class="clear-map"></div>
			<?php if ( $show_filters_any_breakpoint ) : ?>
			<div class="<?php echo esc_attr( $filter_class ); ?>" id="<?php echo esc_attr( $map_id ); ?>-filters" style="<?php echo esc_attr( $filter_inline_style ); ?>">
				<?php if ( $filters_show_header_desktop ) : ?>
				<div class="filters-header">
					<h5><?php esc_html_e( 'The Area', 'clear-map' ); ?></h5>
					<button class="toggle-filters" type="button">
						<span class="screen-reader-text"><?php esc_html_e( 'Toggle Filters', 'clear-map' ); ?></span>
						<svg width="20" height="20" viewBox="0 0 20 20">
							<path d="M15 7L10 12L5 7" stroke="currentColor" stroke-width="2" fill="none" />
						</svg>
					</button>
				</div>
				<?php endif; ?>

				<div class="filters-content">
					<?php if ( $show_subway_lines ) : ?>
						<div class="subway-toggle-section">
							<div class="subway-toggle-header">
								<label class="subway-toggle-label">
									<input type="checkbox" class="subway-toggle-checkbox" checked />
									<span class="subway-toggle-switch"></span>
									<span class="subway-toggle-text"><?php esc_html_e( 'Show Subway Lines', 'clear-map' ); ?></span>
								</label>
							</div>
						</div>
					<?php endif; ?>

					<?php
					// Determine pill inline styles.
					$pill_border_inline = '';
					$pill_bg_inline     = '';
					if ( 'pills' === $filters_style ) {
						if ( 'custom' === $filters_pill_border ) {
							$pill_border_inline = '--pill-border-color: ' . esc_attr( $filters_pill_border_color ) . ';';
						}
						if ( 'color' === $filters_pill_bg ) {
							$pill_bg_inline = '--pill-bg-color: ' . esc_attr( $filters_pill_bg_color ) . '; --pill-bg-hover: ' . esc_attr( $filters_pill_bg_color ) . ';';
						}
					}
					?>

					<?php foreach ( $categories as $cat_key => $category ) : ?>
						<?php
						// For pills with category color, set the CSS variable per category.
						$category_style = '';
						if ( 'pills' === $filters_style ) {
							if ( 'category' === $filters_pill_border ) {
								$category_style = '--pill-border-color: ' . esc_attr( $category['color'] ) . ';';
							} elseif ( $pill_border_inline ) {
								$category_style = $pill_border_inline;
							}
							// Add pill background color.
							if ( $pill_bg_inline ) {
								$category_style .= ' ' . $pill_bg_inline;
							}
						}
						?>
						<div class="filter-category" data-category="<?php echo esc_attr( $cat_key ); ?>" style="<?php echo esc_attr( $category_style ); ?>">
							<div class="category-header">
								<div class="category-toggle">
									<span class="category-icon">
										<span class="category-dot" style="background-color: <?php echo esc_attr( $category['color'] ); ?>"></span>
										<span class="category-x" style="color: <?php echo esc_attr( $category['color'] ); ?>;">✕</span>
									</span>
									<span class="category-name"><?php echo esc_html( $category['name'] ); ?></span>
								</div>
								<?php if ( $filters_show_items ) : ?>
								<button class="category-expand" type="button">
									<svg width="16" height="16" viewBox="0 0 16 16">
										<path d="M6 4L10 8L6 12" stroke="currentColor" stroke-width="2" fill="none" />
									</svg>
								</button>
								<?php endif; ?>
							</div>

							<?php if ( $filters_show_items ) : ?>
							<div class="category-pois" style="display: none;" aria-expanded="false">
								<?php
								if ( isset( $pois[ $cat_key ] ) ) :
									// Sort POIs alphabetically while preserving original index.
									$sorted_pois = array();
									foreach ( $pois[ $cat_key ] as $index => $poi ) {
										$sorted_pois[] = array(
											'index' => $index,
											'poi'   => $poi,
										);
									}
									usort(
										$sorted_pois,
										function ( $a, $b ) {
											return strcasecmp( $a['poi']['name'], $b['poi']['name'] );
										}
									);
									foreach ( $sorted_pois as $item ) :
										$index = $item['index'];
										$poi   = $item['poi'];
										?>
										<div class="poi-item" data-poi="<?php echo esc_attr( $cat_key . '-' . $index ); ?>">
											<span class="poi-dot" style="background-color: <?php echo esc_attr( $category['color'] ); ?>"></span>
											<span class="poi-name"><?php echo esc_html( $poi['name'] ); ?></span>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
