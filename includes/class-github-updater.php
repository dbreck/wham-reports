<?php
/**
 * GitHub Updater — checks GitHub releases for plugin updates.
 *
 * Prefers a release asset named "wham-reports.zip" (properly structured for WP).
 * Falls back to the GitHub zipball if no asset exists.
 *
 * @package WHAM_Reports
 */

namespace WHAM_Reports;

defined( 'ABSPATH' ) || exit;

class GitHub_Updater {

	private string $slug;
	private string $plugin_file;
	private string $repo = 'dbreck/wham-reports';
	private string $current_version;
	private string $cache_key = 'wham_github_updater';

	public function __construct() {
		$this->slug            = 'wham-reports';
		$this->plugin_file     = 'wham-reports/wham-reports.php';
		$this->current_version = WHAM_REPORTS_VERSION;

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
		add_filter( 'upgrader_post_install', [ $this, 'post_install' ], 10, 3 );

		// AJAX handler for "Check for updates" link.
		add_action( 'wp_ajax_wham_check_updates', [ $this, 'ajax_check_updates' ] );
	}

	/**
	 * Get the GitHub personal access token.
	 */
	private function get_token(): string {
		if ( defined( 'WHAM_GITHUB_TOKEN' ) && WHAM_GITHUB_TOKEN ) {
			return WHAM_GITHUB_TOKEN;
		}
		return get_option( 'wham_github_token', '' );
	}

	/**
	 * Fetch latest release data from GitHub API (cached for 6 hours).
	 */
	private function fetch_release_data( bool $force = false ): ?array {
		if ( ! $force ) {
			$cached = get_transient( $this->cache_key );
			if ( is_array( $cached ) ) {
				return ! empty( $cached['error'] ) ? null : $cached;
			}
		}

		$url     = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';
		$headers = [
			'Accept'     => 'application/vnd.github.v3+json',
			'User-Agent' => 'WHAM-Reports-Updater',
		];

		$token = $this->get_token();
		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get( $url, [
			'headers' => $headers,
			'timeout' => 10,
		] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $this->cache_key, [ 'error' => true ], HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['tag_name'] ) ) {
			return null;
		}

		// Look for a release asset named "wham-reports.zip".
		$asset_url = '';
		if ( ! empty( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( ( $asset['name'] ?? '' ) === 'wham-reports.zip' ) {
					$asset_url = $asset['browser_download_url'] ?? '';
					break;
				}
			}
		}

		$release = [
			'version'     => ltrim( $data['tag_name'], 'vV' ),
			'tag_name'    => $data['tag_name'],
			'name'        => $data['name'] ?? $data['tag_name'],
			'body'        => $data['body'] ?? '',
			'published'   => $data['published_at'] ?? '',
			'zipball_url' => $data['zipball_url'] ?? '',
			'asset_url'   => $asset_url,
			'html_url'    => $data['html_url'] ?? '',
		];

		set_transient( $this->cache_key, $release, 6 * HOUR_IN_SECONDS );

		return $release;
	}

	/**
	 * Build the download URL, preferring the release asset over the zipball.
	 */
	private function get_download_url( array $release ): string {
		// Prefer the properly-named release asset.
		if ( ! empty( $release['asset_url'] ) ) {
			return $release['asset_url'];
		}

		// Fallback to zipball (needs post_install rename).
		$url   = $release['zipball_url'];
		$token = $this->get_token();
		if ( $token ) {
			$url = add_query_arg( 'access_token', $token, $url );
		}
		return $url;
	}

	/**
	 * Inject update info into WordPress's update transient.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->fetch_release_data();
		if ( ! $release ) {
			return $transient;
		}

		if ( version_compare( $release['version'], $this->current_version, '>' ) ) {
			$transient->response[ $this->plugin_file ] = (object) [
				'slug'        => $this->slug,
				'plugin'      => $this->plugin_file,
				'new_version' => $release['version'],
				'package'     => $this->get_download_url( $release ),
				'url'         => $release['html_url'],
			];
		}

		return $transient;
	}

	/**
	 * Provide plugin info for the "View details" modal.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ( $args->slug ?? '' ) !== $this->slug ) {
			return $result;
		}

		$release = $this->fetch_release_data();
		if ( ! $release ) {
			return $result;
		}

		return (object) [
			'name'          => 'WHAM Reports',
			'slug'          => $this->slug,
			'version'       => $release['version'],
			'author'        => 'Clear pH Design',
			'homepage'      => 'https://github.com/' . $this->repo,
			'requires'      => '6.0',
			'requires_php'  => '8.0',
			'downloaded'    => 0,
			'last_updated'  => $release['published'],
			'sections'      => [
				'description'  => 'Automated monthly reporting for WHAM clients.',
				'changelog'    => nl2br( esc_html( $release['body'] ) ),
			],
			'download_link' => $this->get_download_url( $release ),
		];
	}

	/**
	 * After install, rename the extracted folder to match the plugin slug.
	 * Only needed for zipball fallback — release assets should already have the right structure.
	 */
	public function post_install( $response, $hook_extra, $result ) {
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_file ) {
			return $result;
		}

		global $wp_filesystem;

		$plugin_dir  = WP_PLUGIN_DIR . '/' . $this->slug;
		$install_dir = $result['destination'];

		// Move from GitHub's extracted folder name to our expected slug.
		if ( $install_dir !== $plugin_dir ) {
			$wp_filesystem->move( $install_dir, $plugin_dir );
			$result['destination'] = $plugin_dir;
		}

		// Re-activate if it was active.
		if ( is_plugin_active( $this->plugin_file ) ) {
			activate_plugin( $this->plugin_file );
		}

		return $result;
	}

	/**
	 * AJAX handler for "Check for updates" — returns JSON with update status.
	 */
	public function ajax_check_updates(): void {
		check_ajax_referer( 'wham_check_updates', 'nonce' );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		// Force a fresh check (bypass cache).
		delete_transient( $this->cache_key );
		$release = $this->fetch_release_data( true );

		if ( ! $release ) {
			wp_send_json_error( 'Could not reach GitHub. Check your token in Settings > Plugin Updates.' );
		}

		$has_update = version_compare( $release['version'], $this->current_version, '>' );

		if ( $has_update ) {
			// Inject into update_plugins transient so the standard WP "update now" link works.
			$transient = get_site_transient( 'update_plugins' );
			if ( ! is_object( $transient ) ) {
				$transient = new \stdClass();
			}
			$transient->response[ $this->plugin_file ] = (object) [
				'slug'        => $this->slug,
				'plugin'      => $this->plugin_file,
				'new_version' => $release['version'],
				'package'     => $this->get_download_url( $release ),
				'url'         => $release['html_url'],
			];
			set_site_transient( 'update_plugins', $transient );
		}

		wp_send_json_success( [
			'has_update'      => $has_update,
			'current_version' => $this->current_version,
			'latest_version'  => $release['version'],
			'update_url'      => $has_update ? wp_nonce_url(
				self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . urlencode( $this->plugin_file ) ),
				'upgrade-plugin_' . $this->plugin_file
			) : '',
			'details_url'     => $release['html_url'],
		] );
	}

	/**
	 * Clear the cached release data to force a fresh check.
	 */
	public static function flush_cache(): void {
		delete_transient( 'wham_github_updater' );
		delete_site_transient( 'update_plugins' );
	}
}
