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

		// Get filter panel appearance settings.
		$filters_style       = get_option( 'clear_map_filters_style', 'list' );
		$filters_show_items  = 1 === (int) get_option( 'clear_map_filters_show_items', 1 );
		$filters_pill_border = get_option( 'clear_map_filters_pill_border', 'category' );
		$filters_pill_color  = get_option( 'clear_map_filters_pill_border_color', '#666666' );

		// Prepare data for JS.
		$map_data = array(
			'mapboxToken'         => get_option( 'clear_map_mapbox_token' ),
			'centerLat'           => floatval( $atts['center_lat'] ),
			'centerLng'           => floatval( $atts['center_lng'] ),
			'zoom'                => intval( $atts['zoom'] ),
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
			'clusterDistance'     => get_option( 'clear_map_cluster_distance', 50 ),
			'clusterMinPoints'    => get_option( 'clear_map_cluster_min_points', 3 ),
			'zoomThreshold'       => get_option( 'clear_map_zoom_threshold', 15 ),
			'showSubwayLines'     => 1 === (int) get_option( 'clear_map_show_subway_lines', 0 ),
			'subwayDataUrl'       => CLEAR_MAP_PLUGIN_URL . 'assets/data/nyc-subway-lines.geojson',
			// Filter panel settings for JS.
			'filtersStyle'        => $filters_style,
			'showItems'           => $filters_show_items,
			'pillBorderMode'      => $filters_pill_border,
			'pillBorderColor'     => $filters_pill_color,
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
		$filter_classes = array( 'clear-map-filters' );
		$filter_classes[] = 'filter-style-' . sanitize_html_class( $filters_style );

		if ( 1 === (int) get_option( 'clear_map_filters_frosted', 0 ) ) {
			$filter_classes[] = 'filters-frosted';
		}

		if ( 1 !== (int) get_option( 'clear_map_filters_show_header', 1 ) ) {
			$filter_classes[] = 'no-header';
		}

		if ( ! $filters_show_items ) {
			$filter_classes[] = 'no-items';
		}

		// Add class for transparent background (to remove shadow).
		if ( 1 === (int) get_option( 'clear_map_filters_bg_transparent', 0 ) ) {
			$filter_classes[] = 'bg-transparent';
		}

		$filter_class = implode( ' ', $filter_classes );

		// Build filter panel inline styles.
		$filter_inline_style = '';

		// Width.
		$filter_width = get_option( 'clear_map_filters_width', '320px' );
		if ( $filter_width ) {
			$filter_inline_style .= 'width: ' . esc_attr( $filter_width ) . ';';
		}

		// Height.
		$filter_height = get_option( 'clear_map_filters_height', 'auto' );
		if ( $filter_height && 'auto' !== $filter_height ) {
			$filter_inline_style .= 'height: ' . esc_attr( $filter_height ) . '; max-height: ' . esc_attr( $filter_height ) . ';';
		}

		// Background color.
		if ( 1 === (int) get_option( 'clear_map_filters_bg_transparent', 0 ) ) {
			$filter_inline_style .= 'background-color: transparent;';
		} else {
			$bg_color = get_option( 'clear_map_filters_bg_color', '#FBF8F1' );
			if ( $bg_color ) {
				$filter_inline_style .= 'background-color: ' . esc_attr( $bg_color ) . ';';
			}
		}

		ob_start();
		?>
		<div<?php echo $wrapper_id; ?> class="<?php echo esc_attr( $wrapper_class ); ?>" data-map-id="<?php echo esc_attr( $map_id ); ?>" data-js-var="<?php echo esc_attr( $js_var_name ); ?>" style="height: <?php echo esc_attr( $atts['height'] ); ?>;">
			<div id="<?php echo esc_attr( $map_id ); ?>" class="clear-map"></div>
			<?php if ( 1 === (int) get_option( 'clear_map_show_filters', 1 ) ) : ?>
			<div class="<?php echo esc_attr( $filter_class ); ?>" id="<?php echo esc_attr( $map_id ); ?>-filters" style="<?php echo esc_attr( $filter_inline_style ); ?>">
				<?php if ( 1 === (int) get_option( 'clear_map_filters_show_header', 1 ) ) : ?>
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
					<?php if ( 1 === (int) get_option( 'clear_map_show_subway_lines', 0 ) ) : ?>
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
					// Determine pill border color for inline styles.
					$pill_border_inline = '';
					if ( 'pills' === $filters_style && 'custom' === $filters_pill_border ) {
						$pill_border_inline = '--pill-border-color: ' . esc_attr( $filters_pill_color ) . ';';
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
