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

        // Add screen options for POI list.
        add_filter('set-screen-option', array($this, 'set_screen_option'), 10, 3);
    }

    /**
     * Set screen option value.
     */
    public function set_screen_option($status, $option, $value)
    {
        if ('clear_map_pois_per_page' === $option) {
            return $value;
        }
        return $status;
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

        // Categories & POIs submenu - use hook for screen options.
        $manage_hook = add_submenu_page(
            'clear-map',
            'Categories & POIs',
            'Categories & POIs',
            'manage_options',
            'clear-map-manage',
            array($this, 'manage_page')
        );

        // Add screen options for POI list.
        add_action("load-$manage_hook", array($this, 'add_manage_screen_options'));
    }

    /**
     * Add screen options for manage page.
     */
    public function add_manage_screen_options()
    {
        $option = 'per_page';
        $args   = array(
            'label'   => 'POIs per page',
            'default' => 20,
            'option'  => 'clear_map_pois_per_page',
        );
        add_screen_option($option, $args);

        // Load WP_List_Table class.
        require_once CLEAR_MAP_PLUGIN_PATH . 'includes/class-poi-list-table.php';
    }

    public function register_settings()
    {
        // API Configuration.
        register_setting('clear_map_settings', 'clear_map_mapbox_token');
        register_setting('clear_map_settings', 'clear_map_google_api_key');

        // Building Information.
        register_setting('clear_map_settings', 'clear_map_building_icon_width');
        register_setting('clear_map_settings', 'clear_map_building_icon_svg');
        register_setting('clear_map_settings', 'clear_map_building_icon_png');
        register_setting('clear_map_settings', 'clear_map_building_address');
        register_setting('clear_map_settings', 'clear_map_building_phone');
        register_setting('clear_map_settings', 'clear_map_building_email');
        register_setting('clear_map_settings', 'clear_map_building_description');

        // Categories and POIs.
        register_setting('clear_map_categories_pois', 'clear_map_categories');
        register_setting('clear_map_categories_pois', 'clear_map_pois');
    }

    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'clear-map') === false) return;

        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_media();

        // Add jQuery UI for sortable (category reordering).
        if (strpos($hook, 'clear-map-manage') !== false) {
            wp_enqueue_script('jquery-ui-sortable');
        }

        wp_enqueue_script(
            'clear-map-admin',
            CLEAR_MAP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            CLEAR_MAP_VERSION,
            true
        );

        // Get categories for the frontend.
        $categories = get_option('clear_map_categories', array());

        // Localize script with AJAX data
        wp_localize_script('clear-map-admin', 'clearMapAdmin', array(
            'ajaxurl'            => admin_url('admin-ajax.php'),
            'clearCacheNonce'    => wp_create_nonce('clear_map_geocode_cache'),
            'clearAllPoisNonce'  => wp_create_nonce('clear_map_clear_all_pois'),
            'importKmlNonce'     => wp_create_nonce('clear_map_import_kml_pois'),
            'runGeocodingNonce'  => wp_create_nonce('clear_map_run_geocoding'),
            'managePoisNonce'    => wp_create_nonce('clear_map_manage_pois'),
            'categories'         => $categories,
            'strings'            => array(
                'confirmDelete'       => __('Are you sure you want to delete this POI?', 'clear-map'),
                'confirmBulkDelete'   => __('Are you sure you want to delete the selected POIs?', 'clear-map'),
                'confirmCatDelete'    => __('Are you sure you want to delete this category? This will also delete all POIs in this category.', 'clear-map'),
                'noSelection'         => __('Please select at least one POI.', 'clear-map'),
                'saving'              => __('Saving...', 'clear-map'),
                'deleting'            => __('Deleting...', 'clear-map'),
                'exportSuccess'       => __('Export successful! Downloading file...', 'clear-map'),
            ),
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
        // Determine active tab.
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'pois';

        // Get stats.
        $categories = get_option('clear_map_categories', array());
        $pois       = get_option('clear_map_pois', array());
        $total_pois = 0;
        foreach ($pois as $cat_pois) {
            $total_pois += count($cat_pois);
        }
        ?>
        <div class="wrap clear-map-admin clear-map-manage-page">
            <h1 class="wp-heading-inline">Categories & POIs</h1>
            <a href="#" class="page-title-action" id="add-new-poi-btn">Add New POI</a>
            <hr class="wp-header-end">

            <!-- Stats summary -->
            <div class="manage-stats">
                <span class="stat-badge"><strong><?php echo esc_html($total_pois); ?></strong> POIs</span>
                <span class="stat-badge"><strong><?php echo count($categories); ?></strong> Categories</span>
            </div>

            <!-- Tabs navigation -->
            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="<?php echo esc_url(admin_url('admin.php?page=clear-map-manage&tab=pois')); ?>"
                   class="nav-tab <?php echo 'pois' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-location" style="vertical-align: text-bottom;"></span>
                    POIs
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=clear-map-manage&tab=categories')); ?>"
                   class="nav-tab <?php echo 'categories' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-category" style="vertical-align: text-bottom;"></span>
                    Categories
                </a>
            </nav>

            <div class="tab-content">
                <?php if ('pois' === $active_tab) : ?>
                    <?php $this->render_pois_tab(); ?>
                <?php else : ?>
                    <?php $this->render_categories_tab(); ?>
                <?php endif; ?>
            </div>

            <!-- POI Edit Modal -->
            <?php $this->render_poi_modal(); ?>

            <!-- Category Edit Modal -->
            <?php $this->render_category_modal(); ?>

            <!-- Export Modal -->
            <?php $this->render_export_modal(); ?>
        </div>
        <?php
    }

    /**
     * Render the POIs tab with WP_List_Table.
     */
    private function render_pois_tab()
    {
        // Create and prepare the table.
        $poi_table = new Clear_Map_POI_List_Table();
        $poi_table->prepare_items();
        ?>
        <form method="get" id="pois-filter-form">
            <input type="hidden" name="page" value="clear-map-manage" />
            <input type="hidden" name="tab" value="pois" />
            <?php
            $poi_table->search_box('Search POIs', 'poi-search');
            $poi_table->display();
            ?>
        </form>
        <?php
    }

    /**
     * Render the Categories tab.
     */
    private function render_categories_tab()
    {
        $categories = get_option('clear_map_categories', array());
        $pois       = get_option('clear_map_pois', array());
        ?>
        <div class="categories-header">
            <button type="button" class="button button-primary" id="add-new-category-btn">
                <span class="dashicons dashicons-plus-alt2" style="vertical-align: text-bottom;"></span>
                Add New Category
            </button>
            <p class="description">Drag and drop to reorder categories. Changes are saved automatically.</p>
        </div>

        <div class="categories-grid" id="categories-sortable">
            <?php foreach ($categories as $key => $category) :
                $poi_count = isset($pois[$key]) ? count($pois[$key]) : 0;
                ?>
                <div class="category-card" data-category-key="<?php echo esc_attr($key); ?>">
                    <div class="category-card-header">
                        <span class="category-drag-handle dashicons dashicons-move"></span>
                        <span class="category-color-swatch" style="background-color: <?php echo esc_attr($category['color']); ?>;"></span>
                        <h3 class="category-name"><?php echo esc_html($category['name']); ?></h3>
                        <span class="category-poi-count" title="POIs in this category"><?php echo esc_html($poi_count); ?></span>
                    </div>
                    <div class="category-card-body">
                        <div class="category-meta">
                            <span class="category-key">Key: <code><?php echo esc_html($key); ?></code></span>
                        </div>
                    </div>
                    <div class="category-card-actions">
                        <button type="button" class="button category-edit-btn" data-category-key="<?php echo esc_attr($key); ?>">
                            <span class="dashicons dashicons-edit" style="vertical-align: text-bottom;"></span> Edit
                        </button>
                        <button type="button" class="button category-delete-btn" data-category-key="<?php echo esc_attr($key); ?>">
                            <span class="dashicons dashicons-trash" style="vertical-align: text-bottom;"></span> Delete
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($categories)) : ?>
                <div class="no-categories-notice">
                    <p>No categories found. <a href="#" id="add-first-category">Add your first category</a> to get started.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render the POI edit modal.
     */
    private function render_poi_modal()
    {
        $categories = get_option('clear_map_categories', array());
        ?>
        <div id="poi-modal" class="clear-map-modal" style="display:none;">
            <div class="modal-backdrop"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="poi-modal-title">Edit POI</h2>
                    <button type="button" class="modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="poi-edit-form">
                        <input type="hidden" id="poi-id" name="poi_id" value="" />
                        <input type="hidden" id="poi-is-new" name="is_new" value="false" />

                        <!-- Basic Info Section -->
                        <div class="modal-section">
                            <h3>Basic Information</h3>
                            <div class="modal-field">
                                <label for="poi-name">Name <span class="required">*</span></label>
                                <input type="text" id="poi-name" name="name" required />
                            </div>
                            <div class="modal-field">
                                <label for="poi-category">Category <span class="required">*</span></label>
                                <select id="poi-category" name="category" required>
                                    <?php foreach ($categories as $key => $cat) : ?>
                                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="modal-field">
                                <label for="poi-address">Address</label>
                                <input type="text" id="poi-address" name="address" />
                            </div>
                            <div class="modal-field">
                                <label for="poi-description">Description</label>
                                <textarea id="poi-description" name="description" rows="3"></textarea>
                            </div>
                            <div class="modal-field">
                                <label for="poi-website">Website</label>
                                <input type="url" id="poi-website" name="website" placeholder="https://" />
                            </div>
                        </div>

                        <!-- Media Section -->
                        <div class="modal-section">
                            <h3>Media</h3>
                            <div class="modal-field-row">
                                <div class="modal-field modal-field-half">
                                    <label>Photo</label>
                                    <div class="media-upload-field">
                                        <input type="hidden" id="poi-photo" name="photo" value="" />
                                        <div class="media-preview" id="poi-photo-preview">
                                            <span class="dashicons dashicons-format-image no-media"></span>
                                        </div>
                                        <button type="button" class="button media-upload-btn" data-target="poi-photo">Select Photo</button>
                                        <button type="button" class="button media-remove-btn" data-target="poi-photo" style="display:none;">Remove</button>
                                    </div>
                                </div>
                                <div class="modal-field modal-field-half">
                                    <label>Logo</label>
                                    <div class="media-upload-field">
                                        <input type="hidden" id="poi-logo" name="logo" value="" />
                                        <div class="media-preview" id="poi-logo-preview">
                                            <span class="dashicons dashicons-store no-media"></span>
                                        </div>
                                        <button type="button" class="button media-upload-btn" data-target="poi-logo">Select Logo</button>
                                        <button type="button" class="button media-remove-btn" data-target="poi-logo" style="display:none;">Remove</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Location Section (Read-only) -->
                        <div class="modal-section modal-section-collapsed">
                            <h3 class="section-toggle">
                                Location Data
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </h3>
                            <div class="section-content" style="display:none;">
                                <div class="modal-field-row">
                                    <div class="modal-field modal-field-half">
                                        <label for="poi-lat">Latitude</label>
                                        <input type="text" id="poi-lat" name="lat" readonly class="readonly-field" />
                                    </div>
                                    <div class="modal-field modal-field-half">
                                        <label for="poi-lng">Longitude</label>
                                        <input type="text" id="poi-lng" name="lng" readonly class="readonly-field" />
                                    </div>
                                </div>
                                <div class="modal-field">
                                    <label for="poi-coordinate-source">Coordinate Source</label>
                                    <input type="text" id="poi-coordinate-source" name="coordinate_source" readonly class="readonly-field" />
                                </div>
                                <!-- Hidden fields for geocoding metadata -->
                                <input type="hidden" id="poi-needs-geocoding" name="needs_geocoding" />
                                <input type="hidden" id="poi-reverse-geocoded" name="reverse_geocoded" />
                                <input type="hidden" id="poi-geocoded-address" name="geocoded_address" />
                                <input type="hidden" id="poi-geocoding-precision" name="geocoding_precision" />
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div class="modal-footer-left">
                        <button type="button" class="button button-link-delete" id="poi-delete-btn" style="display:none;">
                            Delete POI
                        </button>
                    </div>
                    <div class="modal-footer-right">
                        <button type="button" class="button" id="poi-cancel-btn">Cancel</button>
                        <button type="button" class="button button-primary" id="poi-save-btn">Save POI</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Category edit modal.
     */
    private function render_category_modal()
    {
        ?>
        <div id="category-modal" class="clear-map-modal" style="display:none;">
            <div class="modal-backdrop"></div>
            <div class="modal-content modal-content-sm">
                <div class="modal-header">
                    <h2 id="category-modal-title">Edit Category</h2>
                    <button type="button" class="modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="category-edit-form">
                        <input type="hidden" id="category-key" name="category_key" value="" />
                        <input type="hidden" id="category-is-new" name="is_new" value="false" />

                        <div class="modal-field">
                            <label for="category-name">Name <span class="required">*</span></label>
                            <input type="text" id="category-name" name="name" required />
                        </div>
                        <div class="modal-field">
                            <label for="category-color">Color</label>
                            <input type="text" id="category-color" name="color" class="color-picker" value="#D4A574" />
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div class="modal-footer-left"></div>
                    <div class="modal-footer-right">
                        <button type="button" class="button" id="category-cancel-btn">Cancel</button>
                        <button type="button" class="button button-primary" id="category-save-btn">Save Category</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the export modal.
     */
    private function render_export_modal()
    {
        ?>
        <div id="export-modal" class="clear-map-modal" style="display:none;">
            <div class="modal-backdrop"></div>
            <div class="modal-content modal-content-sm">
                <div class="modal-header">
                    <h2>Export POIs</h2>
                    <button type="button" class="modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Choose export format:</p>
                    <div class="export-options">
                        <label class="export-option">
                            <input type="radio" name="export_format" value="csv" checked />
                            <span class="export-option-label">
                                <strong>CSV</strong>
                                <small>Spreadsheet compatible</small>
                            </span>
                        </label>
                        <label class="export-option">
                            <input type="radio" name="export_format" value="json" />
                            <span class="export-option-label">
                                <strong>JSON</strong>
                                <small>Developer friendly</small>
                            </span>
                        </label>
                    </div>
                    <p class="export-note">
                        <span class="dashicons dashicons-info"></span>
                        <span id="export-selection-count">All POIs will be exported.</span>
                    </p>
                </div>
                <div class="modal-footer">
                    <div class="modal-footer-left"></div>
                    <div class="modal-footer-right">
                        <button type="button" class="button" id="export-cancel-btn">Cancel</button>
                        <button type="button" class="button button-primary" id="export-confirm-btn">
                            <span class="dashicons dashicons-download" style="vertical-align: text-bottom;"></span>
                            Export
                        </button>
                    </div>
                </div>
            </div>
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

        // Photo thumbnail preview.
        $has_photo = ! empty( $poi['photo'] );
        echo '<div class="poi-photo-preview' . ( $has_photo ? ' has-photo' : '' ) . '" title="Photo">';
        if ( $has_photo ) {
            echo '<img src="' . esc_url( $poi['photo'] ) . '" alt="" />';
        }
        echo '</div>';

        // Logo field and preview.
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][logo]" value="' . esc_attr( $poi['logo'] ?? '' ) . '" class="poi-logo-url" />';
        $has_logo = ! empty( $poi['logo'] );
        echo '<div class="poi-logo-preview' . ( $has_logo ? ' has-photo' : '' ) . '" title="Logo">';
        if ( $has_logo ) {
            echo '<img src="' . esc_url( $poi['logo'] ) . '" alt="" />';
        }
        echo '</div>';

        // Preserve coordinate and geocoding data as hidden fields
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][lat]" value="' . esc_attr($poi['lat'] ?? '') . '" />';
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][lng]" value="' . esc_attr($poi['lng'] ?? '') . '" />';
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][coordinate_source]" value="' . esc_attr($poi['coordinate_source'] ?? '') . '" />';
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][needs_geocoding]" value="' . esc_attr($poi['needs_geocoding'] ?? '') . '" />';
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][reverse_geocoded]" value="' . esc_attr($poi['reverse_geocoded'] ?? '') . '" />';
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][geocoded_address]" value="' . esc_attr($poi['geocoded_address'] ?? '') . '" />';
        echo '<input type="hidden" name="clear_map_pois[' . esc_attr($category) . '][' . esc_attr($index) . '][geocoding_precision]" value="' . esc_attr($poi['geocoding_precision'] ?? '') . '" />';

        echo '<button type="button" class="button upload-photo">Photo</button>';
        echo '<button type="button" class="button upload-logo">Logo</button>';
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
