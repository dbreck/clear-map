# Clear Map WordPress Plugin - Context for Next Session

## Project Overview
**Clear Map** is a WordPress plugin for interactive maps with POI (Point of Interest) filtering and category management. It uses Mapbox GL JS for map rendering and supports KML/KMZ imports with automatic geocoding via Mapbox Geocoding API (with Google as optional fallback).

**GitHub Repo**: https://github.com/dbreck/clear-map

**Plugin Location**: `/wp-content/plugins/clear-map/`

**Current Version**: 2.1.3

---

## Recent Session Summary (Version 2.1.x)

### v2.1.3 - Alphabetical POI Sorting (2026-01-22)
- POIs in filter panel's expandable category lists now sorted alphabetically (case-insensitive)
- Original index preserved for proper data-poi attribute references

### v2.1.2 - Better POI Popup Positioning (2026-01-22)
- When clicking POI from filter panel, map positions POI in lower third of viewport
- Offset 20% below center gives popup card room to display above marker

### v2.1.1 - POI Popup on Filter Click (2026-01-22)
- Clicking a POI in filter panel now opens its popup card after zooming
- Uses `map.once("moveend")` to show popup after animation completes

### v2.1.0 - Frosted Glass Effect (2026-01-22)
- New responsive WPBakery setting: "Frosted Glass Effect"
- Options: None / Panel Only / Buttons Only / Panel & Buttons
- Removed "Frosted Glass" from Pill Background dropdown (unified in new setting)
- CSS: `.filters-frosted` class for panel blur effect

---

## Previous Session Summary (Version 2.0.x)

### v2.0.2 - Fix Center On POI (2026-01-22)
- Fixed `center_on` not working (was missing from shortcode_atts whitelist)
- Sorted POI dropdown alphabetically in WPBakery element

### v2.0.1 - Center On POI Feature (2026-01-22)
- Added "Center On" dropdown in WPBakery Map Position group
- Select a POI as map center instead of manual lat/lng coordinates
- Added `margin: auto` to popup logo images

### v2.0.0 - Admin Interface Redesign (2026-01-22)
- **New admin UI** using WP_List_Table for POI management
- Tabbed interface: POIs tab / Categories tab
- Modal dialogs for editing POIs and categories via AJAX
- Bulk actions: delete, move to category
- Search and category filtering
- Pagination with per-page settings (stored in user meta)
- CSV/JSON export functionality
- Category management with card-based layout and drag-drop reordering

**New File:** `includes/class-poi-list-table.php`

**New AJAX Endpoints:**
- `clear_map_get_poi` - Get single POI data for modal
- `clear_map_save_poi` - Save single POI (AJAX)
- `clear_map_delete_poi` - Delete single POI
- `clear_map_bulk_action` - Bulk delete/category change
- `clear_map_export_pois` - CSV/JSON export
- `clear_map_save_category` - Save category
- `clear_map_delete_category` - Delete category
- `clear_map_reorder_categories` - Drag-drop reorder

---

## Plugin Architecture

### Core Files
- **`clear-map.php`** - Main plugin file, AJAX handlers, initialization
- **`includes/class-admin.php`** - WordPress admin interface (settings, POI/category management)
- **`includes/class-poi-list-table.php`** - WP_List_Table for POI list (NEW in 2.0.0)
- **`includes/class-frontend.php`** - Frontend shortcode registration
- **`includes/class-map-renderer.php`** - Renders map HTML and enqueues assets
- **`includes/class-api-handler.php`** - Handles geocoding (forward & reverse) via Mapbox/Google APIs
- **`includes/class-kml-parser.php`** - Parses KML/KMZ files and extracts POIs with coordinates
- **`includes/class-assets.php`** - Asset management
- **`includes/class-wpbakery.php`** - WPBakery Page Builder integration (ALL display settings)
- **`includes/class-github-updater.php`** - GitHub auto-update functionality

### Frontend Assets
- **`assets/js/map.js`** - Map initialization, POI rendering, clustering, filters, popups (class: `ClearMap`)
- **`assets/css/map.css`** - Map and UI styles
- **`assets/js/admin.js`** - Admin page JavaScript (AJAX handlers, modals, bulk actions)
- **`assets/css/admin.css`** - Admin page styles
- **`assets/js/wpbakery-admin.js`** - WPBakery responsive field toggles
- **`assets/css/wpbakery-admin.css`** - WPBakery admin styling

---

## WPBakery Element Settings (v2.1.x)

### General Group
- **Map Height** - `responsive_textfield` - Height of map container (default: 60vh)
- **Element ID** - `el_id` - Custom element ID
- **Extra CSS Class** - `el_class` - Custom CSS classes

### Map Position Group
- **Center On** - `dropdown` - Select POI as map center or use custom coordinates
- **Center Latitude** - `textfield` - Initial map center lat (default: 40.7451)
- **Center Longitude** - `textfield` - Initial map center lng (default: -74.0011)
- **Initial Zoom Level** - `dropdown` - Zoom level 3-18 (default: 14)

### Map Display Group
- **Cluster Distance** - `textfield` - Pixel distance for clustering (default: 50)
- **Cluster Minimum Points** - `textfield` - Min POIs for cluster (default: 3)
- **Zoom Threshold** - `textfield` - Zoom level for clusters to expand (default: 15)
- **Show Subway Lines** - `dropdown` - NYC subway overlay (default: No)

