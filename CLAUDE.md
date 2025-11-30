# Clear Map WordPress Plugin - Context for Next Session

## Project Overview
**Clear Map** is a WordPress plugin for interactive maps with POI (Point of Interest) filtering and category management. It uses Mapbox GL JS for map rendering and supports KML/KMZ imports with automatic geocoding via Mapbox Geocoding API (with Google as optional fallback).

**GitHub Repo (upcoming)**: This plugin will be pushed to GitHub for reuse across multiple client sites.

**Plugin Location**: `/wp-content/plugins/clear-map/`

---

## Recent Session Summary (Version 1.2.0)

### Latest Update - Enhanced Category Filtering with Visual Feedback
**Date**: 2025-01-04
**Feature**: Toggle-based category filtering with visual indicators and auto-expand/collapse

**What Changed:**
- Category clicks now toggle filtering on/off (click to filter, click again to clear)
- Visual feedback: Category icon changes from colored circle to X when filtered
- POI lists automatically expand when filter is applied
- POI lists automatically collapse when filter is removed
- Switching categories auto-collapses previous and expands new one
- Expand button remains independent for browsing without filtering
- Smooth animations and transitions between states

**Technical Implementation:**
- Added dual icon system (dot + X) in category HTML template
- CSS filtered state with opacity transitions for smooth icon swaps
- Refactored JavaScript category toggle logic from "show only" to "toggle on/off"
- New `filteredCategory` property to track currently filtered category
- New `updateCategoryIcon()` helper method to swap between dot and X
- Integrated expand/collapse animation with filter toggle
- Maintained single-filter behavior (exclusive filtering)

**Files Modified:**
- `includes/class-map-renderer.php` - Lines 105-108 (added X icon to template)
- `assets/css/map.css` - Lines 112-151 (icon container and filtered state styles)
- `assets/js/map.js` - Lines 10 (new property), 612-685 (refactored toggle logic), 787-802 (new helper method)

**User Experience:**
- Clear visual indicator when category is filtered (X replaces dot)
- Intuitive: click once to filter, click again to clear
- Smooth: automatic expand/collapse with animations
- Flexible: expand button still works independently

---

## Previous Session Summary (Version 1.1.1)

### POI Photo & Description Display
**Date**: Previous Session
**Feature**: Added clickable POI popups with photo and description display

**What Changed:**
- POIs now display full details when clicked (not just hovered)
- Photos and descriptions (saved in admin) now visible on the map
- Smart viewport-aware popup positioning (above, below, left, or right of pin)
- Popups include: name, photo, address, description, and website link

**Technical Implementation:**
- Added `photo` field to GeoJSON properties in `poisToGeoJSON()` method
- Added click handler for `unclustered-point` layer
- Created new `showPoiPopup()` method with intelligent positioning logic
- Popup calculates viewport space and positions itself optimally
- Uses Mapbox GL JS Popup API with custom HTML content

**Files Modified:**
- `assets/js/map.js` - Lines 254, 305-314, 456-521

---

## Previous Session Summary (Version 1.1.0)

### Major Improvements - Location-Agnostic & Production Ready
This session focused on making the plugin truly portable and fixing critical bugs that prevented it from working outside NYC.

**5 Critical Fixes Implemented:**

1. **Removed NYC-Specific Constraints** ✅
   - Removed proximity bias to NYC center
   - Removed bounding box validation (40.47-40.91 lat, -74.25 to -73.70 lng)
   - Removed coordinate validation that rejected non-NYC addresses
   - Simplified address cleaning to just trim whitespace
   - Plugin now works for ANY geographic location worldwide

2. **Fixed Geocoding Failure Caching** ✅
   - Previous: Failed geocoding was cached for 24 hours, preventing retries
   - Now: Only successful geocoding results are cached
   - Failed addresses will retry on next attempt
   - Fixes persistent "Skipped X POIs without valid coordinates" errors

3. **Removed Unwanted Default Categories** ✅
   - Previous: Plugin created 5 default categories on activation and import
   - Now: Starts with empty categories array
   - Categories are created only from KML folder structure
   - Prevents unwanted categories (Restaurants, Shopping, Fitness, Services, General)

