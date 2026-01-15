# Clear Map WordPress Plugin - Context for Next Session

## Project Overview
**Clear Map** is a WordPress plugin for interactive maps with POI (Point of Interest) filtering and category management. It uses Mapbox GL JS for map rendering and supports KML/KMZ imports with automatic geocoding via Mapbox Geocoding API (with Google as optional fallback).

**GitHub Repo**: https://github.com/dbreck/clear-map

**Plugin Location**: `/wp-content/plugins/clear-map/`

**Current Version**: 1.9.2

---

## Recent Session Summary (Version 1.9.2)

### Latest Update - Mobile Layout Fixes & Bug Fixes
**Date**: 2026-01-15
**Feature**: Fixed mobile height handling, cleaned up WPBakery options, added "Above Map" option

**What Changed:**
- **Fixed mobile height handling** - On mobile, container uses `height: auto`, map element gets explicit height
- **Removed "Use Global Setting"** from all Filter Panel dropdown options in WPBakery
- **Added "Above Map" option** to Mobile Filter Display dropdown
- **Fixed `get_setting()` bug** - Was returning empty string instead of default when option_name was empty
- **CSS flexbox ordering** - Map has `order: 1`, filters have `order: 2` (or `order: 0` for above)

**Files Modified:**
- `includes/class-map-renderer.php` - Fixed `get_setting()` to return default when no option name provided
- `includes/class-wpbakery.php` - Removed "Use Global Setting" options, added "Above Map" option
- `assets/css/map.css` - Restructured mobile layout with flexbox ordering
- `assets/js/map.js` - Updated `applyResponsiveContainerHeight()` for mobile-specific height handling

**Technical Details:**
- Mobile layout uses CSS flexbox with explicit `order` properties
- Map element: `order: 1`, Filters: `order: 2` (default), `order: 0` (above map)
- `get_setting()` now checks if `$option_name` is empty and returns `$default` directly

---

## Known Issue - Deferred

### WPBakery Dropdown Not Saving Non-Default Values
**Status**: Deferred - workaround in place
**Issue**: WPBakery's `mobile_filters` dropdown doesn't save "Above Map" selection to shortcode
**Workaround**: Default value "Below Map" works correctly; "Above Map" feature is implemented but WPBakery may not save the selection

---

## Previous Session Summary (Version 1.9.0)

### Settings Consolidation to WPBakery Only
**Date**: 2026-01-15
**Feature**: Consolidated all display settings to WPBakery element, removed from global admin

**What Changed:**
- **Map Height now responsive** - Added device toggles (desktop/tablet/mobile) to Map Height field
- **Removed 19 display settings from global admin** - All map display settings now in WPBakery only
- **Global admin simplified** - Now only contains API keys and building information
- **Added `applyResponsiveContainerHeight()` method** - Map container resizes on viewport change

---

## Previous Session Summary (Version 1.8.0)

### WPBakery Responsive Filter Panel Settings
**Date**: 2026-01-14
**Feature**: Device-specific settings (desktop/tablet/mobile) for WPBakery element

**What Changed:**
- Added responsive device toggles (desktop/tablet/mobile buttons) to Filter Panel settings in WPBakery
- All Filter Panel settings now support per-breakpoint values
- Added Mobile Filter Display and Mobile Filter Style fields to WPBakery element
- Values stored as pipe-separated format: `desktop|tablet|mobile`

**Technical Implementation:**
- Created custom WPBakery param types: `responsive_textfield`, `responsive_dropdown`
- Device toggle buttons using WordPress dashicons
- JavaScript handles switching between device inputs and updating combined value
- Map renderer parses responsive values and passes to frontend
- Frontend JS applies values based on viewport breakpoint with inheritance

**New Files:**
- `assets/css/wpbakery-admin.css` - Styling for device toggle buttons
- `assets/js/wpbakery-admin.js` - Device toggle functionality

**Responsive Fields in WPBakery:**
- Map Height (NEW in 1.9.0)
- Show Filter Panel, Panel Width, Panel Height
- Transparent Background, Frosted Glass, Show Header
- Button Style, Show Individual Items

**Breakpoints:**
- Desktop: >1024px
- Tablet: 769-1024px
- Mobile: ≤768px

---

## Previous Session Summary (Version 1.7.0)

### Mobile Filter Panel Settings
**Date**: 2026-01-14
**Feature**: Mobile-specific settings for filter panel display and layout

**What Changed:**
- Added "Mobile Settings" card in admin settings
- Mobile filter display modes: Below Map (default), Bottom Drawer, Hidden
- Mobile filter style override (inherit/list/pills)
- Fixed mobile layout to use CSS flexbox - filter panel now flows below the map

**CSS Classes:**
- `.mobile-filters-below` - Filter panel displays below map (new default)
- `.mobile-filters-hidden` - Hide filter panel on mobile
- `.mobile-drawer-mode` - Container class for drawer mode
- `.mobile-drawer` - Filter panel as slide-up drawer

---

## Plugin Architecture

### Core Files
- **`clear-map.php`** - Main plugin file, AJAX handlers, initialization
- **`includes/class-admin.php`** - WordPress admin interface (API keys & building info only)
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
- **`assets/js/admin.js`** - Admin page JavaScript (AJAX handlers, UI interactions)
- **`assets/css/admin.css`** - Admin page styles
- **`assets/js/wpbakery-admin.js`** - WPBakery responsive field toggles
- **`assets/css/wpbakery-admin.css`** - WPBakery admin styling

---

## WPBakery Element Settings (v1.9.0)

