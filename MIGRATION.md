# Data Migration Guide

If you're upgrading from "The Andrea Map" plugin to "Clear Map", follow these steps to migrate your existing data:

## Migration Steps

1. **Backup your database** (always recommended before migrations)

2. **Add migration flag to wp-config.php**

   Open your `wp-config.php` file and add this line **before** the `/* That's all, stop editing! */` comment:

   ```php
   define('CLEAR_MAP_MIGRATE', true);
   ```

3. **Visit any page on your WordPress site**

   The migration will run automatically on the next page load. You'll see an admin notice confirming the migration.

4. **Remove the migration flag**

   After you see the success notice, **remove** the `define('CLEAR_MAP_MIGRATE', true);` line from your `wp-config.php` file.

5. **Verify your data**

   - Go to Clear Map → Dashboard to see your POIs and categories
   - Check Clear Map → Settings to verify your API keys migrated

## What Gets Migrated

The following data is automatically migrated from `andrea_map_*` to `clear_map_*` options:

- Mapbox API token
- Google Maps API key
- Building information (address, phone, email, description)
- Map display settings (icon width, cluster settings, zoom threshold)
- Categories with colors
- All POIs with their coordinates and metadata
- Activity log
- Subway lines display setting

## Troubleshooting

**If the migration doesn't run:**
- Make sure you saved the wp-config.php file
- Clear your site cache if you're using a caching plugin
- Try visiting the WordPress admin area specifically

**If data is missing after migration:**
- Check if the old `andrea_map_*` options still exist in the database
- Contact support with details about what's missing

**Starting Fresh:**
If you don't have old data to migrate, just:
1. Skip the migration steps above
2. Go to Clear Map → Settings and configure your API keys
3. Import your POIs via KML file

## Cleaning Up Old Data (Optional)

After verifying the migration was successful, you can optionally remove the old `andrea_map_*` options from the database:

```sql
DELETE FROM wp_options WHERE option_name LIKE 'andrea_map_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_andrea_map_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_andrea_map_%';
```

**Note:** Only do this after confirming your data migrated successfully!
