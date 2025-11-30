<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Clear_Map_Renderer
{

    public function render($atts)
    {
        $map_id = 'clear-map-' . uniqid();
        $js_var_name = str_replace('-', '_', $map_id); // Sanitize for JavaScript

        // Get all data
        $categories = get_option('clear_map_categories', array());
        $pois = get_option('clear_map_pois', array());
        $building_address = get_option('clear_map_building_address', '');

        // Get stored building coordinates (geocoded separately via admin)
        $building_lat = get_option('clear_map_building_lat', null);
        $building_lng = get_option('clear_map_building_lng', null);

        $building_coords = null;
        if ($building_lat && $building_lng) {
            $building_coords = array(
                'lat' => floatval($building_lat),
                'lng' => floatval($building_lng)
            );
        }

        // Prepare data for JS
        $map_data = array(
            'mapboxToken' => get_option('clear_map_mapbox_token'),
            'centerLat' => floatval($atts['center_lat']),
            'centerLng' => floatval($atts['center_lng']),
            'zoom' => intval($atts['zoom']),
            'buildingCoords' => $building_coords,
            'buildingAddress' => get_option('clear_map_building_address', $building_address),
            'buildingIconWidth' => get_option('clear_map_building_icon_width', '40px'),
            'buildingIconSVG' => get_option('clear_map_building_icon_svg', ''),
            'buildingIconPNG' => get_option('clear_map_building_icon_png', ''),
            'buildingPhone' => get_option('clear_map_building_phone', ''),
            'buildingEmail' => get_option('clear_map_building_email', ''),
            'buildingDescription' => get_option('clear_map_building_description', ''),
            'categories' => $categories,
            'pois' => $pois,
            'clusterDistance' => get_option('clear_map_cluster_distance', 50),
            'clusterMinPoints' => get_option('clear_map_cluster_min_points', 3),
            'zoomThreshold' => get_option('clear_map_zoom_threshold', 15),
            'showSubwayLines' => get_option('clear_map_show_subway_lines', 0) == 1,
            'subwayDataUrl' => CLEAR_MAP_PLUGIN_URL . 'assets/data/nyc-subway-lines.geojson'
        );

        // Enqueue assets
        wp_enqueue_script(
            'clear-map-frontend',
            CLEAR_MAP_PLUGIN_URL . 'assets/js/map.js',
            array('jquery', 'mapbox-gl'),
            CLEAR_MAP_VERSION,
            true
        );

        wp_localize_script('clear-map-frontend', 'clearMapData_' . $js_var_name, $map_data);

        wp_enqueue_style(
            'clear-map-frontend',
            CLEAR_MAP_PLUGIN_URL . 'assets/css/map.css',
            array('mapbox-gl'),
            CLEAR_MAP_VERSION
        );

        ob_start();
?>
        <div class="clear-map-container" data-map-id="<?php echo esc_attr($map_id); ?>" data-js-var="<?php echo esc_attr($js_var_name); ?>" style="height: <?php echo esc_attr($atts['height']); ?>;">
            <div id="<?php echo esc_attr($map_id); ?>" class="clear-map"></div>
            <?php if (get_option('clear_map_show_filters', 1) == 1): ?>
            <div class="clear-map-filters" id="<?php echo esc_attr($map_id); ?>-filters">
                <div class="filters-header">
                    <h5>The Area</h5>
                    <button class="toggle-filters" type="button">
                        <span class="screen-reader-text">Toggle Filters</span>
                        <svg width="20" height="20" viewBox="0 0 20 20">
                            <path d="M15 7L10 12L5 7" stroke="currentColor" stroke-width="2" fill="none" />
                        </svg>
                    </button>
                </div>

                <div class="filters-content">
                    <?php if (get_option('clear_map_show_subway_lines', 0) == 1): ?>
                        <div class="subway-toggle-section">
                            <div class="subway-toggle-header">
                                <label class="subway-toggle-label">
                                    <input type="checkbox" class="subway-toggle-checkbox" checked />
                                    <span class="subway-toggle-switch"></span>
                                    <span class="subway-toggle-text">Show Subway Lines</span>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($categories as $cat_key => $category): ?>
                        <div class="filter-category" data-category="<?php echo esc_attr($cat_key); ?>">
                            <div class="category-header">
                                <div class="category-toggle">
                                    <span class="category-icon">
                                        <span class="category-dot" style="background-color: <?php echo esc_attr($category['color']); ?>"></span>
                                        <span class="category-x" style="color: <?php echo esc_attr($category['color']); ?>;">✕</span>
                                    </span>
                                    <span class="category-name"><?php echo esc_html($category['name']); ?></span>
                                </div>
                                <button class="category-expand" type="button">
                                    <svg width="16" height="16" viewBox="0 0 16 16">
                                        <path d="M6 4L10 8L6 12" stroke="currentColor" stroke-width="2" fill="none" />
                                    </svg>
                                </button>
                            </div>

                            <div class="category-pois" style="display: none;" aria-expanded="false">
                                <?php if (isset($pois[$cat_key])): ?>
                                    <?php foreach ($pois[$cat_key] as $index => $poi): ?>
                                        <div class="poi-item" data-poi="<?php echo esc_attr($cat_key . '-' . $index); ?>">
                                            <span class="poi-dot" style="background-color: <?php echo esc_attr($category['color']); ?>"></span>
                                            <span class="poi-name"><?php echo esc_html($poi['name']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
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
