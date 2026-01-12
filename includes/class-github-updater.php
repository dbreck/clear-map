<?php
/**
 * GitHub Plugin Updater
 *
 * Enables automatic updates from a GitHub repository.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Clear_Map_GitHub_Updater {

    private $slug;
    private $plugin_data;
    private $username;
    private $repo;
    private $plugin_file;
    private $github_response;
    private $access_token;

    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->slug = plugin_basename($plugin_file);
        $this->username = 'dbreck';
        $this->repo = 'clear-map';
        $this->access_token = ''; // Optional: for private repos

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
        add_filter('upgrader_source_selection', array($this, 'fix_source_dir'), 10, 4);
    }

    private function get_plugin_data() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $this->plugin_data = get_plugin_data($this->plugin_file);
    }

    private function get_repo_release_info() {
        if (!empty($this->github_response)) {
            return;
        }

        $url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest";

        $args = array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            )
        );

        if (!empty($this->access_token)) {
            $args['headers']['Authorization'] = "token {$this->access_token}";
        }

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $this->github_response = json_decode(wp_remote_retrieve_body($response));

        return true;
    }

    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $this->get_plugin_data();
        $this->get_repo_release_info();

        if (empty($this->github_response)) {
            return $transient;
        }

        // Get version from GitHub (remove 'v' prefix if present)
        $github_version = ltrim($this->github_response->tag_name, 'v');
        $current_version = $this->plugin_data['Version'];

        if (version_compare($github_version, $current_version, '>')) {
            // Find the zip asset
            $download_url = $this->get_download_url();

            if ($download_url) {
                $plugin = array(
                    'slug' => dirname($this->slug),
                    'plugin' => $this->slug,
                    'new_version' => $github_version,
                    'url' => $this->plugin_data['PluginURI'],
                    'package' => $download_url,
                    'icons' => array(),
                    'banners' => array(),
                    'banners_rtl' => array(),
                    'tested' => '',
                    'requires_php' => '7.4',
                    'compatibility' => new stdClass()
                );

                $transient->response[$this->slug] = (object) $plugin;
            }
        }

        return $transient;
    }

    private function get_download_url() {
        // Look for clear-map.zip in release assets
        if (!empty($this->github_response->assets)) {
            foreach ($this->github_response->assets as $asset) {
                if ($asset->name === 'clear-map.zip') {
                    return $asset->browser_download_url;
                }
            }
        }

        // Fallback to zipball URL
        return $this->github_response->zipball_url ?? null;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== dirname($this->slug)) {
            return $result;
        }

        $this->get_plugin_data();
        $this->get_repo_release_info();

        if (empty($this->github_response)) {
            return $result;
        }

        $github_version = ltrim($this->github_response->tag_name, 'v');

        $plugin_info = array(
            'name' => $this->plugin_data['Name'],
            'slug' => dirname($this->slug),
            'version' => $github_version,
            'author' => $this->plugin_data['AuthorName'],
            'author_profile' => $this->plugin_data['AuthorURI'],
            'homepage' => $this->plugin_data['PluginURI'],
            'requires' => '5.0',
            'tested' => get_bloginfo('version'),
            'requires_php' => '7.4',
            'downloaded' => 0,
            'last_updated' => $this->github_response->published_at,
            'sections' => array(
                'description' => $this->plugin_data['Description'],
                'changelog' => $this->format_changelog($this->github_response->body),
            ),
            'download_link' => $this->get_download_url()
        );

        return (object) $plugin_info;
    }

    private function format_changelog($body) {
        if (empty($body)) {
            return '<p>No changelog available.</p>';
        }

        // Convert markdown to basic HTML
        $changelog = esc_html($body);
        $changelog = nl2br($changelog);

        return $changelog;
    }

    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        // Check if this is our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->slug) {
            return $result;
        }

        $install_directory = plugin_dir_path($this->plugin_file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;

        // Reactivate if it was active
        if (is_plugin_active($this->slug)) {
            activate_plugin($this->slug);
        }

        return $result;
    }

    public function fix_source_dir($source, $remote_source, $upgrader, $hook_extra = array()) {
        global $wp_filesystem;

        // Check if this is our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->slug) {
            return $source;
        }

        // The source might be something like "dbreck-clear-map-abc123/"
        // We need it to be "clear-map/"
        $corrected_source = trailingslashit($remote_source) . 'clear-map/';

        if ($source !== $corrected_source && $wp_filesystem->exists($source)) {
            $wp_filesystem->move($source, $corrected_source);
            return $corrected_source;
        }

        return $source;
    }
}
