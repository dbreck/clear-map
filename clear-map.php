<?php
/**
 * Plugin Name: Clear Map
 * Description: Interactive map with POI filtering and category management. Import locations via KML, geocode addresses, and display on customizable Mapbox maps.
 * Version: 1.4.1
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
define('CLEAR_MAP_VERSION', '1.4.1');
define('CLEAR_MAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CLEAR_MAP_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Load data migration script (only runs if CLEAR_MAP_MIGRATE is defined in wp-config.php)
require_once CLEAR_MAP_PLUGIN_PATH . 'migrate-data.php';

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
        check_ajax_referer('clear_map_geocode_building', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $address = get_option('clear_map_building_address', '');

        if (empty($address)) {
            wp_send_json_error('No building address found. Please enter an address and try again.');
        }

        $api_handler = new Clear_Map_API_Handler();
        $result = $api_handler->geocode_address($address, 'Building Address');

        if ($result && !isset($result['error'])) {
            update_option('clear_map_building_lat', $result['lat']);
            update_option('clear_map_building_lng', $result['lng']);

            $this->log_activity('Building address geocoded: ' . $address);

            wp_send_json_success(array(
                'message' => 'Building address geocoded successfully!',
                'lat' => $result['lat'],
                'lng' => $result['lng'],
                'address' => $result['formatted_address'] ?? $address
            ));
        } else {
            $error_message = $result['message'] ?? 'Unknown error';
            wp_send_json_error('Geocoding failed: ' . $error_message);
        }
    }
}

// Initialize the plugin
new ClearMap();