### General Group
- **Map Height** - `responsive_textfield` - Height of map container (default: 60vh)
- **Element ID** - `el_id` - Custom element ID
- **Extra CSS Class** - `el_class` - Custom CSS classes

### Map Position Group
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
- **Frosted Glass Effect** - `responsive_dropdown` - Backdrop blur effect
- **Show Header** - `responsive_dropdown` - Show "The Area" header
- **Button Style** - `responsive_dropdown` - List or Pills style
- **Pill Border Color** - `dropdown` - Category color or custom (pills only)
- **Custom Pill Border Color** - `colorpicker` - Custom border color
- **Pill Background** - `dropdown` - Transparent, color, or frosted (pills only)
- **Pill Background Color** - `colorpicker` - Custom background color
- **Show Individual Items** - `responsive_dropdown` - Expandable POI lists
- **Mobile Filter Display** - `dropdown` - Below/Drawer/Hidden
- **Mobile Filter Style** - `dropdown` - Inherit/List/Pills

### Design Options Group
- **CSS Editor** - `css_editor` - Custom CSS via WPBakery

---

## Data Flow for Responsive Settings

### 1. WPBakery Editor (Admin)
```
User clicks device toggles → wpbakery-admin.js updates hidden input
Value stored as: "desktop_value|tablet_value|mobile_value"
```

### 2. Map Renderer (PHP)
```php
// Parse responsive value
$height = $this->parse_responsive_value($atts['height'], '60vh');
// Returns: ['desktop' => '60vh', 'tablet' => '', 'mobile' => '50vh']

// Pass to JavaScript
$map_data = array(
    'mapHeight' => $height,
    'filtersWidth' => $filters_width,
    // etc...
);
```

### 3. Frontend JavaScript
```javascript
// Get breakpoint
getBreakpoint() {
    const width = window.innerWidth
    if (width <= 768) return "mobile"
    if (width <= 1024) return "tablet"
    return "desktop"
}

// Get value for current breakpoint with inheritance
getResponsiveValue(values, defaultValue) {
    const breakpoint = this.getBreakpoint()
    const desktop = values.desktop || defaultValue
    const tablet = values.tablet || desktop  // Inherit from desktop
    const mobile = values.mobile || tablet   // Inherit from tablet
    // Return appropriate value
}

// Apply to DOM
applyResponsiveContainerHeight() {
    const height = this.getResponsiveValue(this.data.mapHeight, "60vh")
    containerEl.style.height = height
    this.map.resize()
}
```

---

## Global Admin Settings (v1.9.0)

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

## Key JavaScript Methods (map.js)

### Responsive Methods
- `getBreakpoint()` - Returns 'desktop', 'tablet', or 'mobile' based on viewport
- `getResponsiveValue(values, default)` - Gets value for current breakpoint with inheritance
- `applyResponsiveContainerHeight()` - Applies responsive map height, calls map.resize()
- `applyResponsiveStyles()` - Applies responsive filter panel styles (width, height, style)
- `updateMobileMode()` - Applies mobile-specific display mode and style

### Called On Init
```javascript
init() {
    this.createMap()
    this.setupFilters()
    this.addBuildingMarker()
    this.setupMobileDrawer()  // Calls applyResponsiveContainerHeight() and applyResponsiveStyles()
}
```

### Called On Resize
```javascript
window.addEventListener("resize", () => {
    clearTimeout(resizeTimeout)
    resizeTimeout = setTimeout(() => {
        this.applyResponsiveContainerHeight()
        this.applyResponsiveStyles()
        this.updateMobileMode()
    }, 150)
})
```

---

## CSS Class Structure

### Map Container
- `.clear-map-container` - Main container (height set via JS)
- `.clear-map` - Mapbox map instance

### Filters Panel
- `.clear-map-filters` - Filter sidebar
- `.filter-style-list` - List button style
- `.filter-style-pills` - Pills button style
- `.filters-frosted` - Frosted glass effect
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

## Recent Commits

### Version 1.9.2 - Mobile Layout Fixes (2026-01-15)
- Fix mobile height handling (map element gets explicit height)
- Remove "Use Global Setting" from WPBakery dropdowns
- Add "Above Map" option to Mobile Filter Display
- Fix `get_setting()` default value bug

### Version 1.9.0 - Settings Consolidation (2026-01-15)
- Add responsive device toggles to Map Height
- Remove display settings from global admin
- Keep only API keys and building info in global admin
- All display settings now configured per-element in WPBakery

### Version 1.8.0 - WPBakery Responsive Settings (2026-01-14)
- Add responsive device toggles to Filter Panel settings
- Custom WPBakery param types for responsive fields
- Mobile Filter Display and Style fields

### Version 1.7.0 - Mobile Filter Settings (2026-01-14)
- Mobile filter display modes (below/drawer/hidden)
- Mobile filter style override
- CSS flexbox layout for mobile

---

## Git Workflow & Release SOP

When committing changes, always create a GitHub release for plugin auto-updates:

```bash
# After committing
git tag v1.x.x
git push origin main
git push origin v1.x.x

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
gh release create v1.x.x --title "v1.x.x - Title" --notes "Release notes..." "../clear-map.zip"
```

**IMPORTANT**: Always attach `clear-map.zip` to releases. Do NOT use GitHub's auto-generated source archives (they have version numbers in the folder name).

---

## End of Context

**Current Version**: 1.9.2
**Current Status**: Production ready
**Known Issue**: WPBakery "Above Map" option may not save (deferred)
