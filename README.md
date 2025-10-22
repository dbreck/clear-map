# Clear Map

A WordPress plugin for creating interactive Mapbox maps with customizable Points of Interest (POI) filtering and category management.

## Features

- **Interactive Mapbox Maps** - Beautiful, customizable maps powered by Mapbox GL JS
- **POI Management** - Add, edit, and organize points of interest by category
- **KML Import** - Import locations from KML files with automatic categorization
- **Geocoding** - Automatic address-to-coordinate conversion using Google Maps API
- **Reverse Geocoding** - Convert coordinates to addresses automatically
- **Category Filtering** - Toggle POI categories on/off with custom colors
- **Clustering** - Automatic marker clustering for better performance
- **Subway Lines** - Optional NYC subway line overlay
- **Building Icon** - Custom icon to highlight your building location

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Mapbox API token
- Google Maps API key (for geocoding)

## Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/clear-map/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **Clear Map** in the admin menu to configure

## Configuration

### API Keys

1. Go to **Clear Map > Settings** in WordPress admin
2. Add your **Mapbox Token** - Get one at [mapbox.com](https://www.mapbox.com/)
3. Add your **Google Maps API Key** - Get one at [Google Cloud Console](https://console.cloud.google.com/)

### Map Settings

- **Building Icon Width** - Size of your building marker (default: 40px)
- **Cluster Distance** - How close points need to be to cluster (default: 50)
- **Cluster Min Points** - Minimum points to form a cluster (default: 3)
- **Zoom Threshold** - Zoom level where clusters break apart (default: 15)
- **Show Subway Lines** - Toggle NYC subway line overlay

## Usage

### Importing POIs from KML

1. Go to **Clear Map > Import POIs**
2. Upload your KML file
3. The plugin will automatically:
   - Parse placemarks from the KML
   - Extract coordinates and addresses
   - Organize by folder structure (categories)
   - Geocode missing coordinates
   - Reverse geocode missing addresses

### Managing Categories

1. Go to **Clear Map > Categories**
2. Add, edit, or delete categories
3. Assign custom colors to each category
4. Categories are filterable on the frontend map

### Displaying the Map

Add the map to any page or post using the shortcode:

```
[clear_map]
```

The map will display with all active POIs and category filters.

## Data Structure

### Categories

Categories are stored with:
- **Name** - Display name
- **Color** - Hex color for markers and UI

### POIs

Each POI contains:
- **Name** - Location name
- **Address** - Street address
- **Lat/Lng** - Coordinates
- **Category** - Assigned category
- **Coordinate Source** - Origin of coordinates (kml, geocoded, etc.)

## Development

### File Structure

```
clear-map/
├── clear-map.php           # Main plugin file
├── includes/
│   ├── class-admin.php     # Admin interface
│   ├── class-frontend.php  # Frontend display
│   ├── class-map-renderer.php  # Map rendering
│   ├── class-api-handler.php   # API integrations
│   ├── class-assets.php    # Asset management
│   └── class-kml-parser.php    # KML file parsing
├── assets/
│   ├── js/
│   │   ├── map.js         # Frontend map functionality
│   │   └── admin.js       # Admin interface JS
│   ├── css/
│   │   ├── map.css        # Frontend styles
│   │   └── admin.css      # Admin styles
│   └── data/
│       └── nyc-subway-lines.geojson  # NYC subway data
└── migrate-data.php        # Data migration utilities
```

### Hooks & Filters

The plugin provides several WordPress actions and filters for customization:

**AJAX Actions:**
- `wp_ajax_clear_map_geocode_cache` - Clear geocoding cache
- `wp_ajax_clear_map_clear_all_pois` - Delete all POIs
- `wp_ajax_clear_map_import_kml_pois` - Import KML file
- `wp_ajax_clear_map_save_imported_pois` - Save imported POIs
- `wp_ajax_clear_map_run_geocoding` - Run manual geocoding

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html

## Author

Danny Breckenridge

## Support

For issues, questions, or contributions, please visit the [GitHub repository](https://github.com/dbreck/clear-map).