4. **Fixed POI Data Loss on Category Edit** ✅ CRITICAL BUG
   - Previous: Editing category names stripped all POI coordinates
   - Root cause: Form didn't include hidden fields for lat/lng and geocoding metadata
   - Now: All coordinate data preserved as hidden inputs
   - Users can safely rename categories without losing POI locations

5. **Added Building Icon Geocoding** ✅
   - Previous: Building icon wouldn't display (coordinates never set)
   - Added: Manual "Geocode Now" button in Settings
   - Added: Auto-geocode hook when building address is saved
   - Building icon now displays correctly with custom SVG/PNG

### Performance Improvements
- Removed geocoding from map renderer (was running on every page load)
- Geocoding now only happens during import or manual trigger
- Building address geocodes once when saved in admin
- Eliminates hundreds of unnecessary API calls

### Current Status - Production Ready
- **Location Support**: ✅ Works for any location worldwide (tested: NYC, Sarasota FL)
- **KML Import**: ✅ Coordinates extracted and preserved
- **Geocoding**: ✅ Forward and reverse geocoding working
- **Category Management**: ✅ Edit categories without losing POI data
- **Building Icon**: ✅ Displays with manual geocoding button
- **Performance**: ✅ Optimized - no geocoding on page load
- **Default Categories**: ✅ Removed - cleaner setup

### GitHub Repository
- **Repo**: https://github.com/dbreck/clear-map
- **Version**: 1.1.1
- **Status**: POI popup feature committed (not yet pushed)

---

## Plugin Architecture

### Core Files
- **`clear-map.php`** - Main plugin file, AJAX handlers, initialization
- **`includes/class-admin.php`** - WordPress admin interface and settings pages
- **`includes/class-frontend.php`** - Frontend shortcode registration
- **`includes/class-map-renderer.php`** - Renders map HTML and enqueues assets
- **`includes/class-api-handler.php`** - Handles geocoding (forward & reverse) via Mapbox/Google APIs
- **`includes/class-kml-parser.php`** - Parses KML/KMZ files and extracts POIs with coordinates
- **`includes/class-assets.php`** - Asset management

### Frontend Assets
- **`assets/js/map.js`** - Map initialization, POI rendering, clustering, filters, popups (class: `ClearMap`)
- **`assets/css/map.css`** - Map and UI styles
- **`assets/js/admin.js`** - Admin page JavaScript (AJAX handlers, UI interactions)
- **`assets/css/admin.css`** - Admin page styles

### Key Features
- **KML/KMZ Import** - Imports POIs from Google My Maps exports
- **Auto-categorization** - Detects categories from KML folder structure
- **Forward Geocoding** - Converts addresses → coordinates (Mapbox primary, Google fallback)
- **Reverse Geocoding** - Converts coordinates → addresses (Mapbox primary, Google fallback)
- **Manual Geocoding Button** - Runs reverse geocoding on demand
- **Interactive Map** - Mapbox GL JS with clustering, category filtering, POI tooltips and popups
- **POI Details Display** - Click POIs to see photos, descriptions, and website links
- **Smart Popup Positioning** - Viewport-aware popups that adapt to available space
- **Shortcodes** - `[clear_map]` and `[the_andrea_map]` (backwards compatibility)

---

## Data Structure

### POI Structure (stored in `clear_map_pois` option)
```php
array(
    'category_key' => array(
        array(
            'name' => 'POI Name',
            'address' => '123 Street, City, State ZIP',
            'description' => 'Description text',
            'website' => 'https://example.com',
            'photo' => 'https://example.com/photo.jpg',
            'lat' => 40.7479925,
            'lng' => -74.0047649,
            'coordinate_source' => 'kml|geocoded|reverse_geocoded',
            'reverse_geocoded' => true|false,
            'needs_geocoding' => true|false
        )
    )
)
```

### Categories Structure (stored in `clear_map_categories` option)
```php
array(
    'category_key' => array(
        'name' => 'Display Name',
        'color' => '#HEX'
    )
)
```

---

## Known Issues & Next Steps

### ✅ POI Display Issue - RESOLVED
**Problem**: POI descriptions and photos were being saved but not displayed on the map
**Root Cause**: Frontend JavaScript only showed name/address in hover tooltips
**Solution**: Added click handler that displays full POI details in a Mapbox popup
**Status**: Completed in current session (commit: 34d5a92)

