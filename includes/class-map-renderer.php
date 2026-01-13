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
		// Otherwise fall back to global option.
		return get_option( $option_name, $default );
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
		$height              = $this->get_setting( $atts, 'height', '', '60vh' );
		$center_lat          = $this->get_setting( $atts, 'center_lat', '', 40.7451 );
		$center_lng          = $this->get_setting( $atts, 'center_lng', '', -74.0011 );
		$zoom                = $this->get_setting( $atts, 'zoom', '', 14 );
		$cluster_distance    = $this->get_setting( $atts, 'cluster_distance', 'clear_map_cluster_distance', 50 );
		$cluster_min_points  = $this->get_setting( $atts, 'cluster_min_points', 'clear_map_cluster_min_points', 3 );
		$zoom_threshold      = $this->get_setting( $atts, 'zoom_threshold', 'clear_map_zoom_threshold', 15 );
		$show_subway_lines   = $this->get_setting( $atts, 'show_subway_lines', 'clear_map_show_subway_lines', 0 );
		$show_filters        = $this->get_setting( $atts, 'show_filters', 'clear_map_show_filters', 1 );

		// Filter panel appearance settings.
		$filters_width            = $this->get_setting( $atts, 'filters_width', 'clear_map_filters_width', '320px' );
		$filters_height           = $this->get_setting( $atts, 'filters_height', 'clear_map_filters_height', 'auto' );
		$filters_bg_color         = $this->get_setting( $atts, 'filters_bg_color', 'clear_map_filters_bg_color', '#FBF8F1' );
		$filters_bg_transparent   = $this->get_setting( $atts, 'filters_bg_transparent', 'clear_map_filters_bg_transparent', 0 );
		$filters_frosted          = $this->get_setting( $atts, 'filters_frosted', 'clear_map_filters_frosted', 0 );
		$filters_show_header      = $this->get_setting( $atts, 'filters_show_header', 'clear_map_filters_show_header', 1 );
		$filters_style            = $this->get_setting( $atts, 'filters_style', 'clear_map_filters_style', 'list' );
		$filters_pill_border      = $this->get_setting( $atts, 'filters_pill_border', 'clear_map_filters_pill_border', 'category' );
		$filters_pill_border_color = $this->get_setting( $atts, 'filters_pill_border_color', 'clear_map_filters_pill_border_color', '#666666' );
		$filters_pill_bg          = $this->get_setting( $atts, 'filters_pill_bg', 'clear_map_filters_pill_bg', 'transparent' );
		$filters_pill_bg_color    = $this->get_setting( $atts, 'filters_pill_bg_color', 'clear_map_filters_pill_bg_color', '#ffffff' );
		$filters_show_items       = $this->get_setting( $atts, 'filters_show_items', 'clear_map_filters_show_items', 1 );

		// Convert string values to proper types for boolean comparisons.
		$show_subway_lines      = 1 === (int) $show_subway_lines;
		$show_filters           = 1 === (int) $show_filters;
		$filters_bg_transparent = 1 === (int) $filters_bg_transparent;
		$filters_frosted        = 1 === (int) $filters_frosted;
		$filters_show_header    = 1 === (int) $filters_show_header;
		$filters_show_items     = 1 === (int) $filters_show_items;

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
			// Filter panel settings for JS.
			'filtersStyle'        => $filters_style,
			'showItems'           => $filters_show_items,
			'pillBorderMode'      => $filters_pill_border,
			'pillBorderColor'     => $filters_pill_border_color,
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

		// Build filter panel classes.
		$filter_classes   = array( 'clear-map-filters' );
		$filter_classes[] = 'filter-style-' . sanitize_html_class( $filters_style );

		if ( $filters_frosted ) {
			$filter_classes[] = 'filters-frosted';
		}

		if ( ! $filters_show_header ) {
			$filter_classes[] = 'no-header';
		}

		if ( ! $filters_show_items ) {
			$filter_classes[] = 'no-items';
		}

		// Add class for transparent background (to remove shadow).
		if ( $filters_bg_transparent ) {
			$filter_classes[] = 'bg-transparent';
		}

		// Add class for frosted pill backgrounds.
		if ( 'pills' === $filters_style && 'frosted' === $filters_pill_bg ) {
			$filter_classes[] = 'pills-frosted';
		}

		$filter_class = implode( ' ', $filter_classes );

		// Build filter panel inline styles.
		$filter_inline_style = '';

		// Width.
		if ( $filters_width ) {
			$filter_inline_style .= 'width: ' . esc_attr( $filters_width ) . ';';
		}

		// Height.
		if ( $filters_height && 'auto' !== $filters_height ) {
			$filter_inline_style .= 'height: ' . esc_attr( $filters_height ) . '; max-height: ' . esc_attr( $filters_height ) . ';';
		}

		// Background color.
		if ( $filters_bg_transparent ) {
			$filter_inline_style .= 'background-color: transparent;';
		} elseif ( $filters_bg_color ) {
			$filter_inline_style .= 'background-color: ' . esc_attr( $filters_bg_color ) . ';';
		}

		ob_start();
		?>
		<div<?php echo $wrapper_id; ?> class="<?php echo esc_attr( $wrapper_class ); ?>" data-map-id="<?php echo esc_attr( $map_id ); ?>" data-js-var="<?php echo esc_attr( $js_var_name ); ?>" style="height: <?php echo esc_attr( $height ); ?>;">
			<div id="<?php echo esc_attr( $map_id ); ?>" class="clear-map"></div>
			<?php if ( $show_filters ) : ?>
			<div class="<?php echo esc_attr( $filter_class ); ?>" id="<?php echo esc_attr( $map_id ); ?>-filters" style="<?php echo esc_attr( $filter_inline_style ); ?>">
				<?php if ( $filters_show_header ) : ?>
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
								<?php if ( isset( $pois[ $cat_key ] ) ) : ?>
									<?php foreach ( $pois[ $cat_key ] as $index => $poi ) : ?>
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
