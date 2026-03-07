<?php
/**
 * GitHub Updater — checks GitHub releases for plugin updates.
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
	private function fetch_release_data(): ?array {
		$cached = get_transient( $this->cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
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
			// Cache the failure for 1 hour so we don't hammer the API.
			set_transient( $this->cache_key, [ 'error' => true ], HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['tag_name'] ) ) {
			return null;
		}

		$release = [
			'version'     => ltrim( $data['tag_name'], 'vV' ),
			'tag_name'    => $data['tag_name'],
			'name'        => $data['name'] ?? $data['tag_name'],
			'body'        => $data['body'] ?? '',
			'published'   => $data['published_at'] ?? '',
			'zipball_url' => $data['zipball_url'] ?? '',
			'html_url'    => $data['html_url'] ?? '',
		];

		set_transient( $this->cache_key, $release, 6 * HOUR_IN_SECONDS );

		return $release;
	}

	/**
	 * Inject update info into WordPress's update transient.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->fetch_release_data();
		if ( ! $release || ! empty( $release['error'] ) ) {
			return $transient;
		}

		if ( version_compare( $release['version'], $this->current_version, '>' ) ) {
			$download_url = $release['zipball_url'];
			$token        = $this->get_token();
			if ( $token ) {
				$download_url = add_query_arg( 'access_token', $token, $download_url );
			}

			$transient->response[ $this->plugin_file ] = (object) [
				'slug'        => $this->slug,
				'plugin'      => $this->plugin_file,
				'new_version' => $release['version'],
				'package'     => $download_url,
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
		if ( ! $release || ! empty( $release['error'] ) ) {
			return $result;
		}

		return (object) [
			'name'          => 'WHAM Reports',
			'slug'          => $this->slug,
			'version'       => $release['version'],
			'author'        => 'Clear ph Design',
			'homepage'      => 'https://github.com/' . $this->repo,
			'requires'      => '6.0',
			'requires_php'  => '8.0',
			'downloaded'    => 0,
			'last_updated'  => $release['published'],
			'sections'      => [
				'description'  => 'Automated monthly reporting for WHAM clients.',
				'changelog'    => nl2br( esc_html( $release['body'] ) ),
			],
			'download_link' => $release['zipball_url'],
		];
	}

	/**
	 * After install, rename the extracted folder to match the plugin slug.
	 * GitHub zipballs extract to "owner-repo-hash/" which breaks WP's expectations.
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
	 * Clear the cached release data to force a fresh check.
	 */
	public static function flush_cache(): void {
		delete_transient( 'wham_github_updater' );
		delete_site_transient( 'update_plugins' );
	}
}
