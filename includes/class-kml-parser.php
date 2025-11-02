<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Clear_Map_KML_Parser {
    
    private $debug_log = array();
    
    public function parse($file_path) {
        $this->debug_log = array();
        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        try {
            if ($file_extension === 'kmz') {
                $kml_content = $this->extract_kml_from_kmz($file_path);
            } else {
                $kml_content = file_get_contents($file_path);
            }
            
            if (empty($kml_content)) {
                return new WP_Error('empty_file', 'KML file appears to be empty');
            }
            
            $this->log('KML content length: ' . strlen($kml_content));
            
            return $this->parse_kml($kml_content);
            
        } catch (Exception $e) {
            $this->log('Parse error: ' . $e->getMessage());
            return new WP_Error('parse_error', 'Error parsing KML: ' . $e->getMessage());
        }
    }
    
    private function log($message) {
        $this->debug_log[] = $message;
        error_log('Clear Map KML Parser: ' . $message);
    }
    
    public function get_debug_log() {
        return $this->debug_log;
    }
    
    private function extract_kml_from_kmz($kmz_path) {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive class not available. Cannot process KMZ files.');
        }
        
        $zip = new ZipArchive();
        $result = $zip->open($kmz_path);
        
        if ($result !== TRUE) {
            throw new Exception('Unable to open KMZ file');
        }
        
        // Look for doc.kml or the first .kml file
        $kml_content = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'kml') {
                $kml_content = $zip->getFromIndex($i);
                break;
            }
        }
        
        $zip->close();
        
        if (empty($kml_content)) {
            throw new Exception('No KML file found in KMZ archive');
        }
        
        return $kml_content;
    }
    
    private function parse_kml($kml_content) {
        // Load XML with error handling
        libxml_use_internal_errors(true);
        
        // Clean the KML content first
        $kml_content = $this->clean_kml_content($kml_content);
        
        $xml = simplexml_load_string($kml_content);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_message = 'Invalid KML format';
            if (!empty($errors)) {
                $error_message .= ': ' . $errors[0]->message;
            }
            throw new Exception($error_message);
        }
        
        $this->log('XML loaded successfully');
        
        $pois_by_category = array();
        $detected_categories = array();
        
        // Parse folders and their placemarks
        $folders = $this->find_folders($xml);
        $this->log('Found ' . count($folders) . ' folders');
        
        foreach ($folders as $folder) {
            $category_name = (string) $folder->name;
            if (empty($category_name)) continue;
            
            $this->log('Processing folder: ' . $category_name);
            
            // Convert category name to internal key
            $category_key = $this->category_name_to_key($category_name);
            $detected_categories[$category_key] = $category_name;
            
            // Find placemarks in this folder - enhanced approach
            $folder_placemarks = $this->find_placemarks_in_folder($folder);
            $this->log('Found ' . count($folder_placemarks) . ' placemarks in folder: ' . $category_name);
            
            if (!isset($pois_by_category[$category_key])) {
                $pois_by_category[$category_key] = array();
            }
            
            foreach ($folder_placemarks as $placemark) {
                $poi = $this->parse_placemark($placemark);
                if ($poi) {
                    $pois_by_category[$category_key][] = $poi;
                    $this->log('Successfully parsed POI: ' . $poi['name']);
                } else {
                    $placemark_name = (string) $placemark->name;
                    $this->log('Failed to parse placemark: ' . $placemark_name);
                }
            }
        }
        
        // Also check for any loose placemarks not in folders
        $loose_placemarks = $this->find_loose_placemarks($xml);
        $this->log('Found ' . count($loose_placemarks) . ' loose placemarks');
        
        if (!empty($loose_placemarks)) {
            $detected_categories['general'] = 'General';
            if (!isset($pois_by_category['general'])) {
                $pois_by_category['general'] = array();
            }
            
            foreach ($loose_placemarks as $placemark) {
                $poi = $this->parse_placemark($placemark);
                if ($poi) {
                    $pois_by_category['general'][] = $poi;
                }
            }
        }
        
        // Flatten POIs for legacy compatibility
        $all_pois = array();
        foreach ($pois_by_category as $category_pois) {
            $all_pois = array_merge($all_pois, $category_pois);
        }
        
        $this->log('Total POIs parsed: ' . count($all_pois));
        
        return array(
            'pois' => $all_pois,
            'pois_by_category' => $pois_by_category,
            'categories' => array_keys($detected_categories),
            'category_names' => $detected_categories,
            'total' => count($all_pois),
            'debug_log' => $this->debug_log
        );
    }
    
    private function clean_kml_content($kml_content) {
        // Remove any BOM
        $kml_content = preg_replace('/^\x{FEFF}/u', '', $kml_content);
        
        // Remove any characters before <?xml
        $kml_content = preg_replace('/^.*?(<\?xml.*?>)/s', '$1', $kml_content);
        
        // Fix namespace issues that might exist
        $kml_content = str_replace('xmlns="http://www.opengis.net/kml/2.2"', '', $kml_content);
        
        return $kml_content;
    }
    
    private function find_folders($xml) {
        $folders = array();
        
        // Register namespaces
        $xml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');
        
        // Look for folders at various levels with multiple approaches
        $xpath_queries = array(
            '//Folder',
            '//kml:Folder',
            '//Document/Folder',
            '//Document//Folder',
            '//*[local-name()="Folder"]'
        );
        
        foreach ($xpath_queries as $query) {
            try {
                $results = $xml->xpath($query);
                if (!empty($results)) {
                    foreach ($results as $result) {
                        $folders[] = $result;
                    }
                }
            } catch (Exception $e) {
                $this->log('XPath query failed: ' . $query . ' - ' . $e->getMessage());
                continue;
            }
        }
        
        // Remove duplicates by comparing folder names
        $unique_folders = array();
        $seen_names = array();
        
        foreach ($folders as $folder) {
            $name = (string) $folder->name;
            if (!in_array($name, $seen_names) && !empty($name)) {
                $unique_folders[] = $folder;
                $seen_names[] = $name;
            }
        }
        
        return $unique_folders;
    }
    
    private function find_placemarks_in_folder($folder) {
        $folder_placemarks = array();
        
        // Method 1: Direct children
        if (isset($folder->Placemark)) {
            foreach ($folder->Placemark as $placemark) {
                $folder_placemarks[] = $placemark;
            }
        }
        
        // Method 2: XPath search within folder
        try {
            $xpath_placemarks = $folder->xpath('.//Placemark');
            if (!empty($xpath_placemarks)) {
                foreach ($xpath_placemarks as $placemark) {
                    $folder_placemarks[] = $placemark;
                }
            }
        } catch (Exception $e) {
            $this->log('XPath placemark search failed in folder');
        }
        
        // Method 3: Look for nested folders/placemarks
        if (isset($folder->Folder)) {
            foreach ($folder->Folder as $subfolder) {
                try {
                    $sub_placemarks = $subfolder->xpath('.//Placemark');
                    if (!empty($sub_placemarks)) {
                        foreach ($sub_placemarks as $placemark) {
                            $folder_placemarks[] = $placemark;
                        }
                    }
                } catch (Exception $e) {
                    $this->log('XPath subfolder placemark search failed');
                }
            }
        }
        
        // Remove duplicates by comparing placemark names
        $unique_placemarks = array();
        $seen_names = array();
        
        foreach ($folder_placemarks as $placemark) {
            $name = (string) $placemark->name;
            if (!in_array($name, $seen_names) && !empty($name)) {
                $unique_placemarks[] = $placemark;
                $seen_names[] = $name;
            }
        }
        
        return $unique_placemarks;
    }
    
    private function find_loose_placemarks($xml) {
        $all_placemarks = array();
        $folder_placemarks = array();
        
        // Get all placemarks
        $xpath_queries = array(
            '//Placemark',
            '//kml:Placemark',
            '//*[local-name()="Placemark"]'
        );
        
        foreach ($xpath_queries as $query) {
            try {
                $results = $xml->xpath($query);
                if (!empty($results)) {
                    foreach ($results as $result) {
                        $all_placemarks[] = $result;
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        // Get placemarks inside folders
        $folder_xpath_queries = array(
            '//Folder//Placemark',
            '//kml:Folder//kml:Placemark',
            '//*[local-name()="Folder"]//*[local-name()="Placemark"]'
        );
        
        foreach ($folder_xpath_queries as $query) {
            try {
                $results = $xml->xpath($query);
                if (!empty($results)) {
                    foreach ($results as $result) {
                        $folder_placemarks[] = $result;
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        // Find loose placemarks (in all but not in folders)
        $loose_placemarks = array();
        foreach ($all_placemarks as $placemark) {
            $is_in_folder = false;
            $placemark_name = (string) $placemark->name;
            
            foreach ($folder_placemarks as $folder_placemark) {
                if ((string) $folder_placemark->name === $placemark_name) {
                    $is_in_folder = true;
                    break;
                }
            }
            
            if (!$is_in_folder) {
                $loose_placemarks[] = $placemark;
            }
        }
        
        return $loose_placemarks;
    }
    
    private function category_name_to_key($category_name) {
        // Convert category name to a safe key
        $key = strtolower(trim($category_name));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim($key, '_');
        
        // Map common category names to our standard keys
        $mappings = array(
            'parks' => 'parks',
            'park' => 'parks',
            'restaurants' => 'restaurants',
            'restaurant' => 'restaurants',
            'food' => 'restaurants',
            'dining' => 'restaurants',
            'shopping' => 'shopping',
            'shops' => 'shopping',
            'stores' => 'shopping',
            'retail' => 'shopping',
            'arts_culture' => 'arts_culture',
            'arts' => 'arts_culture',
            'culture' => 'arts_culture',
            'museums' => 'arts_culture',
            'galleries' => 'arts_culture',
            'fitness' => 'fitness',
            'gym' => 'fitness',
            'sports' => 'fitness',
            'health' => 'fitness',
            'services' => 'services',
            'transportation' => 'transportation',
            'transport' => 'transportation',
            'transit' => 'transportation',
            'schools' => 'education',
            'education' => 'education',
            'school' => 'education'
        );
        
        return $mappings[$key] ?? $key;
    }
    
    private function parse_placemark($placemark) {
        $name = (string) $placemark->name;
        if (empty($name)) {
            $this->log('Skipping placemark with empty name');
            return null;
        }
        
        $this->log('Parsing placemark: ' . $name);
        
        // Get coordinates - enhanced extraction with priority
        $coordinates = $this->extract_coordinates($placemark);
        $coordinate_source = 'none';
        
        if ($coordinates) {
            $coordinate_source = 'kml';
            // Validate coordinate precision for NYC area
            if (!$this->validate_coordinate_precision($coordinates, $name)) {
                $this->log('Warning: Coordinates for ' . $name . ' may have low precision');
            }
            $this->log('KML coordinates found for ' . $name . ': ' . $coordinates['lat'] . ', ' . $coordinates['lng']);
        } else {
            $this->log('No KML coordinates found for: ' . $name . ' - will attempt geocoding if address available');
        }
        
        // Get description
        $description = (string) $placemark->description;
        $description = $this->clean_description($description);
        
        // Try to extract address from multiple sources
        $address = $this->extract_address_comprehensive($placemark, $description, $coordinates);
        $this->log('Address extracted for ' . $name . ': ' . ($address ?: 'none'));
        
        // Try to extract website from description
        $website = '';
        if (preg_match('/https?:\/\/[^\s<>]+/i', $description, $matches)) {
            $website = $matches[0];
            $description = str_replace($website, '', $description);
        }
        
        // Clean up description after extracting address and website
        $description = preg_replace('/\s+/', ' ', trim($description));
        
        // Priority: Use KML coordinates first, only geocode if no coordinates and we have address
        if (!$coordinates && $address) {
            $this->log('No KML coordinates for ' . $name . ' - will geocode address: ' . $address);
            $coordinate_source = 'geocoding_needed';
        } else if (!$coordinates && !$address) {
            $this->log('No coordinates or address found for: ' . $name . ' - skipping');
            return null;
        }
        
        return array(
            'name' => trim($name),
            'description' => $description,
            'address' => $address,
            'website' => $website,
            'lat' => $coordinates ? $coordinates['lat'] : null,
            'lng' => $coordinates ? $coordinates['lng'] : null,
            'photo' => '',
            'coordinate_source' => $coordinate_source
        );
    }
    
    private function extract_address_comprehensive($placemark, $description, $coordinates) {
        $address = '';
        
        // Method 1: Check for address in ExtendedData
        if (isset($placemark->ExtendedData)) {
            $this->log('Checking ExtendedData for address');
            
            // Look for common address field names
            $address_fields = array('address', 'Address', 'location', 'Location', 'street', 'Street', 'addr', 'place');
            
            foreach ($address_fields as $field) {
                // Check SimpleData elements
                if (isset($placemark->ExtendedData->SchemaData)) {
                    foreach ($placemark->ExtendedData->SchemaData as $schemaData) {
                        if (isset($schemaData->SimpleData)) {
                            foreach ($schemaData->SimpleData as $data) {
                                $name_attr = (string) $data['name'];
                                $value = (string) $data;
                                
                                if (strcasecmp($name_attr, $field) === 0 && !empty($value)) {
                                    $address = trim($value);
                                    $this->log('Found address in SimpleData[' . $field . ']: ' . $address);
                                    break 3;
                                }
                            }
                        }
                    }
                }
                
                // Check Data elements
                if (isset($placemark->ExtendedData->Data)) {
                    foreach ($placemark->ExtendedData->Data as $data) {
                        $name_attr = (string) $data['name'];
                        $value = isset($data->value) ? (string) $data->value : (string) $data;
                        
                        if (strcasecmp($name_attr, $field) === 0 && !empty($value)) {
                            $address = trim($value);
                            $this->log('Found address in Data[' . $field . ']: ' . $address);
                            break 2;
                        }
                    }
                }
            }
        }
        
        // Method 2: Check for address field directly in placemark
        if (empty($address) && isset($placemark->address)) {
            $address = trim((string) $placemark->address);
            $this->log('Found address in direct field: ' . $address);
        }
        
        // Method 3: Extract from description using enhanced patterns
        if (empty($address) && !empty($description)) {
            $this->log('Attempting to extract address from description');
            
            $address_patterns = array(
                // Full address patterns with numbers
                '/\b\d+\s+[A-Za-z\s\.\-]+(?:Street|St|Avenue|Ave|Road|Rd|Boulevard|Blvd|Drive|Dr|Lane|Ln|Way|Place|Pl|Circle|Cir|Court|Ct)\b[^,\n]*(?:,\s*[A-Za-z\s]+)*(?:,\s*[A-Z]{2}\s*\d{5})?/i',
                
                // NYC specific patterns
                '/\d+\s+[A-Za-z\s\.\-]+(?:Street|St|Avenue|Ave|Road|Rd|Boulevard|Blvd|Drive|Dr|Lane|Ln|Way|Place|Pl)[^,\n]*,\s*(?:New York|NYC|Manhattan|Brooklyn|Queens|Bronx|Staten Island)[^,\n]*,?\s*NY\s*\d*/i',
                
                // Simple NYC pattern
                '/[^,\n]+,\s*(?:New York|NYC|Manhattan|Brooklyn|Queens|Bronx|Staten Island)[^,\n]*,?\s*NY[^,\n]*/i',
                
                // Generic city, state pattern
                '/[A-Za-z0-9\s\.\-]+,\s*[A-Za-z\s]+,\s*[A-Z]{2}\s*\d{5}/i',
                
                // Just city and state
                '/[A-Za-z\s]+,\s*[A-Z]{2}/i'
            );
            
            foreach ($address_patterns as $pattern) {
                if (preg_match($pattern, $description, $matches)) {
                    $potential_address = trim($matches[0]);
                    // Basic validation - must have at least 5 characters and a letter
                    if (strlen($potential_address) > 5 && preg_match('/[A-Za-z]/', $potential_address)) {
                        $address = $potential_address;
                        $this->log('Extracted address from description: ' . $address);
                        break;
                    }
                }
            }
        }
        
        // Method 4: If we still don't have an address, try reverse geocoding with the coordinates
        if (empty($address) && !empty($coordinates)) {
            $this->log('Attempting reverse geocoding for coordinates');
            $address = $this->reverse_geocode_address($coordinates['lat'], $coordinates['lng']);
            if ($address) {
                $this->log('Reverse geocoded address: ' . $address);
            }
        }
        
        return $address;
    }
    
    private function reverse_geocode_address($lat, $lng) {
        // Use Mapbox for reverse geocoding (preferred), fallback to Google
        $mapbox_token = get_option('clear_map_mapbox_token');
        $google_api_key = get_option('clear_map_google_api_key');

        if (!empty($mapbox_token)) {
            return $this->reverse_geocode_mapbox($lat, $lng, $mapbox_token);
        } else if (!empty($google_api_key)) {
            return $this->reverse_geocode_google($lat, $lng, $google_api_key);
        }

        $this->log('No Mapbox token or Google API key available for reverse geocoding');
        return '';
    }

    private function reverse_geocode_mapbox($lat, $lng, $token) {
        // Check cache first
        $cache_key = 'clear_map_reverse_geocode_mapbox_' . md5($lat . ',' . $lng);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            $this->log('Using cached Mapbox reverse geocoding result');
            return $cached;
        }

        // Mapbox reverse geocoding: https://docs.mapbox.com/api/search/geocoding/#reverse-geocoding
        // Format: /geocoding/v5/{endpoint}/{longitude},{latitude}.json
        $url = add_query_arg(array(
            'access_token' => $token,
            'types' => 'address,poi'
        ), "https://api.mapbox.com/geocoding/v5/mapbox.places/{$lng},{$lat}.json");

        $response = wp_remote_get($url, array('timeout' => 10));
        if (is_wp_error($response)) {
            $this->log('Mapbox reverse geocoding API error: ' . $response->get_error_message());
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!empty($data['features']) && count($data['features']) > 0) {
            $address = $data['features'][0]['place_name'];

            // Cache for 24 hours
            set_transient($cache_key, $address, DAY_IN_SECONDS);

            $this->log('Mapbox reverse geocoding successful: ' . $address);
            return $address;
        } else {
            $this->log('Mapbox reverse geocoding returned no results');
        }

        return '';
    }

    private function reverse_geocode_google($lat, $lng, $api_key) {
        // Check cache first
        $cache_key = 'clear_map_reverse_geocode_google_' . md5($lat . ',' . $lng);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            $this->log('Using cached Google reverse geocoding result');
            return $cached;
        }

        $url = add_query_arg(array(
            'latlng' => $lat . ',' . $lng,
            'key' => $api_key
        ), 'https://maps.googleapis.com/maps/api/geocode/json');

        $response = wp_remote_get($url, array('timeout' => 10));
        if (is_wp_error($response)) {
            $this->log('Google reverse geocoding API error: ' . $response->get_error_message());
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['status'] === 'OK' && !empty($data['results'])) {
            $address = $data['results'][0]['formatted_address'];

            // Cache for 24 hours
            set_transient($cache_key, $address, DAY_IN_SECONDS);

            $this->log('Google reverse geocoding successful: ' . $address);
            return $address;
        } else {
            $this->log('Google reverse geocoding failed: ' . $data['status']);
        }

        return '';
    }
    
    private function extract_coordinates($placemark) {
        $this->log('Extracting coordinates from placemark');
        
        // Try different coordinate formats and locations
        $coord_elements = array();
        $coordinate_paths = array();
        
        // Look for Point coordinates (most common)
        if (isset($placemark->Point->coordinates)) {
            $coord_elements[] = $placemark->Point->coordinates;
            $coordinate_paths[] = 'Point->coordinates';
        }
        
        // Look for direct coordinates
        if (isset($placemark->coordinates)) {
            $coord_elements[] = $placemark->coordinates;
            $coordinate_paths[] = 'coordinates';
        }
        
        // Look for geometry coordinates
        if (isset($placemark->Geometry->Point->coordinates)) {
            $coord_elements[] = $placemark->Geometry->Point->coordinates;
            $coordinate_paths[] = 'Geometry->Point->coordinates';
        }
        
        // Look for MultiGeometry coordinates
        if (isset($placemark->MultiGeometry->Point->coordinates)) {
            $coord_elements[] = $placemark->MultiGeometry->Point->coordinates;
            $coordinate_paths[] = 'MultiGeometry->Point->coordinates';
        }
        
        // Look for LineString coordinates (take first point)
        if (isset($placemark->LineString->coordinates)) {
            $coord_elements[] = $placemark->LineString->coordinates;
            $coordinate_paths[] = 'LineString->coordinates';
        }
        
        // Look for Polygon coordinates (take first point)
        if (isset($placemark->Polygon->outerBoundaryIs->LinearRing->coordinates)) {
            $coord_elements[] = $placemark->Polygon->outerBoundaryIs->LinearRing->coordinates;
            $coordinate_paths[] = 'Polygon->outerBoundaryIs->LinearRing->coordinates';
        }
        
        // Try XPath as fallback
        try {
            $xpath_coords = $placemark->xpath('.//coordinates');
            if (!empty($xpath_coords)) {
                foreach ($xpath_coords as $coord) {
                    $coord_elements[] = $coord;
                    $coordinate_paths[] = 'xpath .//coordinates';
                }
            }
        } catch (Exception $e) {
            $this->log('XPath coordinate search failed');
        }
        
        $this->log('Found ' . count($coord_elements) . ' coordinate elements');
        
        foreach ($coord_elements as $index => $coord_element) {
            if (!empty($coord_element)) {
                $coord_string = trim((string) $coord_element);
                $this->log('Trying coordinates from ' . $coordinate_paths[$index] . ': ' . substr($coord_string, 0, 100));
                
                $result = $this->parse_coordinates($coord_string);
                if ($result) {
                    $this->log('Successfully parsed coordinates from ' . $coordinate_paths[$index]);
                    return $result;
                }
            }
        }
        
        $this->log('No valid coordinates found in any element');
        return null;
    }
    
    private function parse_coordinates($coord_string) {
        // KML coordinates are in longitude,latitude,altitude format
        // Handle multi-point coordinates by taking the first point
        
        // Clean the coordinate string
        $coord_string = trim($coord_string);
        $coord_string = preg_replace('/\s+/', ' ', $coord_string); // normalize whitespace
        
        // Split by newlines first, then by spaces/commas
        $lines = preg_split('/[\r\n]+/', $coord_string);
        $first_line = trim($lines[0]);
        
        $this->log('Parsing coordinate line: ' . $first_line);
        
        // Try different separators
        $separators = array(',', ' ', '\t');
        
        foreach ($separators as $sep) {
            if ($sep === ',') {
                $parts = explode(',', $first_line);
            } else {
                $parts = preg_split('/\s+/', $first_line);
            }
            
            // Filter out empty parts
            $parts = array_filter(array_map('trim', $parts), function($part) {
                return $part !== '';
            });
            
            if (count($parts) >= 2) {
                $lng = filter_var(trim($parts[0]), FILTER_VALIDATE_FLOAT);
                $lat = filter_var(trim($parts[1]), FILTER_VALIDATE_FLOAT);
                
                // Validate coordinates
                if ($lng !== false && $lat !== false && 
                    $lat >= -90 && $lat <= 90 && 
                    $lng >= -180 && $lng <= 180) {
                    
                    $this->log('Valid coordinates parsed: lat=' . $lat . ', lng=' . $lng);
                    return array(
                        'lat' => $lat,
                        'lng' => $lng
                    );
                } else {
                    $this->log('Invalid coordinate values: lat=' . $lat . ', lng=' . $lng);
                }
            }
        }
        
        $this->log('Failed to parse coordinates from: ' . $coord_string);
        return null;
    }
    
    private function clean_description($description) {
        // Remove HTML tags if present
        $description = strip_tags($description);
        
        // Remove CDATA wrapper if present
        $description = preg_replace('/^\s*<!\[CDATA\[(.*?)\]\]>\s*$/s', '$1', $description);
        
        // Decode HTML entities
        $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
        
        // Clean up whitespace
        $description = preg_replace('/\s+/', ' ', trim($description));
        
        return $description;
    }
    
    private function validate_coordinate_precision($coordinates, $poi_name) {
        $lat = $coordinates['lat'];
        $lng = $coordinates['lng'];
        
        // Check if coordinates are in NYC area (rough bounds)
        $nyc_bounds = array(
            'lat_min' => 40.4774,
            'lat_max' => 40.9176,
            'lng_min' => -74.2591,
            'lng_max' => -73.7004
        );
        
        $in_nyc = ($lat >= $nyc_bounds['lat_min'] && $lat <= $nyc_bounds['lat_max'] &&
                   $lng >= $nyc_bounds['lng_min'] && $lng <= $nyc_bounds['lng_max']);
        
        if (!$in_nyc) {
            $this->log('Warning: ' . $poi_name . ' coordinates appear to be outside NYC area');
        }
        
        // Check coordinate precision (should have at least 4 decimal places for accuracy)
        $lat_precision = strlen(substr(strrchr($lat, '.'), 1));
        $lng_precision = strlen(substr(strrchr($lng, '.'), 1));
        
        if ($lat_precision < 4 || $lng_precision < 4) {
            $this->log('Warning: ' . $poi_name . ' coordinates may have low precision (lat: ' . $lat_precision . ', lng: ' . $lng_precision . ' decimal places)');
            return false;
        }
        
        return true;
    }
}