### 📋 Future Enhancements
Potential improvements for future versions:
- Click-to-call phone numbers in popups
- Directions link from building to POI
- Photo gallery support (multiple photos per POI)
- Social media links in POI data
- Custom popup styling per category
- POI search/filter functionality

---

## API Configuration

### Required API Keys
- **Mapbox Access Token** - Required for map rendering and geocoding
- **Google Geocoding API Key** - Optional fallback for geocoding

### API Usage
- **Mapbox Geocoding**: 100,000 free requests/month
- **Mapbox Maps**: 50,000 free loads/month
- **Google Geocoding**: User disabled billing to avoid unexpected charges

---

## Geocoding Flow

### Forward Geocoding (Address → Coordinates)
1. User has POI with address but no coordinates
2. `Clear_Map_API_Handler::geocode_pois()` called
3. Tries Mapbox first, falls back to Google
4. Coordinates saved to POI with `coordinate_source: 'geocoded'`

### Reverse Geocoding (Coordinates → Address)
1. User imports KML with coordinates but no addresses
2. `Clear_Map_API_Handler::reverse_geocode_pois()` called automatically during import
3. OR user clicks "Run Geocoding on All POIs" button manually
4. Tries Mapbox first, falls back to Google
5. Address saved to POI with `reverse_geocoded: true`

---

## Important Code Sections

### KML Import Process
**File**: `clear-map.php` lines 144-320
**Function**: `save_imported_pois()`
**Key Logic**: Detects if POIs need forward or reverse geocoding, runs appropriate method

### Manual Geocoding Button Handler
**File**: `clear-map.php` lines 423-463
**Function**: `run_manual_geocoding()`
**Does**: Reverse geocodes all POIs with coordinates but no addresses

### Map Tooltip & Popup Display
**File**: `assets/js/map.js`
**Hover Tooltip**: Lines 443-467 - `showPoiTooltip(e)` and `hidePoiTooltip()`
**Click Popup**: Lines 456-521 - `showPoiPopup(poi, coordinates)`
**Popup Logic**: Calculates viewport space to position popup optimally (above/below/left/right)
**CSS**: `assets/css/map.css` lines 198-226 (class: `.clear-map-tooltip`)

### Reverse Geocoding Implementation
**File**: `includes/class-api-handler.php` lines 235-368
**Functions**:
- `reverse_geocode_pois()` - Main method for batch reverse geocoding
- `reverse_geocode()` - Routes to Mapbox or Google
- `reverse_geocode_mapbox()` - Mapbox reverse geocoding API
- `reverse_geocode_google()` - Google reverse geocoding API

---

## Testing Data

### Test KML File
**Location**: `/wp-content/plugins/clear-map/280 8th Avenue Neighborhood.kml`
**Contains**: 95 POIs across 6 categories (Parks, Schools, Transportation, Museums & Entertainment, Grocery & Shopping, Bars/Cafes & Dining)
**Structure**: Google My Maps export with KML folders containing placemarks with coordinates

### Test Scripts (for debugging)
- `/check-pois.php` - View POI data structure
- `/check-single-poi.php` - View first POI complete data
- `/test-kml-parse.php` - Test KML parser with debug log

---

## Plugin Settings

### Settings Location
WordPress Admin → Clear Map → Settings

### Available Settings
- Mapbox Access Token (required)
- Google Geocoding API Key (optional)
- Building Icon Width
- Cluster Distance
- Cluster Min Points
- Zoom Threshold
- Show Subway Lines (toggle)
- Building Address
- Building Icon (SVG/PNG upload)

### Manual Geocoding Section
Located in Settings page with button: "Run Geocoding on All POIs"
**Purpose**: Reverse geocode POIs that have coordinates but no addresses
**Feedback**: Spinner, status messages, detailed statistics, 20-second refresh delay

---

## WordPress Integration

### Shortcodes
- `[clear_map]` - Primary shortcode
- `[the_andrea_map]` - Backwards compatibility (from previous plugin name)

