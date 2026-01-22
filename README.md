# Clear Map

A WordPress plugin for creating interactive Mapbox maps with customizable Points of Interest (POI) filtering and category management. Works for any geographic location worldwide.

**Version:** 2.1.3

## Features

- **Interactive Mapbox Maps** - Beautiful, customizable maps powered by Mapbox GL JS
- **POI Management** - Add, edit, and organize points of interest by category with modern WP_List_Table UI
- **WPBakery Integration** - Full Page Builder support with responsive settings
- **KML Import** - Import locations from KML files with automatic categorization
- **Geocoding** - Automatic address-to-coordinate conversion using Mapbox Geocoding API
- **Reverse Geocoding** - Convert coordinates to addresses automatically
- **Category Filtering** - Toggle POI categories on/off with custom colors
- **Frosted Glass Effects** - Apply backdrop blur to filter panel and/or buttons
- **Responsive Settings** - Per-device settings (desktop/tablet/mobile) for all display options
- **Center On POI** - Select any POI as the map center point
- **Clustering** - Automatic marker clustering for better performance
- **Location-Agnostic** - Works for any location worldwide
- **Building Icon** - Custom SVG/PNG icon to highlight your building location
- **Export** - Export POIs as CSV or JSON
- **Auto-Updates** - GitHub-based automatic updates

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
3. Optionally add your **Google Maps API Key** for geocoding fallback

### WPBakery Element Settings

All display settings are configured per-element in WPBakery Page Builder:

**Map Position:**
- Center On - Select a POI as map center or use custom coordinates
- Center Latitude/Longitude - Manual coordinates
- Zoom Level - 3-18

**Map Display:**
- Cluster Distance, Min Points, Zoom Threshold
- Show Subway Lines (NYC)

**Filter Panel:**
- Show/Hide Filter Panel (responsive)
- Panel Width, Height (responsive)
- Background Color, Transparent Background
- Frosted Glass Effect - None/Panel/Buttons/Both (responsive)
- Button Style - List or Pills (responsive)
- Pill Border Color, Background
- Show Header, Show Individual Items (responsive)
- Mobile Filter Display - Below/Above/Drawer/Hidden
- Mobile Filter Style - Inherit/List/Pills

## Usage

### Managing POIs

1. Go to **Clear Map > Manage POIs**
2. Use the tabbed interface:
   - **POIs tab** - View, search, filter, and edit POIs
   - **Categories tab** - Manage categories with drag-drop reordering
3. Click any POI row to edit via modal
4. Use bulk actions for delete or move to category
5. Export selected or all POIs as CSV/JSON

### Importing POIs from KML

1. Go to **Clear Map > Import POIs**
2. Upload your KML file
3. The plugin will automatically:
   - Parse placemarks from the KML
   - Extract coordinates and addresses
   - Organize by folder structure (categories)
   - Geocode missing coordinates

### Displaying the Map

Add the map using WPBakery Page Builder or shortcode:

```
[clear_map]
```

Or with parameters:
```
[clear_map height="500px" zoom="15" center_on="category_key|poi_index"]
```

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
- **Photo** - Image URL
- **Logo** - Logo image URL
- **Description** - Location description
- **Website** - URL link

## Development

### File Structure

```
clear-map/
├── clear-map.php              # Main plugin file, AJAX handlers
├── includes/
│   ├── class-admin.php        # Admin interface (settings, POI management)
│   ├── class-poi-list-table.php  # WP_List_Table for POIs
│   ├── class-frontend.php     # Shortcode registration
│   ├── class-map-renderer.php # Map HTML rendering
│   ├── class-api-handler.php  # Geocoding API integrations
│   ├── class-wpbakery.php     # WPBakery Page Builder integration
│   ├── class-assets.php       # Asset management
│   ├── class-kml-parser.php   # KML file parsing
│   └── class-github-updater.php  # Auto-update functionality
├── assets/
│   ├── js/
│   │   ├── map.js            # Frontend map functionality
│   │   ├── admin.js          # Admin interface JS (modals, bulk actions)
│   │   └── wpbakery-admin.js # WPBakery responsive toggles
│   ├── css/
│   │   ├── map.css           # Frontend styles
│   │   ├── admin.css         # Admin styles
│   │   └── wpbakery-admin.css # WPBakery admin styles
│   └── data/
│       └── nyc-subway-lines.geojson  # NYC subway data
```

### AJAX Actions

**POI Management:**
- `clear_map_get_poi` - Get single POI for editing
- `clear_map_save_poi` - Save POI changes
- `clear_map_delete_poi` - Delete single POI
- `clear_map_bulk_action` - Bulk delete or move
- `clear_map_export_pois` - Export as CSV/JSON

**Category Management:**
- `clear_map_save_category` - Save category
- `clear_map_delete_category` - Delete category
- `clear_map_reorder_categories` - Reorder via drag-drop

**Import & Geocoding:**
- `clear_map_import_kml_pois` - Import KML file
- `clear_map_save_imported_pois` - Save imported POIs
- `clear_map_run_geocoding` - Run manual geocoding
- `clear_map_geocode_building` - Geocode building address

## Changelog

### Version 2.1.3 (2026-01-22)
- POIs in filter panel sorted alphabetically (case-insensitive)

### Version 2.1.2 (2026-01-22)
- POI positioned in lower third of viewport when clicked from filter panel
- Popup card fully visible above marker

### Version 2.1.1 (2026-01-22)
- Clicking POI in filter panel now opens popup card after zooming

### Version 2.1.0 (2026-01-22)
- New "Frosted Glass Effect" setting (None/Panel/Buttons/Both)
- Responsive support via device toggles
- Removed redundant "Frosted Glass" from Pill Background dropdown

### Version 2.0.2 (2026-01-22)
- Fixed Center On POI not working (missing from shortcode_atts)
- Sorted POI dropdown alphabetically in WPBakery

### Version 2.0.1 (2026-01-22)
- Added "Center On" POI dropdown in WPBakery Map Position group
- Centered popup logo images

### Version 2.0.0 (2026-01-22)
- **Major admin interface redesign**
- WP_List_Table for POI management
- Tabbed interface (POIs / Categories)
- Modal dialogs for editing via AJAX
- Bulk actions (delete, move to category)
- Search and category filtering
- Pagination with per-page settings
- CSV/JSON export functionality
- Category drag-drop reordering

### Version 1.9.x (2026-01-15)
- Consolidated all display settings to WPBakery element
- Responsive device toggles for all Filter Panel settings
- Mobile filter display modes (Below/Above/Drawer/Hidden)

### Version 1.4.0 (2026-01-12)
- Filter panel customization (colors, transparency, frosted glass)
- Button styles (List, Pills)
- Show/hide header and items toggles

### Version 1.3.0 (2026-01-12)
- WPBakery Page Builder integration
- GitHub auto-updates

### Version 1.0.0
- Initial release

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html

## Author

Danny Breckenridge

## Support

For issues, questions, or contributions, please visit the [GitHub repository](https://github.com/dbreck/clear-map).
