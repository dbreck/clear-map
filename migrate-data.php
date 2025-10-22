<?php
/**
 * Data Migration Script
 *
 * Run this once to migrate data from andrea_map_* options to clear_map_* options
 *
 * Usage: Add this to your wp-config.php temporarily:
 * define('CLEAR_MAP_MIGRATE', true);
 *
 * Then visit any page on your site and this will run automatically.
 * Remove the line from wp-config.php after migration is complete.
 */

// Only run if explicitly enabled
if (!defined('CLEAR_MAP_MIGRATE') || !CLEAR_MAP_MIGRATE) {
    return;
}

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Hook into WordPress init
add_action('init', 'clear_map_migrate_old_data');

function clear_map_migrate_old_data() {
    // Check if migration has already been done
    if (get_option('clear_map_migration_complete')) {
        return;
    }

    echo "<!-- Starting Clear Map data migration -->\n";

    $migrated = array();
    $not_found = array();

    // Map of old option names to new option names
    $option_map = array(
        'andrea_map_mapbox_token' => 'clear_map_mapbox_token',
        'andrea_map_google_api_key' => 'clear_map_google_api_key',
        'andrea_map_building_icon_width' => 'clear_map_building_icon_width',
        'andrea_map_building_icon_svg' => 'clear_map_building_icon_svg',
        'andrea_map_building_icon_png' => 'clear_map_building_icon_png',
        'andrea_map_building_address' => 'clear_map_building_address',
        'andrea_map_building_phone' => 'clear_map_building_phone',
        'andrea_map_building_email' => 'clear_map_building_email',
        'andrea_map_building_description' => 'clear_map_building_description',
        'andrea_map_cluster_distance' => 'clear_map_cluster_distance',
        'andrea_map_cluster_min_points' => 'clear_map_cluster_min_points',
        'andrea_map_zoom_threshold' => 'clear_map_zoom_threshold',
        'andrea_map_show_subway_lines' => 'clear_map_show_subway_lines',
        'andrea_map_categories' => 'clear_map_categories',
        'andrea_map_pois' => 'clear_map_pois',
        'andrea_map_activity' => 'clear_map_activity'
    );

    foreach ($option_map as $old_option => $new_option) {
        $value = get_option($old_option, null);

        if ($value !== null) {
            update_option($new_option, $value);
            $migrated[] = $old_option;

            // Log POI and category counts for verification
            if ($old_option === 'andrea_map_pois' && is_array($value)) {
                $total_pois = 0;
                foreach ($value as $category => $pois) {
                    $total_pois += count($pois);
                }
                echo "<!-- Migrated {$total_pois} POIs -->\n";
            }

            if ($old_option === 'andrea_map_categories' && is_array($value)) {
                echo "<!-- Migrated " . count($value) . " categories -->\n";
            }
        } else {
            $not_found[] = $old_option;
        }
    }

    // Mark migration as complete
    update_option('clear_map_migration_complete', current_time('mysql'));

    // Output results
    echo "<!-- Migration complete! -->\n";
    echo "<!-- Migrated " . count($migrated) . " options -->\n";
    echo "<!-- Not found: " . count($not_found) . " options -->\n";

    if (!empty($migrated)) {
        echo "<!-- Successfully migrated: " . implode(', ', $migrated) . " -->\n";
    }

    // Display admin notice
    add_action('admin_notices', function() use ($migrated, $not_found) {
        ?>
        <div class="notice notice-success is-dismissible">
            <h3>Clear Map Data Migration Complete!</h3>
            <p><strong>Migrated <?php echo count($migrated); ?> options from andrea_map_* to clear_map_*</strong></p>
            <?php if (!empty($migrated)): ?>
                <p>Successfully migrated: <?php echo implode(', ', $migrated); ?></p>
            <?php endif; ?>
            <?php if (!empty($not_found)): ?>
                <p>Not found (skipped): <?php echo implode(', ', $not_found); ?></p>
            <?php endif; ?>
            <p><em>Remember to remove the CLEAR_MAP_MIGRATE line from your wp-config.php file.</em></p>
        </div>
        <?php
    });
}
