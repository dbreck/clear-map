<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Clear_Map_API_Handler {

    private $mapbox_token;
    private $google_api_key; // Keep for backwards compatibility

    public function __construct() {
        $this->mapbox_token = get_option('clear_map_mapbox_token');
        $this->google_api_key = get_option('clear_map_google_api_key');
    }

    public function geocode_address($address, $poi_name = '') {
        // Use Mapbox by default, fallback to Google if Mapbox token not available
        if (!empty($this->mapbox_token)) {
            return $this->geocode_address_mapbox($address, $poi_name);
        } else if (!empty($this->google_api_key)) {
            return $this->geocode_address_google($address, $poi_name);
        } else {
            error_log('Clear Map: No Mapbox token or Google API key configured for geocoding');
            return array('error' => 'no_api_key');
        }
    }

    private function geocode_address_mapbox($address, $poi_name = '') {
        // Clean and enhance address for better geocoding
        $cleaned_address = $this->clean_address_for_geocoding($address);

        // Check cache first
        $cache_key = 'clear_map_geocode_mapbox_' . md5($cleaned_address);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            if (isset($cached['error'])) {
                error_log('Clear Map: Using cached Mapbox geocoding error for ' . $poi_name . ': ' . $cached['error']);
            } else {
                error_log('Clear Map: Using cached Mapbox geocoding result for ' . $poi_name);
            }
            return $cached;
        }

        // Mapbox Geocoding API URL
        // https://docs.mapbox.com/api/search/geocoding/
        $endpoint = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . urlencode($cleaned_address) . '.json';

        $url = add_query_arg(array(
            'access_token' => $this->mapbox_token,
            'limit' => 1,
            'types' => 'address,poi' // Look for addresses and points of interest
        ), $endpoint);

        $response = wp_remote_get($url, array('timeout' => 10));

        if (is_wp_error($response)) {
            $error_result = array('error' => 'network_error', 'message' => $response->get_error_message());
            error_log('Clear Map: Network error geocoding with Mapbox ' . $poi_name . ': ' . $response->get_error_message());
            return $error_result;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Mapbox returns features array, not status/results like Google
        if (!empty($data['features']) && count($data['features']) > 0) {
            $feature = $data['features'][0];
            $coordinates = $feature['geometry']['coordinates']; // [lng, lat] format in Mapbox!

            $lng = $coordinates[0];
            $lat = $coordinates[1];

            $coords = array(
                'lat' => $lat,
                'lng' => $lng,
                'formatted_address' => $feature['place_name'] ?? $cleaned_address,
                'precision' => $this->get_mapbox_precision($feature),
                'source' => 'mapbox'
            );

            // Cache for 24 hours
            set_transient($cache_key, $coords, DAY_IN_SECONDS);

            error_log('Clear Map: Successfully geocoded with Mapbox: ' . $poi_name . ' to ' . $lat . ', ' . $lng);

            return $coords;
        } else {
            $error_msg = isset($data['message']) ? $data['message'] : 'No results found';
            $error_result = array('error' => 'geocoding_failed', 'message' => $error_msg);
            error_log('Clear Map: Mapbox geocoding failed for ' . $poi_name . ' (' . $cleaned_address . '): ' . $error_msg);
            return $error_result;
        }
    }

    private function geocode_address_google($address, $poi_name = '') {
        // Keep original Google geocoding for backwards compatibility
        $cleaned_address = $this->clean_address_for_geocoding($address);

        $cache_key = 'clear_map_geocode_google_' . md5($cleaned_address);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $url = add_query_arg(array(
            'address' => urlencode($cleaned_address),
            'key' => $this->google_api_key,
            'region' => 'us'
        ), 'https://maps.googleapis.com/maps/api/geocode/json');

        $response = wp_remote_get($url, array('timeout' => 10));

        if (is_wp_error($response)) {
            $error_result = array('error' => 'network_error', 'message' => $response->get_error_message());
            return $error_result;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['status'] === 'OK' && !empty($data['results'])) {
            $result = $data['results'][0];
            $location = $result['geometry']['location'];

            $coords = array(
                'lat' => $location['lat'],
                'lng' => $location['lng'],
                'formatted_address' => $result['formatted_address'],
                'precision' => $this->get_google_precision($result),
                'source' => 'google'
            );

            set_transient($cache_key, $coords, DAY_IN_SECONDS);
            return $coords;
        } else {
            $error_result = array('error' => 'geocoding_failed', 'message' => 'Status: ' . $data['status']);
            return $error_result;
        }
    }
    
    public function geocode_pois($pois) {
        $geocoded = array();
        $stats = array(
            'total_processed' => 0,
            'already_had_coordinates' => 0,
            'successfully_geocoded' => 0,
            'failed_geocoding' => 0,
            'geocoding_errors' => array()
        );
        
        foreach ($pois as $category => $category_pois) {
            $geocoded[$category] = array();
            
            foreach ($category_pois as $index => $poi) {
                $poi_data = $poi;
                $stats['total_processed']++;
                
                // Only geocode if no coordinates exist
                if (!empty($poi['lat']) && !empty($poi['lng']) && is_numeric($poi['lat']) && is_numeric($poi['lng'])) {
                    $stats['already_had_coordinates']++;
                    error_log('Clear Map: ' . $poi['name'] . ' already has coordinates, skipping geocoding');
                } else if (!empty($poi['address'])) {
                    $coords = $this->geocode_address($poi['address'], $poi['name']);

                    if ($coords && !isset($coords['error'])) {
                        $poi_data['lat'] = $coords['lat'];
                        $poi_data['lng'] = $coords['lng'];
                        $poi_data['coordinate_source'] = 'geocoded';
                        $poi_data['geocoded_address'] = $coords['formatted_address'];
                        $poi_data['geocoding_precision'] = $coords['precision'];
                        $stats['successfully_geocoded']++;
                    } else {
                        // Set to false instead of null so JavaScript can easily check
                        $poi_data['lat'] = false;
                        $poi_data['lng'] = false;
                        $poi_data['coordinate_source'] = 'failed';
                        $poi_data['needs_geocoding'] = true;
                        $stats['failed_geocoding']++;

                        if (isset($coords['error'])) {
                            $poi_data['geocoding_error'] = $coords['error'];
                            $stats['geocoding_errors'][] = $poi['name'] . ': ' . $coords['error'];
                        }
                    }
                } else {
                    // Set to false instead of null so JavaScript can easily check
                    $poi_data['lat'] = false;
                    $poi_data['lng'] = false;
                    $poi_data['coordinate_source'] = 'no_address';
                    $poi_data['needs_geocoding'] = true;
                    $stats['failed_geocoding']++;
                    $stats['geocoding_errors'][] = $poi['name'] . ': No address provided';
                }
                
                $geocoded[$category][] = $poi_data;
            }
        }
        
        // Log geocoding statistics
        error_log('Clear Map: Geocoding complete - ' . $stats['total_processed'] . ' processed, ' . 
                 $stats['already_had_coordinates'] . ' already had coordinates, ' . 
                 $stats['successfully_geocoded'] . ' successfully geocoded, ' . 
                 $stats['failed_geocoding'] . ' failed');
        
        return array(
            'pois' => $geocoded,
            'stats' => $stats
        );
    }

    public function reverse_geocode_pois($pois) {
        $geocoded = array();
        $stats = array(
            'total_processed' => 0,
            'already_had_addresses' => 0,
            'successfully_reverse_geocoded' => 0,
            'failed_reverse_geocoding' => 0,
            'reverse_geocoding_errors' => array()
        );

        foreach ($pois as $category => $category_pois) {
            $geocoded[$category] = array();

            foreach ($category_pois as $index => $poi) {
                $poi_data = $poi;
                $stats['total_processed']++;

                // Only reverse geocode if we have coordinates but no address
                if (!empty($poi['lat']) && !empty($poi['lng']) && is_numeric($poi['lat']) && is_numeric($poi['lng'])) {
                    if (!empty($poi['address'])) {
                        // Already has address
                        $stats['already_had_addresses']++;
                        error_log('Clear Map: ' . $poi['name'] . ' already has address, skipping reverse geocoding');
                    } else {
                        // Has coordinates but no address - reverse geocode
                        error_log('Clear Map: Reverse geocoding ' . $poi['name'] . ' at ' . $poi['lat'] . ',' . $poi['lng']);
                        $address = $this->reverse_geocode($poi['lat'], $poi['lng']);

                        if ($address && !empty($address)) {
                            $poi_data['address'] = $address;
                            $poi_data['reverse_geocoded'] = true;
                            $stats['successfully_reverse_geocoded']++;
                            error_log('Clear Map: Successfully reverse geocoded ' . $poi['name'] . ' to: ' . $address);
                        } else {
                            $stats['failed_reverse_geocoding']++;
                            $stats['reverse_geocoding_errors'][] = $poi['name'] . ': Reverse geocoding returned empty result';
                            error_log('Clear Map: Failed to reverse geocode ' . $poi['name']);
                        }
                    }
                }

                $geocoded[$category][] = $poi_data;
            }
        }

        // Log reverse geocoding statistics
        error_log('Clear Map: Reverse geocoding complete - ' . $stats['total_processed'] . ' processed, ' .
                 $stats['already_had_addresses'] . ' already had addresses, ' .
                 $stats['successfully_reverse_geocoded'] . ' successfully reverse geocoded, ' .
                 $stats['failed_reverse_geocoding'] . ' failed');

        return array(
            'pois' => $geocoded,
            'stats' => $stats
        );
    }

    private function reverse_geocode($lat, $lng) {
        // Use Mapbox by default, fallback to Google if Mapbox token not available
        if (!empty($this->mapbox_token)) {
            return $this->reverse_geocode_mapbox($lat, $lng);
        } else if (!empty($this->google_api_key)) {
            return $this->reverse_geocode_google($lat, $lng);
        } else {
            error_log('Clear Map: No Mapbox token or Google API key configured for reverse geocoding');
            return '';
        }
    }

    private function reverse_geocode_mapbox($lat, $lng) {
        $cache_key = 'clear_map_reverse_geocode_mapbox_' . md5($lat . ',' . $lng);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            error_log('Clear Map: Using cached Mapbox reverse geocoding result');
            return $cached;
        }

        $url = add_query_arg(array(
            'access_token' => $this->mapbox_token,
            'types' => 'address,poi'
        ), "https://api.mapbox.com/geocoding/v5/mapbox.places/{$lng},{$lat}.json");

        $response = wp_remote_get($url, array('timeout' => 10));

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!empty($data['features']) && count($data['features']) > 0) {
                $address = $data['features'][0]['place_name'];
                set_transient($cache_key, $address, DAY_IN_SECONDS);
                error_log('Clear Map: Mapbox reverse geocoding successful: ' . $address);
                return $address;
            }
        }

        return '';
    }

    private function reverse_geocode_google($lat, $lng) {
        $cache_key = 'clear_map_reverse_geocode_google_' . md5($lat . ',' . $lng);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            error_log('Clear Map: Using cached Google reverse geocoding result');
            return $cached;
        }

        $url = add_query_arg(array(
            'latlng' => $lat . ',' . $lng,
            'key' => $this->google_api_key
        ), 'https://maps.googleapis.com/maps/api/geocode/json');

        $response = wp_remote_get($url, array('timeout' => 10));
        if (is_wp_error($response)) {
            error_log('Clear Map: Google reverse geocoding API error: ' . $response->get_error_message());
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['status'] === 'OK' && !empty($data['results'])) {
            $address = $data['results'][0]['formatted_address'];
            set_transient($cache_key, $address, DAY_IN_SECONDS);
            error_log('Clear Map: Google reverse geocoding successful: ' . $address);
            return $address;
        } else {
            error_log('Clear Map: Google reverse geocoding failed: ' . $data['status']);
        }

        return '';
    }

    public function clear_geocode_cache() {
        global $wpdb;
        
        $deleted_geocode = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_clear_map_geocode_%'
            )
        );
        
        $deleted_timeout = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_clear_map_geocode_%'
            )
        );
        
        // Also clear reverse geocoding cache
        $deleted_reverse = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_clear_map_reverse_geocode_%'
            )
        );
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_clear_map_reverse_geocode_%'
            )
        );
        
        error_log('Clear Map: Cleared geocode cache - ' . ($deleted_geocode + $deleted_timeout + $deleted_reverse) . ' entries removed');
    }
    
    private function clean_address_for_geocoding($address) {
        // Remove extra whitespace
        $address = trim(preg_replace('/\s+/', ' ', $address));

        return $address;
    }
    
    private function get_mapbox_precision($feature) {
        // Mapbox uses 'accuracy' field for precision
        // Values: rooftop, parcel, point, interpolated, street, place
        $accuracy = $feature['properties']['accuracy'] ?? 'unknown';

        switch ($accuracy) {
            case 'rooftop':
            case 'parcel':
            case 'point':
                return 'high';
            case 'interpolated':
            case 'street':
                return 'medium';
            case 'place':
                return 'low';
            default:
                return 'medium'; // Mapbox is generally accurate
        }
    }

    private function get_google_precision($result) {
        if (isset($result['geometry']['location_type'])) {
            switch ($result['geometry']['location_type']) {
                case 'ROOFTOP':
                    return 'high';
                case 'RANGE_INTERPOLATED':
                    return 'medium';
                case 'GEOMETRIC_CENTER':
                    return 'low';
                case 'APPROXIMATE':
                    return 'very_low';
            }
        }
        return 'unknown';
    }
}
