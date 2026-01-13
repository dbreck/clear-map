<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Clear_Map_Admin
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_clear_map_import_kml_pois', array($this, 'handle_kml_import'));
    }

    public function add_admin_menu()
    {
        // Main menu item
        add_menu_page(
            'Clear Map',
            'Clear Map',
            'manage_options',
            'clear-map',
            array($this, 'dashboard_page'),
            'dashicons-location-alt',
            30
        );

        // Dashboard submenu (default)
        add_submenu_page(
            'clear-map',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'clear-map',
            array($this, 'dashboard_page')
        );

        // Settings submenu
        add_submenu_page(
            'clear-map',
            'Settings',
            'Settings',
            'manage_options',
            'clear-map-settings',
            array($this, 'settings_page')
        );

        // Import POIs submenu
        add_submenu_page(
            'clear-map',
            'Import POIs',
            'Import POIs',
            'manage_options',
            'clear-map-import',
            array($this, 'import_page')
        );

        // Categories & POIs submenu
        add_submenu_page(
            'clear-map',
            'Categories & POIs',
            'Categories & POIs',
            'manage_options',
            'clear-map-manage',
            array($this, 'manage_page')
        );
    }

    public function register_settings()
    {
        register_setting('clear_map_settings', 'clear_map_mapbox_token');
        register_setting('clear_map_settings', 'clear_map_google_api_key');
        register_setting('clear_map_settings', 'clear_map_building_icon_width');
        register_setting('clear_map_settings', 'clear_map_building_icon_svg');
        register_setting('clear_map_settings', 'clear_map_building_icon_png');
        register_setting('clear_map_settings', 'clear_map_building_address');
        register_setting('clear_map_settings', 'clear_map_building_phone');
        register_setting('clear_map_settings', 'clear_map_building_email');
        register_setting('clear_map_settings', 'clear_map_building_description');
        register_setting('clear_map_settings', 'clear_map_cluster_distance');
        register_setting('clear_map_settings', 'clear_map_cluster_min_points');
        register_setting('clear_map_settings', 'clear_map_zoom_threshold');
        register_setting('clear_map_settings', 'clear_map_show_subway_lines');
        register_setting('clear_map_settings', 'clear_map_show_filters');

        // Filter panel appearance settings.
        register_setting('clear_map_settings', 'clear_map_filters_bg_color');
        register_setting('clear_map_settings', 'clear_map_filters_bg_transparent');
        register_setting('clear_map_settings', 'clear_map_filters_frosted');
        register_setting('clear_map_settings', 'clear_map_filters_show_header');
        register_setting('clear_map_settings', 'clear_map_filters_style');
        register_setting('clear_map_settings', 'clear_map_filters_pill_border');
        register_setting('clear_map_settings', 'clear_map_filters_pill_border_color');
        register_setting('clear_map_settings', 'clear_map_filters_show_items');

        register_setting('clear_map_categories_pois', 'clear_map_categories');
        register_setting('clear_map_categories_pois', 'clear_map_pois');
    }

    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'clear-map') === false) return;

        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_media();

        wp_enqueue_script(
            'clear-map-admin',
            CLEAR_MAP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            CLEAR_MAP_VERSION,
            true
        );

        // Localize script with AJAX data
        wp_localize_script('clear-map-admin', 'clearMapAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'clearCacheNonce' => wp_create_nonce('clear_map_geocode_cache'),
            'clearAllPoisNonce' => wp_create_nonce('clear_map_clear_all_pois'),
            'importKmlNonce' => wp_create_nonce('clear_map_import_kml_pois'),
            'runGeocodingNonce' => wp_create_nonce('clear_map_run_geocoding')
        ));

        wp_enqueue_style(
            'clear-map-admin',
            CLEAR_MAP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CLEAR_MAP_VERSION
        );
    }

    public function dashboard_page()
    {
        $pois = get_option('clear_map_pois', array());
        $categories = get_option('clear_map_categories', array());
        $mapbox_token = get_option('clear_map_mapbox_token');
        $google_key = get_option('clear_map_google_api_key');

        // Calculate stats
        $total_pois = 0;
        $category_counts = array();
        foreach ($pois as $category => $category_pois) {
            $count = count($category_pois);
            $total_pois += $count;
            if (isset($categories[$category])) {
                $category_counts[$categories[$category]['name']] = $count;
            }
        }

        $is_configured = !empty($mapbox_token) && !empty($google_key);
?>
        <div class="wrap clear-map-admin">
            <h1>Clear Map Dashboard</h1>

            <?php if (!$is_configured): ?>
                <div class="notice notice-warning">
                    <p><strong>Setup Required:</strong> Please configure your API keys in <a href="<?php echo admin_url('admin.php?page=clear-map-settings'); ?>">Settings</a> to get started.</p>
                </div>
            <?php endif; ?>

            <div class="clear-map-dashboard-grid">
                <div class="dashboard-card">
                    <h2>Quick Stats</h2>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $total_pois; ?></div>
                            <div class="stat-label">Total POIs</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo count($categories); ?></div>
                            <div class="stat-label">Categories</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $is_configured ? '✓' : '✗'; ?></div>
                            <div class="stat-label">API Status</div>
                        </div>
                    </div>

                    <?php if (!empty($category_counts)): ?>
                        <h4>POIs by Category</h4>
                        <ul class="category-breakdown">
                            <?php foreach ($category_counts as $cat_name => $count): ?>
                                <li><?php echo esc_html($cat_name); ?>: <strong><?php echo $count; ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="dashboard-card">
                    <h2>Shortcode Usage</h2>
                    <p>Copy and paste these shortcodes into your pages or posts:</p>

                    <div class="shortcode-example">
                        <h4>Basic Map</h4>
                        <code class="shortcode-copy">[clear_map]</code>
                        <button class="button copy-shortcode" data-shortcode="[clear_map]">Copy</button>
                    </div>

                    <div class="shortcode-example">
                        <h4>Custom Height</h4>
                        <code class="shortcode-copy">[clear_map height="70vh"]</code>
                        <button class="button copy-shortcode" data-shortcode='[clear_map height="70vh"]'>Copy</button>
                    </div>

                    <div class="shortcode-example">
                        <h4>Custom Center & Zoom</h4>
                        <code class="shortcode-copy">[clear_map center_lat="40.7451" center_lng="-74.0011" zoom="16"]</code>
                        <button class="button copy-shortcode" data-shortcode='[clear_map center_lat="40.7451" center_lng="-74.0011" zoom="16"]'>Copy</button>
                    </div>

                    <p class="description">For best results, use the map in a full-width row in your page builder.</p>
                    <p class="description"><em>Note: The old <code>[the_andrea_map]</code> shortcode still works for backwards compatibility.</em></p>
                </div>

                <div class="dashboard-card">
                    <h2>Quick Actions</h2>
                    <div class="action-buttons">
                        <a href="<?php echo admin_url('admin.php?page=clear-map-settings'); ?>" class="button button-primary">
                            Configure API Keys
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=clear-map-import'); ?>" class="button button-secondary">
                            Import POIs from KML
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=clear-map-manage'); ?>" class="button button-secondary">
                            Manage POIs
                        </a>
                        <button class="button" id="clear-geocode-cache" data-nonce="<?php echo wp_create_nonce('clear_map_geocode_cache'); ?>">
                            Clear Geocode Cache
                        </button>
                    </div>

                    <?php if ($is_configured): ?>
                        <div class="api-status">
                            <h4>API Configuration</h4>
                            <p>✓ Mapbox Token: Configured</p>
                            <p>✓ Google Geocoding: Configured</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dashboard-card">
                    <h2>Recent Activity</h2>
                    <div class="activity-log">
                        <?php $this->render_activity_log(); ?>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    public function settings_page()
    {
    ?>
        <div class="wrap clear-map-admin">
            <h1>Clear Map Settings</h1>
            <p class="settings-subtitle">Configure API keys, map display options, and clustering behavior</p>

            <form method="post" action="options.php">
                <?php settings_fields('clear_map_settings'); ?>

                <div class="settings-grid">
                    <!-- API Configuration Card -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h2>
                                <span class="dashicons dashicons-admin-network"></span>
                                API Configuration
                            </h2>
                        </div>
                        <div class="settings-card-body">
                            <div class="settings-field">
                                <label for="clear_map_mapbox_token">
                                    Mapbox Access Token
                                    <span class="required">*</span>
                                    <span class="help-tip" data-tooltip="Your Mapbox public access token. Required for map display.">?</span>
                                </label>
                                <input type="text" id="clear_map_mapbox_token" name="clear_map_mapbox_token"
                                    value="<?php echo esc_attr(get_option('clear_map_mapbox_token')); ?>"
                                    class="widefat" placeholder="pk.eyJ1..." />
                                <p class="field-description">
                                    <a href="https://account.mapbox.com/access-tokens/" target="_blank">Get your Mapbox token →</a>
                                </p>
                            </div>

                            <div class="settings-field">
                                <label for="clear_map_google_api_key">
                                    Google Geocoding API Key
                                    <span class="optional-badge">Optional</span>
                                    <span class="help-tip" data-tooltip="Fallback for geocoding if Mapbox is unavailable. Mapbox provides 100k free requests/month.">?</span>
                                </label>
                                <input type="text" id="clear_map_google_api_key" name="clear_map_google_api_key"
                                    value="<?php echo esc_attr(get_option('clear_map_google_api_key')); ?>"
                                    class="widefat" placeholder="AIza..." />
                                <p class="field-description">
                                    <a href="https://console.cloud.google.com/" target="_blank">Get Google API key →</a>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Geocoding Tools Card -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h2>
                                <span class="dashicons dashicons-location"></span>
                                Geocoding Tools
                            </h2>
                        </div>
                        <div class="settings-card-body">
                            <div class="settings-field">
                                <label>
                                    Manual Geocoding
                                    <span class="help-tip" data-tooltip="Converts coordinates to addresses for POIs that are missing address information.">?</span>
                                </label>
                                <button type="button" class="button button-secondary geocoding-btn" id="run-geocoding-btn">
                                    <span class="dashicons dashicons-location"></span>
                                    Run Geocoding on All POIs
                                </button>
                                <span class="spinner" id="geocoding-spinner"></span>
                                <p class="field-description">
                                    Adds addresses to POIs that have coordinates but no addresses using reverse geocoding via Mapbox API.
                                </p>
                                <div id="geocoding-status"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Building Information Card -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h2>
                                <span class="dashicons dashicons-building"></span>
                                Building Information
                            </h2>
                        </div>
                        <div class="settings-card-body">
                            <div class="settings-field">
                                <label for="clear_map_building_icon_svg">
                                    Building Icon (SVG)
                                    <span class="help-tip" data-tooltip="Upload an SVG icon to mark your building location on the map.">?</span>
                                </label>
                                <div class="input-with-button">
                                    <input type="text" id="clear_map_building_icon_svg" name="clear_map_building_icon_svg"
                                        value="<?php echo esc_attr(get_option('clear_map_building_icon_svg', '')); ?>"
                                        class="widefat" placeholder="URL to SVG file..." />
                                    <button type="button" class="button" id="clear_map_building_icon_svg_upload">
                                        <span class="dashicons dashicons-upload"></span> Select SVG
                                    </button>
                                </div>
                            </div>

                            <div class="settings-field">
                                <label for="clear_map_building_icon_png">
                                    Building Icon (PNG)
                                    <span class="help-tip" data-tooltip="Alternative PNG icon. For best results, use a transparent PNG with the pin tip at the bottom center.">?</span>
                                </label>
                                <div class="input-with-button">
                                    <input type="text" id="clear_map_building_icon_png" name="clear_map_building_icon_png"
                                        value="<?php echo esc_attr(get_option('clear_map_building_icon_png', '')); ?>"
                                        class="widefat" placeholder="URL to PNG file..." />
                                    <button type="button" class="button" id="clear_map_building_icon_png_upload">
                                        <span class="dashicons dashicons-upload"></span> Select PNG
                                    </button>
                                </div>
                            </div>

                            <div class="settings-field">
                                <label for="clear_map_building_icon_width">
                                    Building Icon Width
                                    <span class="help-tip" data-tooltip="Size of the building icon on the map (e.g., 40px or 10%).">?</span>
                                </label>
                                <input type="text" id="clear_map_building_icon_width" name="clear_map_building_icon_width"
                                    value="<?php echo esc_attr(get_option('clear_map_building_icon_width', '40px')); ?>"
                                    class="small-text" placeholder="40px" />
                            </div>

                            <div class="settings-field">
                                <label for="clear_map_building_address">
                                    Building Address
                                    <span class="help-tip" data-tooltip="Enter a full street address with city and state. Click 'Geocode Now' to convert to map coordinates.">?</span>
                                </label>
                                <div class="input-with-button">
                                    <input type="text" id="clear_map_building_address" name="clear_map_building_address"
                                        value="<?php echo esc_attr(get_option('clear_map_building_address', '')); ?>"
                                        class="widefat" placeholder="123 Main Street, City, State ZIP" />
                                    <button type="button" class="button" id="geocode-building-address"
                                        data-nonce="<?php echo wp_create_nonce('clear_map_geocode_building'); ?>">
                                        <span class="dashicons dashicons-location"></span> Geocode Now
                                    </button>
                                </div>
                                <p class="description">Format: Street Address, City, State ZIP (e.g., 4900 Bridge Street, Tampa, FL 33611)</p>
                            </div>

                            <div class="settings-row">
                                <div class="settings-field">
                                    <label for="clear_map_building_phone">Building Phone</label>
                                    <input type="text" id="clear_map_building_phone" name="clear_map_building_phone"
                                        value="<?php echo esc_attr(get_option('clear_map_building_phone', '')); ?>"
                                        class="widefat" placeholder="(212) 555-1234" />
                                </div>

                                <div class="settings-field">
                                    <label for="clear_map_building_email">Building Email</label>
                                    <input type="email" id="clear_map_building_email" name="clear_map_building_email"
                                        value="<?php echo esc_attr(get_option('clear_map_building_email', '')); ?>"
                                        class="widefat" placeholder="info@example.com" />
                                </div>
                            </div>

                            <div class="settings-field">
                                <label for="clear_map_building_description">Building Description</label>
                                <textarea id="clear_map_building_description" name="clear_map_building_description"
                                    rows="3" class="widefat"><?php echo esc_textarea(get_option('clear_map_building_description', '')); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Map Display Settings Card -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h2>
                                <span class="dashicons dashicons-admin-settings"></span>
                                Map Display Settings
                            </h2>
                        </div>
                        <div class="settings-card-body">
                            <div class="settings-row">
                                <div class="settings-field">
                                    <label for="clear_map_cluster_distance">
                                        Cluster Distance (px)
                                        <span class="help-tip" data-tooltip="Pixel distance for grouping nearby markers into clusters.">?</span>
                                    </label>
                                    <input type="number" id="clear_map_cluster_distance" name="clear_map_cluster_distance"
                                        value="<?php echo esc_attr(get_option('clear_map_cluster_distance', 50)); ?>"
                                        min="20" max="200" class="small-text" />
                                    <span class="field-unit">20-200</span>
                                </div>

                                <div class="settings-field">
                                    <label for="clear_map_cluster_min_points">
                                        Cluster Min Points
                                        <span class="help-tip" data-tooltip="Minimum number of markers required to form a cluster.">?</span>
                                    </label>
                                    <input type="number" id="clear_map_cluster_min_points" name="clear_map_cluster_min_points"
                                        value="<?php echo esc_attr(get_option('clear_map_cluster_min_points', 3)); ?>"
                                        min="2" max="10" class="small-text" />
                                    <span class="field-unit">2-10</span>
                                </div>
                            </div>

                            <div class="settings-field">
                                <label class="toggle-label">
                                    <input type="checkbox" name="clear_map_show_subway_lines" class="toggle-checkbox"
                                        value="1" <?php checked(get_option('clear_map_show_subway_lines', 0), 1); ?> />
                                    <span class="toggle-switch"></span>
                                    <span class="toggle-text">
                                        Show NYC Subway Lines
                                        <span class="help-tip" data-tooltip="Display MTA subway lines with official colors as an overlay on the map.">?</span>
                                    </span>
                                </label>
                            </div>

                            <div class="settings-field">
                                <label class="toggle-label">
                                    <input type="checkbox" name="clear_map_show_filters" class="toggle-checkbox"
                                        value="1" <?php checked(get_option('clear_map_show_filters', 1), 1); ?> />
                                    <span class="toggle-switch"></span>
                                    <span class="toggle-text">
                                        Show POI Filters Panel
                                        <span class="help-tip" data-tooltip="Display the category filters/legend panel on the map.">?</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Panel Appearance Card -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h2>
                                <span class="dashicons dashicons-art"></span>
                                Filter Panel Appearance
                            </h2>
                        </div>
                        <div class="settings-card-body">
                            <div class="settings-field">
                                <label for="clear_map_filters_bg_color">
                                    Background Color
                                    <span class="help-tip" data-tooltip="Set the background color of the filter panel.">?</span>
                                </label>
                                <div class="color-field-row">
                                    <input type="text" id="clear_map_filters_bg_color" name="clear_map_filters_bg_color"
                                        value="<?php echo esc_attr(get_option('clear_map_filters_bg_color', '#FBF8F1')); ?>"
                                        class="color-picker-field" data-default-color="#FBF8F1" />
                                    <label class="inline-checkbox">
                                        <input type="checkbox" name="clear_map_filters_bg_transparent"
                                            value="1" <?php checked(get_option('clear_map_filters_bg_transparent', 0), 1); ?> />
                                        Transparent
                                    </label>
                                </div>
                            </div>

                            <div class="settings-field">
                                <label class="toggle-label">
                                    <input type="checkbox" name="clear_map_filters_frosted" class="toggle-checkbox"
                                        value="1" <?php checked(get_option('clear_map_filters_frosted', 0), 1); ?> />
                                    <span class="toggle-switch"></span>
                                    <span class="toggle-text">
                                        Enable Frosted Glass Effect
                                        <span class="help-tip" data-tooltip="Adds a blur effect behind the panel. Works best with transparent or semi-transparent backgrounds.">?</span>
                                    </span>
                                </label>
                            </div>

                            <div class="settings-field">
                                <label class="toggle-label">
                                    <input type="checkbox" name="clear_map_filters_show_header" class="toggle-checkbox"
                                        value="1" <?php checked(get_option('clear_map_filters_show_header', 1), 1); ?> />
                                    <span class="toggle-switch"></span>
                                    <span class="toggle-text">
                                        Show Header ("The Area")
                                        <span class="help-tip" data-tooltip="Display the title header and collapse button at the top of the filter panel.">?</span>
                                    </span>
                                </label>
                            </div>

                            <div class="settings-field">
                                <label>
                                    Button Style
                                    <span class="help-tip" data-tooltip="Choose how category filters are displayed.">?</span>
                                </label>
                                <div class="radio-group">
                                    <label class="radio-label">
                                        <input type="radio" name="clear_map_filters_style" value="list"
                                            <?php checked(get_option('clear_map_filters_style', 'list'), 'list'); ?> />
                                        <span class="radio-text">List (colored dots with arrows)</span>
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" name="clear_map_filters_style" value="pills"
                                            <?php checked(get_option('clear_map_filters_style', 'list'), 'pills'); ?> />
                                        <span class="radio-text">Rounded Pills</span>
                                    </label>
                                </div>
                            </div>

                            <div class="settings-field conditional-field" data-show-when="clear_map_filters_style" data-show-value="pills">
                                <label>
                                    Pill Border Color
                                    <span class="help-tip" data-tooltip="Choose the border color for pill-style buttons.">?</span>
                                </label>
                                <div class="radio-group">
                                    <label class="radio-option">
                                        <input type="radio" name="clear_map_filters_pill_border" value="category"
                                            <?php checked(get_option('clear_map_filters_pill_border', 'category'), 'category'); ?> />
                                        <span class="radio-option-label">Use category color</span>
                                        <span class="radio-option-description">Each pill uses its category's assigned color</span>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="clear_map_filters_pill_border" value="custom"
                                            <?php checked(get_option('clear_map_filters_pill_border', 'category'), 'custom'); ?> />
                                        <span class="radio-option-label">Set color for all</span>
                                        <span class="radio-option-description">
                                            <input type="text" id="clear_map_filters_pill_border_color" name="clear_map_filters_pill_border_color"
                                                value="<?php echo esc_attr(get_option('clear_map_filters_pill_border_color', '#666666')); ?>"
                                                class="color-picker-field small-color-picker" data-default-color="#666666" />
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <div class="settings-field">
                                <label class="toggle-label">
                                    <input type="checkbox" name="clear_map_filters_show_items" class="toggle-checkbox"
                                        value="1" <?php checked(get_option('clear_map_filters_show_items', 1), 1); ?> />
                                    <span class="toggle-switch"></span>
                                    <span class="toggle-text">
                                        Show Individual Items
                                        <span class="help-tip" data-tooltip="When enabled, categories can be expanded to show individual POIs. When disabled, clicking a category toggles all its items on/off.">?</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-footer">
                    <?php submit_button('Save Settings', 'primary large', 'submit', false); ?>
                </div>
            </form>
        </div>
    <?php
    }

    public function import_page()
    {
    ?>
        <div class="wrap clear-map-admin">
            <h1>Import POIs from KML</h1>

            <div class="import-instructions">
                <div class="notice notice-info">
                    <h3>How to export KML from Google My Maps:</h3>
                    <ol>
                        <li>Open your <a href="https://www.google.com/maps/d/viewer?ll=40.74610134306383%2C-73.99891800000002&z=16&mid=1voaLAglx182GLweBZjPtln5xd3SRCuE" target="_blank">Google My Maps</a></li>
                        <li>Click the <strong>3-dot menu (⋮)</strong> next to the map title</li>
                        <li>Select <strong>"Export to KML/KMZ"</strong></li>
                        <li>Choose <strong>KML format</strong> and download</li>
                        <li>Upload the KML file below</li>
                    </ol>
                </div>
            </div>
            
            <div class="clear-pois-section">
                <h3>Clear All POIs</h3>
                <p>Use this to clear all existing POIs and categories before importing new data.</p>
                <div class="clear-pois-actions">
                    <button type="button" class="button button-secondary" id="clear-all-pois-btn" data-nonce="<?php echo wp_create_nonce('clear_all_pois'); ?>">
                        Clear All POIs & Categories
                    </button>
                    <p class="description">⚠️ <strong>Warning:</strong> This will permanently delete all POIs and categories. This action cannot be undone.</p>
                </div>
            </div>

            <form id="kml-import-form" enctype="multipart/form-data">
                <table class="form-table">
                    <tr>
                        <th scope="row">KML File</th>
                        <td>
                            <input type="file" id="kml-file" name="kml_file" accept=".kml,.kmz" required />
                            <p class="description">Upload a KML or KMZ file exported from Google My Maps</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Import Options</th>
                        <td>
                            <label>
                                <input type="checkbox" id="replace-existing" name="replace_existing" checked />
                                Replace existing POIs
                            </label>
                            <p class="description">Check this to replace all current POIs with the imported data. Alternatively, use the "Clear All POIs" button above to start fresh.</p>
                        </td>
                    </tr>
                </table>

                <div class="import-actions">
                    <button type="submit" class="button button-primary" id="import-btn">
                        Import POIs
                    </button>
                    <span class="spinner"></span>
                </div>
            </form>

            <div id="import-results" style="display: none;">
                <h3>Import Results</h3>
                <div id="import-summary"></div>
                <div id="import-details"></div>
            </div>

            <div id="category-assignment" style="display: none;">
                <h3>Assign Categories</h3>
                <p>Assign each imported POI to a category:</p>
                <div id="poi-category-list"></div>
                <button type="button" class="button button-primary" id="save-assignments">
                    Save Category Assignments
                </button>
            </div>
        </div>
    <?php
    }

    public function handle_kml_import()
    {
        check_ajax_referer('clear_map_import_kml_pois', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (!isset($_FILES['kml_file'])) {
            wp_send_json_error('No file uploaded');
        }

        $file = $_FILES['kml_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('File upload error');
        }

        $kml_parser = new Clear_Map_KML_Parser();
        $parsed_data = $kml_parser->parse($file['tmp_name']);

        if (is_wp_error($parsed_data)) {
            wp_send_json_error($parsed_data->get_error_message());
        }

        wp_send_json_success($parsed_data);
    }

    public function manage_page()
    {
    ?>
        <div class="wrap clear-map-admin">
            <h1>Manage Categories & POIs</h1>

            <form method="post" action="options.php">
                <?php settings_fields('clear_map_categories_pois'); ?>

                <h2>Categories</h2>
                <div id="categories-container">
                    <?php $this->render_categories(); ?>
                </div>
                <button type="button" id="add-category" class="button">Add Category</button>

                <h2>Points of Interest</h2>
                <div id="pois-container">
                    <?php $this->render_pois(); ?>
                </div>

                <?php submit_button('Save Categories & POIs'); ?>
            </form>
        </div>
<?php
    }

    private function render_categories()
    {
        $categories = get_option('clear_map_categories', array());

        foreach ($categories as $key => $category) {
            echo '<div class="category-row" data-key="' . esc_attr($key) . '">';
            echo '<input type="text" name="clear_map_categories[' . esc_attr($key) . '][name]" value="' . esc_attr($category['name']) . '" placeholder="Category Name" />';
            echo '<input type="text" name="clear_map_categories[' . esc_attr($key) . '][color]" value="' . esc_attr($category['color']) . '" class="color-picker" />';
            echo '<button type="button" class="button remove-category">Remove</button>';
            echo '</div>';
        }
    }

    private function render_pois()
    {
        $pois = get_option('clear_map_pois', array());
        $categories = get_option('clear_map_categories', array());

        foreach ($categories as $cat_key => $category) {
            echo '<h3>' . esc_html($category['name']) . '</h3>';
            echo '<div class="poi-category" data-category="' . esc_attr($cat_key) . '">';

            if (isset($pois[$cat_key])) {
                foreach ($pois[$cat_key] as $index => $poi) {
                    $this->render_poi_row($cat_key, $index, $poi);
                }
            }

            echo '<button type="button" class="button add-poi" data-category="' . esc_attr($cat_key) . '">Add POI</button>';
            echo '</div>';
        }
    }

    private function render_poi_row($category, $index, $poi)
    {
        echo '<div class="poi-row" data-category="' . esc_attr($category) . '" data-index="' . esc_attr($index) . '">';
        echo '<input type="text" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][name]" value="' . esc_attr($poi['name']) . '" placeholder="POI Name" />';
        echo '<input type="text" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][address]" value="' . esc_attr($poi['address']) . '" placeholder="Address" />';
        echo '<textarea name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][description]" placeholder="Description">' . esc_textarea($poi['description']) . '</textarea>';
        echo '<input type="url" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][website]" value="' . esc_attr($poi['website']) . '" placeholder="Website URL" />';
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][photo]" value="' . esc_attr($poi['photo']) . '" class="poi-photo-url" />';

        // Preserve coordinate and geocoding data as hidden fields
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][lat]" value="' . esc_attr($poi['lat'] ?? '') . '" />';
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][lng]" value="' . esc_attr($poi['lng'] ?? '') . '" />';
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][coordinate_source]" value="' . esc_attr($poi['coordinate_source'] ?? '') . '" />';
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][needs_geocoding]" value="' . esc_attr($poi['needs_geocoding'] ?? '') . '" />';
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][reverse_geocoded]" value="' . esc_attr($poi['reverse_geocoded'] ?? '') . '" />';
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][geocoded_address]" value="' . esc_attr($poi['geocoded_address'] ?? '') . '" />';
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][geocoding_precision]" value="' . esc_attr($poi['geocoding_precision'] ?? '') . '" />';

        echo '<button type="button" class="button upload-photo">Upload Photo</button>';
        echo '<button type="button" class="button remove-poi">Remove</button>';
        echo '</div>';
    }

    private function render_activity_log()
    {
        // Simple activity tracking
        $activities = get_option('clear_map_activity', array());

        if (empty($activities)) {
            echo '<p>No recent activity</p>';
            return;
        }

        echo '<ul class="activity-list">';
        foreach (array_slice($activities, 0, 5) as $activity) {
            echo '<li>';
            echo '<span class="activity-time">' . esc_html($activity['time']) . '</span>';
            echo '<span class="activity-action">' . esc_html($activity['action']) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
}
