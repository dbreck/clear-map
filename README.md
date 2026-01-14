# Clear Map

A WordPress plugin for creating interactive Mapbox maps with customizable Points of Interest (POI) filtering and category management. Works for any geographic location worldwide.

**Version:** 1.9.0

## Features

- **Interactive Mapbox Maps** - Beautiful, customizable maps powered by Mapbox GL JS
- **POI Management** - Add, edit, and organize points of interest by category
- **KML Import** - Import locations from KML files with automatic categorization
- **Geocoding** - Automatic address-to-coordinate conversion using Mapbox Geocoding API
- **Reverse Geocoding** - Convert coordinates to addresses automatically
- **Category Filtering** - Toggle POI categories on/off with custom colors
- **Clustering** - Automatic marker clustering for better performance
- **Location-Agnostic** - Works for any location worldwide (not limited to specific regions)
- **Building Icon** - Custom SVG/PNG icon to highlight your building location
- **Performance Optimized** - Geocoding happens only during import, not on every page load

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Mapbox API token (required) - [Get free token](https://www.mapbox.com/) (50k map loads/month, 100k geocoding requests/month)
- Google Maps API key (optional fallback for geocoding)

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
- `wp_ajax_clear_map_geocode_building` - Geocode building address

## Changelog

### Version 1.4.0 (2026-01-12)

**Filter Panel Customization Settings**

- **Background Appearance**:
  - Custom background color picker
  - Transparent background option
  - Frosted glass effect (backdrop blur) toggle

- **Header & Layout**:
  - Show/hide header ("The Area" title) toggle
  - Show individual items toggle (controls expand/collapse behavior)

- **Button Styles**:
  - List style (default) - colored dots with chevron arrows
  - Pills style - rounded transparent pill buttons with borders

- **Pill Border Options** (when pills style is selected):
  - Use category color for each pill border
  - Custom color for all pill borders

- **Technical Implementation**:
  - 8 new admin settings with modern card-based UI
  - Conditional field display based on selected options
  - CSS custom properties for dynamic pill border colors
  - JavaScript conditional expand behavior based on settings
  - Mobile-responsive pills style

### Version 1.3.0 (2026-01-12)

**WPBakery Page Builder Integration & GitHub Auto-Updates**

- **WPBakery Element**:
  - Added Clear Map as a WPBakery Page Builder element
  - Configurable parameters: height, center coordinates, zoom level
  - Design options: custom CSS class, element ID, CSS editor support
  - Located in Content category with map pin icon

- **GitHub Auto-Updates**:
  - Automatic update checks from GitHub releases
  - Seamless WordPress update integration
  - Version comparison and changelog display

- **New Files**:
  - `includes/class-wpbakery.php` - WPBakery integration
  - `includes/class-github-updater.php` - Auto-update functionality

### Version 1.2.0 (2025-01-04)

**Enhanced Category Filtering with Visual Feedback**

- **Toggle-Based Category Filtering**:
  - Click category to apply filter (shows only that category's POIs)
  - Click again to remove filter (shows all POIs)
  - Switch between categories by clicking different ones
  - Single filter at a time (exclusive filtering maintained)

- **Visual Filter Indicator**:
  - Category icon changes from colored circle to X when filtered
  - X icon uses same color as category for consistency
  - Smooth transitions between states

- **Auto Expand/Collapse POI Lists**:
  - POI list automatically expands when filter is applied
  - POI list automatically collapses when filter is removed
  - POI list collapses when switching to different category
  - Expand button remains independent for browsing without filtering

- **User Experience Improvements**:
  - Clear visual feedback for active filters
  - Intuitive toggle behavior (click to filter, click again to clear)
  - Smooth animations using GSAP
  - Maintains accessibility attributes (aria-expanded)

**Technical Implementation**:
- Updated category HTML template with dual icon support
- Added CSS filtered state with opacity transitions
- Refactored JavaScript filter logic for toggle behavior
- New `updateCategoryIcon()` helper method
- Preserved backward compatibility with expand button

### Version 1.1.1 (2025-01-XX)

**POI Photo & Description Display**

- **Clickable POI Popups**:
  - POIs now display full details when clicked (not just hovered)
  - Photos and descriptions (saved in admin) now visible on the map
  - Smart viewport-aware popup positioning (above, below, left, or right of pin)
  - Popups include: name, photo, address, description, and website link

**Technical Implementation**:
- Added `photo` field to GeoJSON properties in `poisToGeoJSON()` method
- Added click handler for `unclustered-point` layer
- Created new `showPoiPopup()` method with intelligent positioning logic

### Version 1.1.0 (2025-01-XX)

**Major Improvements - Location-Agnostic & Production Ready**

- **Removed NYC-Specific Constraints**: Plugin now works for any geographic location worldwide
  - Removed proximity bias to NYC center
  - Removed bounding box validation that rejected non-NYC coordinates
  - Removed NYC-specific address cleaning logic

- **Fixed Critical Geocoding Issues**:
  - Removed failure caching that prevented geocoding retries
  - Only successful geocoding results are now cached
  - Fixes persistent "Skipped X POIs without valid coordinates" errors

- **Fixed POI Data Loss Bug**:
  - Critical bug: Editing category names was stripping all POI coordinates
  - Added hidden form fields to preserve coordinate data during category edits
  - Users can now safely rename categories without losing POI locations

- **Removed Default Categories**:
  - No longer creates unwanted default categories on activation or import
  - Categories are now created only from KML folder structure
  - Cleaner setup experience

- **Added Building Icon Geocoding**:
  - New "Geocode Now" button in Settings for building address
  - Auto-geocode hook when building address is saved
  - Building icon now displays correctly with custom SVG/PNG

- **Performance Optimizations**:
  - Removed geocoding from map renderer (was running on every page load)
  - Geocoding now only happens during import or manual trigger
  - Eliminates hundreds of unnecessary API calls

### Version 1.0.0 (2025-01-XX)

- Initial release
- Interactive Mapbox maps with POI filtering
- KML/KMZ import with automatic categorization
- Mapbox and Google geocoding support
- Category management with custom colors
- Marker clustering
- Subway line overlay (optional)

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html

## Author

Danny Breckenridge

## Support

For issues, questions, or contributions, please visit the [GitHub repository](https://github.com/dbreck/clear-map).
