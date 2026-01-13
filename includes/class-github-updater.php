<?php
/**
 * GitHub Plugin Updater
 *
 * Enables automatic updates from a GitHub repository.
 *
 * @package Clear_Map
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clear_Map_GitHub_Updater
 *
 * Checks GitHub releases for plugin updates and integrates with WordPress update system.
 *
 * @since 1.3.0
 */
class Clear_Map_GitHub_Updater {

	/**
	 * Plugin slug (basename).
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Plugin data from header.
	 *
	 * @var array
	 */
	private $plugin_data;

	/**
	 * GitHub username.
	 *
	 * @var string
	 */
	private $username;

	/**
	 * GitHub repository name.
	 *
	 * @var string
	 */
	private $repo;

	/**
	 * Full path to main plugin file.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Cached GitHub API response.
	 *
	 * @var object
	 */
	private $github_response;

	/**
	 * GitHub access token for private repos.
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param string $plugin_file Full path to main plugin file.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file  = $plugin_file;
		$this->slug         = plugin_basename( $plugin_file );
		$this->username     = 'dbreck';
		$this->repo         = 'clear-map';
		$this->access_token = ''; // Optional: for private repos.

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
		add_filter( 'plugin_action_links_' . $this->slug, array( $this, 'add_check_update_link' ) );
		add_action( 'admin_init', array( $this, 'handle_check_update' ) );
		add_action( 'admin_notices', array( $this, 'show_check_update_notice' ) );
	}

	/**
	 * Show admin notice after manual update check.
	 *
	 * @since 1.4.1
	 *
	 * @return void
	 */
	public function show_check_update_notice() {
		if ( ! isset( $_GET['clear_map_checked'] ) ) {
			return;
		}

		$update_plugins = get_site_transient( 'update_plugins' );
		$has_update     = isset( $update_plugins->response[ $this->slug ] );

		if ( $has_update ) {
			$new_version = $update_plugins->response[ $this->slug ]->new_version;
			$message     = sprintf(
				/* translators: %s: new version number */
				__( 'Clear Map: Update available! Version %s is ready to install.', 'clear-map' ),
				$new_version
			);
			$class = 'notice notice-info';
		} else {
			$message = __( 'Clear Map: You are running the latest version.', 'clear-map' );
			$class   = 'notice notice-success';
		}

		printf( '<div class="%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Add "Check for updates" link to plugin action links.
	 *
	 * @since 1.4.1
	 *
	 * @param array $links Array of plugin action links.
	 * @return array Modified links array.
	 */
	public function add_check_update_link( $links ) {
		$check_url = wp_nonce_url(
			add_query_arg(
				array(
					'clear_map_check_update' => '1',
				),
				admin_url( 'plugins.php' )
			),
			'clear_map_check_update'
		);

		$links['check_updates'] = '<a href="' . esc_url( $check_url ) . '">' . esc_html__( 'Check for updates', 'clear-map' ) . '</a>';

		return $links;
	}

	/**
	 * Handle manual update check request.
	 *
	 * @since 1.4.1
	 *
	 * @return void
	 */
	public function handle_check_update() {
		if ( ! isset( $_GET['clear_map_check_update'] ) ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		check_admin_referer( 'clear_map_check_update' );

		// Clear the cached response so we get fresh data.
		$this->github_response = null;

		// Delete the update transient to force a fresh check.
		delete_site_transient( 'update_plugins' );

		// Trigger a fresh update check.
		wp_update_plugins();

		// Redirect back to plugins page with a message.
		wp_safe_redirect( admin_url( 'plugins.php?clear_map_checked=1' ) );
		exit;
	}

	/**
	 * Get plugin data from the plugin header.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	private function get_plugin_data() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$this->plugin_data = get_plugin_data( $this->plugin_file );
	}

	/**
	 * Fetch the latest release info from GitHub API.
	 *
	 * @since 1.3.0
	 *
	 * @return bool True on success, false on failure.
	 */
	private function get_repo_release_info() {
		if ( ! empty( $this->github_response ) ) {
			return true;
		}

		$url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest";

		$args = array(
			'headers' => array(
				'Accept'     => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			),
		);

		if ( ! empty( $this->access_token ) ) {
			$args['headers']['Authorization'] = "token {$this->access_token}";
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$this->github_response = json_decode( wp_remote_retrieve_body( $response ) );

		return true;
	}

	/**
	 * Check for plugin updates.
	 *
	 * @since 1.3.0
	 *
	 * @param object $transient Update transient object.
	 * @return object Modified transient object.
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$this->get_plugin_data();
		$this->get_repo_release_info();

		if ( empty( $this->github_response ) ) {
			return $transient;
		}

		// Get version from GitHub (remove 'v' prefix if present).
		$github_version  = ltrim( $this->github_response->tag_name, 'v' );
		$current_version = $this->plugin_data['Version'];

		if ( version_compare( $github_version, $current_version, '>' ) ) {
			// Find the zip asset.
			$download_url = $this->get_download_url();

			if ( $download_url ) {
				$plugin = array(
					'slug'         => dirname( $this->slug ),
					'plugin'       => $this->slug,
					'new_version'  => $github_version,
					'url'          => $this->plugin_data['PluginURI'],
					'package'      => $download_url,
					'icons'        => array(),
					'banners'      => array(),
					'banners_rtl'  => array(),
					'tested'       => '',
					'requires_php' => '7.4',
					'compatibility' => new stdClass(),
				);

				$transient->response[ $this->slug ] = (object) $plugin;
			}
		}

		return $transient;
	}

	/**
	 * Get the download URL for the plugin zip.
	 *
	 * @since 1.3.0
	 *
	 * @return string|null Download URL or null if not found.
	 */
	private function get_download_url() {
		// Look for clear-map.zip in release assets.
		if ( ! empty( $this->github_response->assets ) ) {
			foreach ( $this->github_response->assets as $asset ) {
				if ( 'clear-map.zip' === $asset->name ) {
					return $asset->browser_download_url;
				}
			}
		}

		// Fallback to zipball URL.
		return $this->github_response->zipball_url ?? null;
	}

	/**
	 * Provide plugin information for the WordPress plugin details popup.
	 *
	 * @since 1.3.0
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The API action being performed.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object Plugin info object or original result.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || dirname( $this->slug ) !== $args->slug ) {
			return $result;
		}

		$this->get_plugin_data();
		$this->get_repo_release_info();

		if ( empty( $this->github_response ) ) {
			return $result;
		}

		$github_version = ltrim( $this->github_response->tag_name, 'v' );

		$plugin_info = array(
			'name'           => $this->plugin_data['Name'],
			'slug'           => dirname( $this->slug ),
			'version'        => $github_version,
			'author'         => $this->plugin_data['AuthorName'],
			'author_profile' => $this->plugin_data['AuthorURI'],
			'homepage'       => $this->plugin_data['PluginURI'],
			'requires'       => '5.0',
			'tested'         => get_bloginfo( 'version' ),
			'requires_php'   => '7.4',
			'downloaded'     => 0,
			'last_updated'   => $this->github_response->published_at,
			'sections'       => array(
				'description' => $this->plugin_data['Description'],
				'changelog'   => $this->format_changelog( $this->github_response->body ),
			),
			'download_link'  => $this->get_download_url(),
		);

		return (object) $plugin_info;
	}

	/**
	 * Format the changelog from GitHub release body.
	 *
	 * @since 1.3.0
	 *
	 * @param string $body Release body text (markdown).
	 * @return string Formatted HTML changelog.
	 */
	private function format_changelog( $body ) {
		if ( empty( $body ) ) {
			return '<p>No changelog available.</p>';
		}

		// Convert markdown to basic HTML.
		$changelog = esc_html( $body );
		$changelog = nl2br( $changelog );

		return $changelog;
	}

	/**
	 * Move the plugin to the correct directory after installation.
	 *
	 * @since 1.3.0
	 *
	 * @param bool  $response   Installation response.
	 * @param array $hook_extra Extra arguments passed to hooked filters.
	 * @param array $result     Installation result data.
	 * @return array Modified result data.
	 */
	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		// Check if this is our plugin.
		if ( ! isset( $hook_extra['plugin'] ) || $this->slug !== $hook_extra['plugin'] ) {
			return $result;
		}

		$install_directory = plugin_dir_path( $this->plugin_file );
		$wp_filesystem->move( $result['destination'], $install_directory );
		$result['destination'] = $install_directory;

		// Reactivate if it was active.
		if ( is_plugin_active( $this->slug ) ) {
			activate_plugin( $this->slug );
		}

		return $result;
	}

	/**
	 * Fix the source directory name after extraction.
	 *
	 * GitHub zipball extracts to "username-repo-hash/" but we need "clear-map/".
	 *
	 * @since 1.3.0
	 *
	 * @param string       $source        File source location.
	 * @param string       $remote_source Remote file source location.
	 * @param WP_Upgrader  $upgrader      WP_Upgrader instance.
	 * @param array        $hook_extra    Extra arguments passed to hooked filters.
	 * @return string Corrected source path.
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		// Check if this is our plugin.
		if ( ! isset( $hook_extra['plugin'] ) || $this->slug !== $hook_extra['plugin'] ) {
			return $source;
		}

		// The source might be something like "dbreck-clear-map-abc123/".
		// We need it to be "clear-map/".
		$corrected_source = trailingslashit( $remote_source ) . 'clear-map/';

		if ( $source !== $corrected_source && $wp_filesystem->exists( $source ) ) {
			$wp_filesystem->move( $source, $corrected_source );
			return $corrected_source;
		}

		return $source;
	}
}
