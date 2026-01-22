<?php
/**
 * Plugin Name: Clear Map
 * Description: Interactive map with POI filtering and category management. Import locations via KML, geocode addresses, and display on customizable Mapbox maps.
 * Version: 2.1.2
 * Author: Danny Breckenridge
 * Plugin URI: https://github.com/dbreck/clear-map
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CLEAR_MAP_VERSION', '2.1.2');
define('CLEAR_MAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CLEAR_MAP_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Load data migration script (only runs if file exists and CLEAR_MAP_MIGRATE is defined)
if (file_exists(CLEAR_MAP_PLUGIN_PATH . 'migrate-data.php')) {
    require_once CLEAR_MAP_PLUGIN_PATH . 'migrate-data.php';
}

// Load GitHub updater for automatic updates
require_once CLEAR_MAP_PLUGIN_PATH . 'includes/class-github-updater.php';

// Initialize GitHub updater
if (is_admin()) {
    new Clear_Map_GitHub_Updater(__FILE__);
}

class ClearMap {

    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        add_action('wp_ajax_clear_map_geocode_cache', array($this, 'clear_geocode_cache'));
        add_action('wp_ajax_clear_map_clear_all_pois', array($this, 'clear_all_pois'));
        add_action('wp_ajax_clear_map_import_kml_pois', array($this, 'handle_kml_import'));
        add_action('wp_ajax_clear_map_save_imported_pois', array($this, 'save_imported_pois'));
        add_action('wp_ajax_clear_map_run_geocoding', array($this, 'run_manual_geocoding'));
        add_action('wp_ajax_clear_map_geocode_building', array($this, 'ajax_geocode_building'));
        add_action('update_option_clear_map_building_address', array($this, 'geocode_building_address'), 10, 2);

        // POI management AJAX endpoints.
        add_action('wp_ajax_clear_map_get_poi', array($this, 'ajax_get_poi'));
        add_action('wp_ajax_clear_map_save_poi', array($this, 'ajax_save_poi'));
        add_action('wp_ajax_clear_map_delete_poi', array($this, 'ajax_delete_poi'));
        add_action('wp_ajax_clear_map_bulk_action', array($this, 'ajax_bulk_action'));
        add_action('wp_ajax_clear_map_export_pois', array($this, 'ajax_export_pois'));

        // Category management AJAX endpoints.
        add_action('wp_ajax_clear_map_save_category', array($this, 'ajax_save_category'));
        add_action('wp_ajax_clear_map_delete_category', array($this, 'ajax_delete_category'));
        add_action('wp_ajax_clear_map_reorder_categories', array($this, 'ajax_reorder_categories'));

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function init() {
        $this->load_dependencies();
        $this->init_components();
    }

    private function load_dependencies() {
        require_once CLEAR_MAP_PLUGIN_PATH . 'includes/class-admin.php';
        require_once CLEAR_MAP_PLUGIN_PATH . 'includes/class-frontend.php';
        require_once CLEAR_MAP_PLUGIN_PATH . 'includes/class-map-renderer.php';
        require_once CLEAR_MAP_PLUGIN_PATH . 'includes/class-api-handler.php';
        require_once CLEAR_MAP_PLUGIN_PATH . 'includes/class-assets.php';
        require_once CLEAR_MAP_PLUGIN_PATH . 'includes/class-kml-parser.php';
        require_once CLEAR_MAP_PLUGIN_PATH . 'includes/class-wpbakery.php';
    }

    private function init_components() {
        new Clear_Map_Admin();
        new Clear_Map_Frontend();
        new Clear_Map_Assets();
        new Clear_Map_WPBakery();
    }
    
    public function clear_geocode_cache() {
        check_ajax_referer('clear_map_geocode_cache', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $api_handler = new Clear_Map_API_Handler();
        $api_handler->clear_geocode_cache();
        
        // Log activity
        $this->log_activity('Geocode cache cleared');
        
        wp_send_json_success('Cache cleared');
    }
    
    public function handle_kml_import() {
        check_ajax_referer('clear_map_import_kml_pois', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_FILES['kml_file'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $file = $_FILES['kml_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('File upload error: ' . $file['error']);
        }
        
        $kml_parser = new Clear_Map_KML_Parser();
        $parsed_data = $kml_parser->parse($file['tmp_name']);
        
        if (is_wp_error($parsed_data)) {
            wp_send_json_error($parsed_data->get_error_message());
        }
        
        // Store the parsed data temporarily
        set_transient('clear_map_import_data', $parsed_data, HOUR_IN_SECONDS);
        
        $categories_text = '';
        if (isset($parsed_data['categories']) && !empty($parsed_data['categories'])) {
            $categories_text = ' in ' . count($parsed_data['categories']) . ' categories';
        }
        
        $this->log_activity('KML file imported: ' . $parsed_data['total'] . ' POIs found' . $categories_text);
        
        wp_send_json_success($parsed_data);
    }
    
    public function clear_all_pois() {
        check_ajax_referer('clear_map_clear_all_pois', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Get current data for logging
        $current_pois = get_option('clear_map_pois', array());
        $current_categories = get_option('clear_map_categories', array());
        
        $total_pois = 0;
        foreach ($current_pois as $category_pois) {
            $total_pois += count($category_pois);
        }
        
        // Clear all POIs and categories
        update_option('clear_map_pois', array());
        update_option('clear_map_categories', array());
        
        // Clear geocode cache as well
        $api_handler = new Clear_Map_API_Handler();
        $api_handler->clear_geocode_cache();
        
        $this->log_activity("All POIs cleared: Removed $total_pois POIs across " . count($current_categories) . " categories");
        
        wp_send_json_success(array(
            'message' => "Successfully cleared $total_pois POIs and " . count($current_categories) . " categories.",
            'cleared_pois' => $total_pois,
            'cleared_categories' => count($current_categories)
        ));
    }
    
    public function save_imported_pois() {
        check_ajax_referer('clear_map_import_kml_pois', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $import_data = get_transient('clear_map_import_data');
        if (!$import_data) {
            wp_send_json_error('Import data not found. Please try importing again.');
        }
        
        $category_assignments = $_POST['category_assignments'] ?? array();
        $replace_existing = ($_POST['replace_existing'] ?? 'false') === 'true';
        
        // Create categories based on detected categories
        $existing_categories = get_option('clear_map_categories', array());
        $new_categories = $existing_categories;
        
        // Add detected categories
        if (isset($import_data['category_names'])) {
            foreach ($import_data['category_names'] as $key => $name) {
                if (!isset($new_categories[$key])) {
                    $new_categories[$key] = array(
                        'name' => $name,
                        'color' => $this->generate_category_color($key)
                    );
                }
            }
        }

        // Save only the categories that were imported from KML
        update_option('clear_map_categories', $new_categories);
        
        // Organize POIs by category
        $new_pois = array();
        $processed_count = 0;
        $import_stats = array(
            'total_processed' => 0,
            'with_kml_coordinates' => 0,
            'needs_geocoding' => 0,
            'coordinate_sources' => array()
        );
        
        // Use the organized POI data if available (auto-categorized)
        if (isset($import_data['pois_by_category']) && !empty($import_data['pois_by_category'])) {
            // POIs are already organized by category from KML folders
            foreach ($import_data['pois_by_category'] as $category => $pois) {
                if (!isset($new_pois[$category])) {
                    $new_pois[$category] = array();
                }
                foreach ($pois as $poi) {
                    $new_pois[$category][] = $poi;
                    $processed_count++;
                    $import_stats['total_processed']++;
                    
                    // Track coordinate sources
                    $source = $poi['coordinate_source'] ?? 'unknown';
                    if (!isset($import_stats['coordinate_sources'][$source])) {
                        $import_stats['coordinate_sources'][$source] = 0;
                    }
                    $import_stats['coordinate_sources'][$source]++;
                    
                    if ($poi['lat'] && $poi['lng']) {
                        $import_stats['with_kml_coordinates']++;
                    } else {
                        $import_stats['needs_geocoding']++;
                    }
                }
            }
        } else {
            // Fall back to manual assignment (legacy behavior)
            foreach ($import_data['pois'] as $index => $poi) {
                $category = $category_assignments[$index] ?? 'general';
                
                // Ensure category exists
                if (!isset($new_categories[$category])) {
                    $category = 'general';
                }
                
                if (!isset($new_pois[$category])) {
                    $new_pois[$category] = array();
                }
                
                $new_pois[$category][] = $poi;
                $processed_count++;
                $import_stats['total_processed']++;
                
                // Track coordinate sources
                $source = $poi['coordinate_source'] ?? 'unknown';
                if (!isset($import_stats['coordinate_sources'][$source])) {
                    $import_stats['coordinate_sources'][$source] = 0;
                }
                $import_stats['coordinate_sources'][$source]++;
                
                if ($poi['lat'] && $poi['lng']) {
                    $import_stats['with_kml_coordinates']++;
                } else {
                    $import_stats['needs_geocoding']++;
                }
            }
        }
        
        // Check if POIs need forward geocoding (address → coordinates) or reverse geocoding (coordinates → address)
        $needs_forward_geocoding = false;
        $needs_reverse_geocoding = false;

        foreach ($new_pois as $category => $pois) {
            foreach ($pois as $poi) {
                // Need forward geocoding if we have address but no coordinates
                if (!empty($poi['address']) && (empty($poi['lat']) || empty($poi['lng']))) {
                    $needs_forward_geocoding = true;
                }
                // Need reverse geocoding if we have coordinates but no address
                if (!empty($poi['lat']) && !empty($poi['lng']) && empty($poi['address'])) {
                    $needs_reverse_geocoding = true;
                }
            }
        }

        $api_handler = new Clear_Map_API_Handler();

        // Run forward geocoding if needed
        if ($needs_forward_geocoding) {
            $geocoding_result = $api_handler->geocode_pois($new_pois);
            if (isset($geocoding_result['pois'])) {
                $new_pois = $geocoding_result['pois'];
                $import_stats['forward_geocoding_stats'] = $geocoding_result['stats'];
            }
        }

        // Run reverse geocoding if needed
        if ($needs_reverse_geocoding) {
            $reverse_result = $api_handler->reverse_geocode_pois($new_pois);
            if (isset($reverse_result['pois'])) {
                $new_pois = $reverse_result['pois'];
                $import_stats['reverse_geocoding_stats'] = $reverse_result['stats'];
            }
        }
        
        // Save POIs
        if ($replace_existing) {
            update_option('clear_map_pois', $new_pois);
            $action = 'replaced all POIs with';
        } else {
            $existing_pois = get_option('clear_map_pois', array());
            foreach ($new_pois as $category => $pois) {
                if (!isset($existing_pois[$category])) {
                    $existing_pois[$category] = array();
                }
                $existing_pois[$category] = array_merge($existing_pois[$category], $pois);
            }
            update_option('clear_map_pois', $existing_pois);
            $action = 'added';
        }
        
        // Clear the temporary data
        delete_transient('clear_map_import_data');
        
        // Create detailed log message
        $log_message = "POIs imported: $action $processed_count POIs across " . count($new_pois) . " categories";
        if (isset($import_stats['coordinate_sources'])) {
            $source_details = array();
            foreach ($import_stats['coordinate_sources'] as $source => $count) {
                $source_details[] = "$count from $source";
            }
            $log_message .= ' (' . implode(', ', $source_details) . ')';
        }
        
        $this->log_activity($log_message);
        
        wp_send_json_success(array(
            'imported' => $processed_count,
            'categories' => array_keys($new_pois),
            'action' => $action,
            'message' => "Successfully $action $processed_count POIs across " . count($new_pois) . " categories!",
            'import_stats' => $import_stats
        ));
    }
    
    private function generate_category_color($category_key) {
        // Generate consistent colors for categories
        $colors = array(
            'parks' => '#4CAF50',
            'restaurants' => '#D4A574', 
            'transportation' => '#2196F3',
            'education' => '#9C27B0',
            'shopping' => '#A68B5B',
            'arts_culture' => '#8B7355',
            'fitness' => '#6B5B73',
            'services' => '#9B8B6B',
            'entertainment' => '#E91E63',
            'healthcare' => '#FF5722'
        );
        
        if (isset($colors[$category_key])) {
            return $colors[$category_key];
        }
        
        // Generate a color based on the category name
        $hash = md5($category_key);
        $r = hexdec(substr($hash, 0, 2));
        $g = hexdec(substr($hash, 2, 2));
        $b = hexdec(substr($hash, 4, 2));
        
        // Ensure the color is not too light or too dark
        $r = max(80, min(200, $r));
        $g = max(80, min(200, $g));
        $b = max(80, min(200, $b));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    public function activate() {
        $this->create_default_data();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }

    public function geocode_building_address($old_value, $new_value) {
        // Only geocode if address actually changed and is not empty
        if ($old_value === $new_value || empty($new_value)) {
            return;
        }

        $api_handler = new Clear_Map_API_Handler();
        $result = $api_handler->geocode_address($new_value, 'Building Address');

        if ($result && !isset($result['error'])) {
            update_option('clear_map_building_lat', $result['lat']);
            update_option('clear_map_building_lng', $result['lng']);
            error_log('Clear Map: Building address geocoded successfully: ' . $result['lat'] . ', ' . $result['lng']);
        } else {
            error_log('Clear Map: Failed to geocode building address: ' . ($result['message'] ?? 'Unknown error'));
        }
    }

    private function create_default_data() {
        // Set default options
        add_option('clear_map_mapbox_token', '');
        add_option('clear_map_google_api_key', '');
        add_option('clear_map_building_icon_width', 40);
        add_option('clear_map_cluster_distance', 50);
        add_option('clear_map_cluster_min_points', 3);
        add_option('clear_map_zoom_threshold', 15);
        add_option('clear_map_show_subway_lines', 0);
        add_option('clear_map_activity', array());

        // Start with empty categories - will be created during KML import
        add_option('clear_map_categories', array());

        // Start with empty POIs - to be imported from KML
        add_option('clear_map_pois', array());

        $this->log_activity('Plugin activated');
    }
    
    private function log_activity($action) {
        $activities = get_option('clear_map_activity', array());

        // Add new activity
        array_unshift($activities, array(
            'time' => current_time('M j, Y g:i A'),
            'action' => $action
        ));

        // Keep only last 20 activities
        $activities = array_slice($activities, 0, 20);

        update_option('clear_map_activity', $activities);
    }

    public function run_manual_geocoding() {
        check_ajax_referer('clear_map_run_geocoding', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Get all POIs
        $pois = get_option('clear_map_pois', array());

        if (empty($pois)) {
            wp_send_json_error('No POIs found to geocode');
        }

        // Run reverse geocoding on POIs that have coordinates but no addresses
        $api_handler = new Clear_Map_API_Handler();
        $result = $api_handler->reverse_geocode_pois($pois);

        if (isset($result['pois'])) {
            // Save reverse geocoded POIs
            update_option('clear_map_pois', $result['pois']);

            $stats = $result['stats'];
            $message = sprintf(
                'Reverse geocoding complete: %d total POIs, %d already had addresses, %d successfully reverse geocoded, %d failed',
                $stats['total_processed'],
                $stats['already_had_addresses'],
                $stats['successfully_reverse_geocoded'],
                $stats['failed_reverse_geocoding']
            );

            $this->log_activity($message);

            wp_send_json_success(array(
                'message' => $message,
                'stats' => $stats
            ));
        } else {
            wp_send_json_error('Reverse geocoding failed');
        }
    }

    public function ajax_geocode_building() {
        check_ajax_referer( 'clear_map_geocode_building', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        // Get address from POST data (current input value) or fall back to saved option.
        $address = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';

        if ( empty( $address ) ) {
            $address = get_option( 'clear_map_building_address', '' );
        }

        if ( empty( $address ) ) {
            wp_send_json_error( 'No building address found. Please enter an address and save settings first.' );
        }

        // Also save the address to the option so it persists.
        update_option( 'clear_map_building_address', $address );

        $api_handler = new Clear_Map_API_Handler();
        $result      = $api_handler->geocode_address( $address, 'Building Address' );

        if ( $result && ! isset( $result['error'] ) ) {
            update_option( 'clear_map_building_lat', $result['lat'] );
            update_option( 'clear_map_building_lng', $result['lng'] );

            $this->log_activity( 'Building address geocoded: ' . $address );

            wp_send_json_success(
                array(
                    'message' => 'Building address geocoded successfully!',
                    'lat'     => $result['lat'],
                    'lng'     => $result['lng'],
                    'address' => isset( $result['formatted_address'] ) ? $result['formatted_address'] : $address,
                )
            );
        } else {
            $error_message = isset( $result['message'] ) ? $result['message'] : 'Unknown error';
            wp_send_json_error( 'Geocoding failed: ' . $error_message );
        }
    }

    /**
     * AJAX: Get single POI data.
     */
    public function ajax_get_poi() {
        check_ajax_referer( 'clear_map_manage_pois', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $poi_id = isset( $_POST['poi_id'] ) ? sanitize_text_field( wp_unslash( $_POST['poi_id'] ) ) : '';

        if ( empty( $poi_id ) ) {
            wp_send_json_error( 'POI ID is required' );
        }

        // Parse unique_id format: category_key|index.
        $parts = explode( '|', $poi_id );
        if ( count( $parts ) !== 2 ) {
            wp_send_json_error( 'Invalid POI ID format' );
        }

        $category_key = $parts[0];
        $index        = intval( $parts[1] );

        $pois = get_option( 'clear_map_pois', array() );

        if ( ! isset( $pois[ $category_key ][ $index ] ) ) {
            wp_send_json_error( 'POI not found' );
        }

        $poi               = $pois[ $category_key ][ $index ];
        $poi['category']   = $category_key;
        $poi['poi_index']  = $index;
        $poi['unique_id']  = $poi_id;

        wp_send_json_success( $poi );
    }

    /**
     * AJAX: Save single POI.
     */
    public function ajax_save_poi() {
        check_ajax_referer( 'clear_map_manage_pois', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $poi_id       = isset( $_POST['poi_id'] ) ? sanitize_text_field( wp_unslash( $_POST['poi_id'] ) ) : '';
        $new_category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $is_new       = isset( $_POST['is_new'] ) && 'true' === $_POST['is_new'];

        // Sanitize POI data.
        $poi_data = array(
            'name'               => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
            'address'            => isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '',
            'description'        => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
            'website'            => isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '',
            'photo'              => isset( $_POST['photo'] ) ? esc_url_raw( wp_unslash( $_POST['photo'] ) ) : '',
            'logo'               => isset( $_POST['logo'] ) ? esc_url_raw( wp_unslash( $_POST['logo'] ) ) : '',
            'lat'                => isset( $_POST['lat'] ) ? floatval( $_POST['lat'] ) : '',
            'lng'                => isset( $_POST['lng'] ) ? floatval( $_POST['lng'] ) : '',
            'coordinate_source'  => isset( $_POST['coordinate_source'] ) ? sanitize_text_field( wp_unslash( $_POST['coordinate_source'] ) ) : '',
            'needs_geocoding'    => isset( $_POST['needs_geocoding'] ) ? sanitize_text_field( wp_unslash( $_POST['needs_geocoding'] ) ) : '',
            'reverse_geocoded'   => isset( $_POST['reverse_geocoded'] ) ? sanitize_text_field( wp_unslash( $_POST['reverse_geocoded'] ) ) : '',
            'geocoded_address'   => isset( $_POST['geocoded_address'] ) ? sanitize_text_field( wp_unslash( $_POST['geocoded_address'] ) ) : '',
            'geocoding_precision'=> isset( $_POST['geocoding_precision'] ) ? sanitize_text_field( wp_unslash( $_POST['geocoding_precision'] ) ) : '',
        );

        // Validate required fields.
        if ( empty( $poi_data['name'] ) ) {
            wp_send_json_error( 'POI name is required' );
        }

        if ( empty( $new_category ) ) {
            wp_send_json_error( 'Category is required' );
        }

        $pois       = get_option( 'clear_map_pois', array() );
        $categories = get_option( 'clear_map_categories', array() );

        // Verify category exists.
        if ( ! isset( $categories[ $new_category ] ) ) {
            wp_send_json_error( 'Invalid category' );
        }

        // Ensure category array exists.
        if ( ! isset( $pois[ $new_category ] ) ) {
            $pois[ $new_category ] = array();
        }

        if ( $is_new ) {
            // Add new POI.
            $pois[ $new_category ][] = $poi_data;
            $new_index               = count( $pois[ $new_category ] ) - 1;
            $this->log_activity( 'POI added: ' . $poi_data['name'] . ' to ' . $categories[ $new_category ]['name'] );

            update_option( 'clear_map_pois', $pois );

            wp_send_json_success(
                array(
                    'message'   => 'POI added successfully',
                    'poi_id'    => $new_category . '|' . $new_index,
                    'action'    => 'added',
                )
            );
        } else {
            // Update existing POI.
            $parts = explode( '|', $poi_id );
            if ( count( $parts ) !== 2 ) {
                wp_send_json_error( 'Invalid POI ID format' );
            }

            $old_category = $parts[0];
            $old_index    = intval( $parts[1] );

            if ( ! isset( $pois[ $old_category ][ $old_index ] ) ) {
                wp_send_json_error( 'POI not found' );
            }

            // Check if category changed.
            if ( $old_category !== $new_category ) {
                // Remove from old category.
                array_splice( $pois[ $old_category ], $old_index, 1 );

                // Add to new category.
                $pois[ $new_category ][] = $poi_data;
                $new_index               = count( $pois[ $new_category ] ) - 1;

                $this->log_activity( 'POI moved: ' . $poi_data['name'] . ' from ' . $categories[ $old_category ]['name'] . ' to ' . $categories[ $new_category ]['name'] );

                update_option( 'clear_map_pois', $pois );

                wp_send_json_success(
                    array(
                        'message'   => 'POI updated and moved successfully',
                        'poi_id'    => $new_category . '|' . $new_index,
                        'action'    => 'moved',
                    )
                );
            } else {
                // Update in place.
                $pois[ $old_category ][ $old_index ] = $poi_data;

                $this->log_activity( 'POI updated: ' . $poi_data['name'] );

                update_option( 'clear_map_pois', $pois );

                wp_send_json_success(
                    array(
                        'message'   => 'POI updated successfully',
                        'poi_id'    => $poi_id,
                        'action'    => 'updated',
                    )
                );
            }
        }
    }

    /**
     * AJAX: Delete single POI.
     */
    public function ajax_delete_poi() {
        check_ajax_referer( 'clear_map_manage_pois', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $poi_id = isset( $_POST['poi_id'] ) ? sanitize_text_field( wp_unslash( $_POST['poi_id'] ) ) : '';

        if ( empty( $poi_id ) ) {
            wp_send_json_error( 'POI ID is required' );
        }

        $parts = explode( '|', $poi_id );
        if ( count( $parts ) !== 2 ) {
            wp_send_json_error( 'Invalid POI ID format' );
        }

        $category_key = $parts[0];
        $index        = intval( $parts[1] );

        $pois = get_option( 'clear_map_pois', array() );

        if ( ! isset( $pois[ $category_key ][ $index ] ) ) {
            wp_send_json_error( 'POI not found' );
        }

        $poi_name = $pois[ $category_key ][ $index ]['name'];

        // Remove POI.
        array_splice( $pois[ $category_key ], $index, 1 );

        update_option( 'clear_map_pois', $pois );

        $this->log_activity( 'POI deleted: ' . $poi_name );

        wp_send_json_success(
            array(
                'message' => 'POI deleted successfully',
            )
        );
    }

    /**
     * AJAX: Bulk action on POIs.
     */
    public function ajax_bulk_action() {
        check_ajax_referer( 'clear_map_manage_pois', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $action  = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
        $poi_ids = isset( $_POST['poi_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['poi_ids'] ) ) : array();

        if ( empty( $action ) ) {
            wp_send_json_error( 'Action is required' );
        }

        if ( empty( $poi_ids ) ) {
            wp_send_json_error( 'No POIs selected' );
        }

        $pois       = get_option( 'clear_map_pois', array() );
        $categories = get_option( 'clear_map_categories', array() );
        $count      = 0;

        if ( 'delete' === $action ) {
            // Sort by index descending within each category to avoid index shift issues.
            $grouped = array();
            foreach ( $poi_ids as $poi_id ) {
                $parts = explode( '|', $poi_id );
                if ( count( $parts ) === 2 ) {
                    $cat   = $parts[0];
                    $idx   = intval( $parts[1] );
                    if ( ! isset( $grouped[ $cat ] ) ) {
                        $grouped[ $cat ] = array();
                    }
                    $grouped[ $cat ][] = $idx;
                }
            }

            foreach ( $grouped as $cat => $indices ) {
                rsort( $indices ); // Delete from highest index first.
                foreach ( $indices as $idx ) {
                    if ( isset( $pois[ $cat ][ $idx ] ) ) {
                        array_splice( $pois[ $cat ], $idx, 1 );
                        $count++;
                    }
                }
            }

            update_option( 'clear_map_pois', $pois );
            $this->log_activity( "Bulk deleted $count POIs" );

            wp_send_json_success(
                array(
                    'message' => sprintf( '%d POI(s) deleted successfully', $count ),
                    'count'   => $count,
                )
            );
        } elseif ( strpos( $action, 'move_to_' ) === 0 ) {
            // Move to category.
            $target_category = str_replace( 'move_to_', '', $action );

            if ( ! isset( $categories[ $target_category ] ) ) {
                wp_send_json_error( 'Invalid target category' );
            }

            if ( ! isset( $pois[ $target_category ] ) ) {
                $pois[ $target_category ] = array();
            }

            // Group by category and sort indices descending.
            $grouped = array();
            foreach ( $poi_ids as $poi_id ) {
                $parts = explode( '|', $poi_id );
                if ( count( $parts ) === 2 ) {
                    $cat   = $parts[0];
                    $idx   = intval( $parts[1] );
                    if ( ! isset( $grouped[ $cat ] ) ) {
                        $grouped[ $cat ] = array();
                    }
                    $grouped[ $cat ][] = $idx;
                }
            }

            foreach ( $grouped as $cat => $indices ) {
                if ( $cat === $target_category ) {
                    continue; // Skip POIs already in target category.
                }

                rsort( $indices );
                foreach ( $indices as $idx ) {
                    if ( isset( $pois[ $cat ][ $idx ] ) ) {
                        $poi_data = $pois[ $cat ][ $idx ];
                        $pois[ $target_category ][] = $poi_data;
                        array_splice( $pois[ $cat ], $idx, 1 );
                        $count++;
                    }
                }
            }

            update_option( 'clear_map_pois', $pois );
            $this->log_activity( "Bulk moved $count POIs to " . $categories[ $target_category ]['name'] );

            wp_send_json_success(
                array(
                    'message' => sprintf( '%d POI(s) moved to %s', $count, $categories[ $target_category ]['name'] ),
                    'count'   => $count,
                )
            );
        } else {
            wp_send_json_error( 'Unknown action' );
        }
    }

    /**
     * AJAX: Export POIs.
     */
    public function ajax_export_pois() {
        check_ajax_referer( 'clear_map_manage_pois', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $format  = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'csv';
        $poi_ids = isset( $_POST['poi_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['poi_ids'] ) ) : array();

        $pois       = get_option( 'clear_map_pois', array() );
        $categories = get_option( 'clear_map_categories', array() );

        // Flatten POIs.
        $export_data = array();
        foreach ( $pois as $cat_key => $cat_pois ) {
            $cat_name = isset( $categories[ $cat_key ]['name'] ) ? $categories[ $cat_key ]['name'] : $cat_key;

            foreach ( $cat_pois as $idx => $poi ) {
                $unique_id = $cat_key . '|' . $idx;

                // If specific POIs selected, filter.
                if ( ! empty( $poi_ids ) && ! in_array( $unique_id, $poi_ids, true ) ) {
                    continue;
                }

                $export_data[] = array(
                    'name'           => $poi['name'] ?? '',
                    'category'       => $cat_name,
                    'address'        => $poi['address'] ?? '',
                    'description'    => $poi['description'] ?? '',
                    'website'        => $poi['website'] ?? '',
                    'photo'          => $poi['photo'] ?? '',
                    'logo'           => $poi['logo'] ?? '',
                    'lat'            => $poi['lat'] ?? '',
                    'lng'            => $poi['lng'] ?? '',
                );
            }
        }

        if ( 'json' === $format ) {
            wp_send_json_success(
                array(
                    'format' => 'json',
                    'data'   => $export_data,
                    'filename' => 'clear-map-pois-' . gmdate( 'Y-m-d' ) . '.json',
                )
            );
        } else {
            // CSV format.
            $csv_lines   = array();
            $csv_lines[] = implode( ',', array( 'Name', 'Category', 'Address', 'Description', 'Website', 'Photo', 'Logo', 'Latitude', 'Longitude' ) );

            foreach ( $export_data as $row ) {
                $csv_lines[] = implode(
                    ',',
                    array(
                        '"' . str_replace( '"', '""', $row['name'] ) . '"',
                        '"' . str_replace( '"', '""', $row['category'] ) . '"',
                        '"' . str_replace( '"', '""', $row['address'] ) . '"',
                        '"' . str_replace( '"', '""', $row['description'] ) . '"',
                        '"' . str_replace( '"', '""', $row['website'] ) . '"',
                        '"' . str_replace( '"', '""', $row['photo'] ) . '"',
                        '"' . str_replace( '"', '""', $row['logo'] ) . '"',
                        $row['lat'],
                        $row['lng'],
                    )
                );
            }

            wp_send_json_success(
                array(
                    'format'   => 'csv',
                    'data'     => implode( "\n", $csv_lines ),
                    'filename' => 'clear-map-pois-' . gmdate( 'Y-m-d' ) . '.csv',
                )
            );
        }
    }

    /**
     * AJAX: Save category.
     */
    public function ajax_save_category() {
        check_ajax_referer( 'clear_map_manage_pois', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $category_key = isset( $_POST['category_key'] ) ? sanitize_key( wp_unslash( $_POST['category_key'] ) ) : '';
        $name         = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $color        = isset( $_POST['color'] ) ? sanitize_hex_color( wp_unslash( $_POST['color'] ) ) : '#999999';
        $is_new       = isset( $_POST['is_new'] ) && 'true' === $_POST['is_new'];

        if ( empty( $name ) ) {
            wp_send_json_error( 'Category name is required' );
        }

        $categories = get_option( 'clear_map_categories', array() );

        if ( $is_new ) {
            // Generate unique key.
            $base_key = sanitize_key( str_replace( ' ', '_', strtolower( $name ) ) );
            $key      = $base_key;
            $counter  = 1;

            while ( isset( $categories[ $key ] ) ) {
                $key = $base_key . '_' . $counter;
                $counter++;
            }

            $categories[ $key ] = array(
                'name'  => $name,
                'color' => $color,
            );

            // Initialize empty POIs array for new category.
            $pois = get_option( 'clear_map_pois', array() );
            if ( ! isset( $pois[ $key ] ) ) {
                $pois[ $key ] = array();
                update_option( 'clear_map_pois', $pois );
            }

            update_option( 'clear_map_categories', $categories );
            $this->log_activity( 'Category added: ' . $name );

            wp_send_json_success(
                array(
                    'message'      => 'Category added successfully',
                    'category_key' => $key,
                    'category'     => $categories[ $key ],
                )
            );
        } else {
            if ( empty( $category_key ) || ! isset( $categories[ $category_key ] ) ) {
                wp_send_json_error( 'Category not found' );
            }

            $categories[ $category_key ] = array(
                'name'  => $name,
                'color' => $color,
            );

            update_option( 'clear_map_categories', $categories );
            $this->log_activity( 'Category updated: ' . $name );

            wp_send_json_success(
                array(
                    'message'      => 'Category updated successfully',
                    'category_key' => $category_key,
                    'category'     => $categories[ $category_key ],
                )
            );
        }
    }

    /**
     * AJAX: Delete category.
     */
    public function ajax_delete_category() {
        check_ajax_referer( 'clear_map_manage_pois', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $category_key = isset( $_POST['category_key'] ) ? sanitize_key( wp_unslash( $_POST['category_key'] ) ) : '';

        if ( empty( $category_key ) ) {
            wp_send_json_error( 'Category key is required' );
        }

        $categories = get_option( 'clear_map_categories', array() );
        $pois       = get_option( 'clear_map_pois', array() );

        if ( ! isset( $categories[ $category_key ] ) ) {
            wp_send_json_error( 'Category not found' );
        }

        $cat_name  = $categories[ $category_key ]['name'];
        $poi_count = isset( $pois[ $category_key ] ) ? count( $pois[ $category_key ] ) : 0;

        // Remove category.
        unset( $categories[ $category_key ] );
        update_option( 'clear_map_categories', $categories );

        // Remove POIs in category.
        if ( isset( $pois[ $category_key ] ) ) {
            unset( $pois[ $category_key ] );
            update_option( 'clear_map_pois', $pois );
        }

        $this->log_activity( "Category deleted: $cat_name ($poi_count POIs removed)" );

        wp_send_json_success(
            array(
                'message'          => sprintf( 'Category "%s" deleted (%d POIs removed)', $cat_name, $poi_count ),
                'pois_removed'     => $poi_count,
            )
        );
    }

    /**
     * AJAX: Reorder categories.
     */
    public function ajax_reorder_categories() {
        check_ajax_referer( 'clear_map_manage_pois', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $order = isset( $_POST['order'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['order'] ) ) : array();

        if ( empty( $order ) ) {
            wp_send_json_error( 'Order is required' );
        }

        $categories     = get_option( 'clear_map_categories', array() );
        $new_categories = array();

        foreach ( $order as $key ) {
            if ( isset( $categories[ $key ] ) ) {
                $new_categories[ $key ] = $categories[ $key ];
            }
        }

        // Add any categories that weren't in the order (shouldn't happen, but safety).
        foreach ( $categories as $key => $cat ) {
            if ( ! isset( $new_categories[ $key ] ) ) {
                $new_categories[ $key ] = $cat;
            }
        }

        update_option( 'clear_map_categories', $new_categories );
        $this->log_activity( 'Categories reordered' );

        wp_send_json_success(
            array(
                'message' => 'Categories reordered successfully',
            )
        );
    }
}

// Initialize the plugin
new ClearMap();