### Filter Panel Group
- **Show Filter Panel** - `responsive_dropdown` - Show/hide filter panel
- **Panel Width** - `responsive_textfield` - Width of filter panel (default: 320px)
- **Panel Height** - `responsive_textfield` - Height of filter panel (default: auto)
- **Background Color** - `colorpicker` - Panel background color (default: #FBF8F1)
- **Transparent Background** - `responsive_dropdown` - Make panel transparent
- **Frosted Glass Effect** - `responsive_dropdown` - None/Panel/Buttons/Both (NEW in 2.1.0)
- **Show Header** - `responsive_dropdown` - Show "The Area" header
- **Button Style** - `responsive_dropdown` - List or Pills style
- **Pill Border Color** - `dropdown` - Category color or custom (pills only)
- **Custom Pill Border Color** - `colorpicker` - Custom border color
- **Pill Background** - `dropdown` - Transparent or Solid Color (pills only)
- **Pill Background Color** - `colorpicker` - Custom background color
- **Show Individual Items** - `responsive_dropdown` - Expandable POI lists
- **Mobile Filter Display** - `dropdown` - Below/Above/Drawer/Hidden
- **Mobile Filter Style** - `dropdown` - Inherit/List/Pills

### Design Options Group
- **CSS Editor** - `css_editor` - Custom CSS via WPBakery

---

## Key JavaScript Methods (map.js)

### POI Filter Panel Interactions
When clicking a POI in the filter panel:
1. Sets active POI and updates UI
2. Zooms to POI with offset (lower third of viewport)
3. Shows popup after animation completes

```javascript
// POI click in filter panel
const offsetY = mapContainer.offsetHeight * 0.2
this.map.easeTo({
  center: coordinates,
  zoom: Math.max(this.data.zoom, 16),
  offset: [0, -offsetY] // Position POI in lower third
})
this.map.once("moveend", () => {
  this.showPoiPopup(poi, coordinates)
})
```

### Responsive Methods
- `getBreakpoint()` - Returns 'desktop', 'tablet', or 'mobile' based on viewport
- `getResponsiveValue(values, default)` - Gets value for current breakpoint with inheritance
- `applyResponsiveContainerHeight()` - Applies responsive map height, calls map.resize()
- `applyResponsiveStyles()` - Applies responsive filter panel styles including frosted glass
- `updateMobileMode()` - Applies mobile-specific display mode and style

---

## CSS Class Structure

### Map Container
- `.clear-map-container` - Main container (height set via JS)
- `.clear-map` - Mapbox map instance

### Filters Panel
- `.clear-map-filters` - Filter sidebar
- `.filter-style-list` - List button style
- `.filter-style-pills` - Pills button style
- `.filters-frosted` - Frosted glass panel effect (NEW in 2.1.0)
- `.bg-transparent` - Transparent background
- `.no-header` - Header hidden
- `.no-items` - Individual items hidden
- `.pills-frosted` - Frosted pill backgrounds

### Mobile Classes
- `.mobile-filters-below` - Filter panel below map (flexbox layout)
- `.mobile-filters-hidden` - Hide filter panel on mobile
- `.mobile-drawer-mode` - Container class for drawer mode
- `.mobile-drawer` - Filter panel as slide-up drawer

---

## Global Admin Settings

### Settings Location
WordPress Admin → Clear Map → Settings

### Available Settings (API & Building Only)
- **Mapbox Access Token** (required) - For map rendering and geocoding
- **Google Geocoding API Key** (optional) - Fallback for geocoding
- **Building Icon SVG** - Custom SVG icon for building marker
- **Building Icon PNG** - Alternative PNG icon
- **Building Icon Width** - Size of building marker
- **Building Address** - Address with "Geocode Now" button
- **Building Phone** - Contact phone number
- **Building Email** - Contact email address
- **Building Description** - Building description text

### Geocoding Tools
- **Run Geocoding on All POIs** button - Reverse geocode POIs missing addresses

---

## Admin Interface (v2.0.0+)

### POIs Tab
- WP_List_Table with columns: checkbox, photo, logo, name, category, address, status
- Sortable by name, category, address
- Search box filters by name and address
- Category filter dropdown
- Bulk actions: Delete, Move to Category
- Per-page selector: 10, 20, 50, 100
- Click row to edit via modal

### Categories Tab
- Card-based layout with color swatches
- POI count badge per category
- Drag-drop reordering (jQuery UI Sortable)
- AJAX save for each category
- Add/Edit/Delete via modals

### Export
- Export selected or all POIs
- Formats: CSV or JSON

---

## Git Workflow & Release SOP

When committing changes, always create a GitHub release for plugin auto-updates:

```bash
# After committing
git tag vX.X.X
git push origin main
git push origin vX.X.X

# Create release zip (from parent directory)
cd "/Users/dannybreckenridge/Documents/Clear ph/Clear pH Custom WP Plugins"
rm -f clear-map.zip
zip -r clear-map.zip clear-map \
  -x "clear-map/.git/*" -x "clear-map/.claude/*" -x "clear-map/.gitignore" \
  -x "clear-map/*.kml" -x "clear-map/check-*.php" -x "clear-map/test-*.php" \
  -x "clear-map/view-*.php" -x "clear-map/migrate-data.php" \
  -x "clear-map/MIGRATION.md" -x "clear-map/clear-map.zip" \
  -x "clear-map/Subway_Lines_NYC.geojson" -x "clear-map/CLAUDE.md" \
  -x "clear-map/screenshots/*" -x "*.DS_Store"

# Create release with zip attached
cd clear-map
gh release create vX.X.X --title "vX.X.X - Title" --notes "Release notes..." "../clear-map.zip"
```

**IMPORTANT**: Always attach `clear-map.zip` to releases. Do NOT use GitHub's auto-generated source archives (they have version numbers in the folder name).

---

## End of Context

**Current Version**: 2.1.3
**Current Status**: Production ready
**Last Updated**: 2026-01-22