### Options Stored in Database
- `clear_map_pois` - All POI data
- `clear_map_categories` - Category definitions
- `clear_map_mapbox_token` - Mapbox access token
- `clear_map_google_api_key` - Google API key
- `clear_map_building_icon_width` - Icon width setting
- `clear_map_cluster_distance` - Clustering distance
- `clear_map_cluster_min_points` - Min POIs for cluster
- `clear_map_zoom_threshold` - Zoom level threshold
- `clear_map_show_subway_lines` - Subway layer toggle
- `clear_map_activity` - Activity log (last 20 actions)

### AJAX Actions
- `clear_map_import_kml_pois` - Handle KML file upload
- `clear_map_save_imported_pois` - Save imported POIs to database
- `clear_map_run_geocoding` - Manual reverse geocoding button
- `clear_map_geocode_cache` - Clear geocoding cache
- `clear_map_clear_all_pois` - Clear all POIs and categories

---

## Mapbox GL JS Integration

### Map Initialization
**File**: `assets/js/map.js`
**Class**: `ClearMap`
**Creates**: Custom map style with Mapbox raster tiles, adds controls, sets up POI sources

### POI Clustering
Uses Mapbox GL JS built-in clustering with configurable distance and min points

### Layers
- **clusters** - Clustered POI markers
- **cluster-count** - Cluster count labels
- **unclustered-point** - Individual POI markers
- **subway-lines** - NYC subway lines (optional)
- **subway-lines-labels** - Subway line labels

---

## CSS Class Structure

### Map Container
- `.clear-map-container` - Main container
- `.clear-map` - Mapbox map instance

### Filters Panel
- `.clear-map-filters` - Filter sidebar
- `.filters-header` - Header with toggle
- `.filters-content` - Scrollable content
- `.filter-category` - Category row
- `.category-header` - Category name/color
- `.category-pois` - POI list
- `.poi-item` - Individual POI in list

### Tooltips & Popups
- `.clear-map-tooltip` - Hover tooltip container
- `.tooltip-name` - Tooltip POI name
- `.tooltip-address` - Tooltip POI address
- `.poi-popup` - Click popup container (Mapbox GL JS managed)
- `.poi-popup-name` - Popup POI name
- `.poi-popup-photo` - Popup photo container
- `.poi-popup-address` - Popup address
- `.poi-popup-description` - Popup description text
- `.poi-popup-website` - Popup website link

---

## Migration Notes

### Plugin Rename
Renamed from "The Andrea Map" to "Clear Map"
**Migration script**: `migrate-data.php` (activated by defining `CLEAR_MAP_MIGRATE` in wp-config.php)
**Changes all option names**: `andrea_map_*` → `clear_map_*`

---

## Git Repository Preparation

### Files to Include
- All plugin files in `/wp-content/plugins/clear-map/`
- README.md (needs to be created)
- LICENSE file (GPL v2 or later)

### Files to Exclude (.gitignore)
- Test KML files (user's specific data)
- Test/debug PHP scripts (`check-*.php`, `test-*.php`)
- `migrate-data.php` (legacy migration script)
- Any client-specific customizations

---

## Developer Notes

### Code Style
- WordPress Coding Standards
- Class names: `Clear_Map_ClassName`
- Function names: `snake_case`
- JavaScript: ES6 classes, jQuery for admin

### Error Logging
- All geocoding operations log to WordPress error log
- Activity log in admin dashboard tracks major actions

### Caching
- Geocoding results cached in WordPress transients (24 hours)
- Cache key format: `clear_map_geocode_mapbox_{md5(address)}` or `clear_map_reverse_geocode_mapbox_{md5(lat,lng)}`

### Performance
- POI limit: 30 per category (configurable in code)
- Activity log: Last 20 actions
- Transient cache: 24 hours for geocoding, 1 hour for import data

---

## Recent Commits

### Latest Commit
- **Commit**: `34d5a92`
- **Message**: "Add POI photo and description display with viewport-aware popups"
- **Changes**: 80 lines added to `assets/js/map.js`
- **Status**: Committed locally, not yet pushed to origin

### Uncommitted Changes
- **File**: `includes/class-map-renderer.php`
- **Change**: Updated filters header text from "Surrounding Points of Interest" to "The Area"
- **Status**: Modified but not committed

---

## End of Context

**Next Session Goal**: Push latest changes to GitHub
**Current Status**: POI photo/description display feature complete and committed
**Ready for**: Git push, testing on production site, further enhancements
