<?php
namespace WHAM_Reports;

defined( 'ABSPATH' ) || exit;

/**
 * MainWP Data Source — Category C: Updates & Maintenance
 *
 * Pulls site health data from MainWP. Two strategies:
 * 1. Direct DB access (preferred — plugin runs on same WP install as MainWP)
 * 2. REST API fallback (if running on a different server)
 */
class MainWP_Source {

    /**
     * Collect maintenance data for a single child site.
     *
     * @param string $mainwp_site_id  The MainWP child site ID.
     * @param string $tier            Client tier (basic/professional/premium).
     * @return array  Normalized maintenance data.
     */
    public function collect( string $mainwp_site_id, string $tier = 'basic' ): array {
        // Try direct DB access first (fastest, no auth needed).
        if ( class_exists( '\MainWP\Dashboard\MainWP_DB' ) ) {
            return $this->collect_via_db( $mainwp_site_id, $tier );
        }

        // Fallback to REST API.
        return $this->collect_via_api( $mainwp_site_id, $tier );
    }

    /**
     * Direct database access — MainWP data spans three tables:
     *   mainwp_wp       — site basics, plugins (JSON), themes (JSON), plugin_upgrades (JSON), theme_upgrades (JSON)
     *   mainwp_wp_sync  — WP version, last sync timestamp (dtsSync)
     *   mainwp_wp_options — per-site key/value pairs (phpversion, site_info JSON, etc.)
     */
    private function collect_via_db( string $site_id, string $tier ): array {
        global $wpdb;

        $table_wp   = $wpdb->prefix . 'mainwp_wp';
        $table_sync = $wpdb->prefix . 'mainwp_wp_sync';
        $table_opts = $wpdb->prefix . 'mainwp_wp_options';

        // Main site row + sync data via JOIN.
        $site = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT w.*, s.version AS wp_version, s.dtsSync
                 FROM {$table_wp} w
                 LEFT JOIN {$table_sync} s ON s.wpid = w.id
                 WHERE w.id = %d",
                (int) $site_id
            ),
            ARRAY_A
        );

        if ( ! $site ) {
            return $this->empty_result( 'Site not found in MainWP (ID: ' . $site_id . ')' );
        }

        // PHP version from options table.
        $php_version = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT value FROM {$table_opts} WHERE wpid = %d AND name = 'phpversion'",
                (int) $site_id
            )
        );

        // Fallback: try site_info JSON which also contains phpversion.
        if ( empty( $php_version ) ) {
            $site_info_json = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT value FROM {$table_opts} WHERE wpid = %d AND name = 'site_info'",
                    (int) $site_id
                )
            );
            if ( $site_info_json ) {
                $site_info   = json_decode( $site_info_json, true );
                $php_version = $site_info['phpversion'] ?? '';
            }
        }

        // MainWP stores plugin/theme data as JSON (not serialized PHP).
        $plugins_raw    = json_decode( $site['plugins'] ?? '[]', true );
        $themes_raw     = json_decode( $site['themes'] ?? '[]', true );
        $plugin_updates = json_decode( $site['plugin_upgrades'] ?? '{}', true );
        $theme_updates  = json_decode( $site['theme_upgrades'] ?? '{}', true );
        $wp_upgrades    = json_decode( $site['wp_upgrades'] ?? '""', true );

        $plugins   = is_array( $plugins_raw ) ? $plugins_raw : [];
        $themes    = is_array( $themes_raw ) ? $themes_raw : [];
        $p_updates = is_array( $plugin_updates ) ? $plugin_updates : [];
        $t_updates = is_array( $theme_updates ) ? $theme_updates : [];

        // Active theme detection.
        $active_theme         = '';
        $active_theme_version = '';
        foreach ( $themes as $theme ) {
            if ( ! empty( $theme['active'] ) && $theme['active'] == 1 ) {
                $active_theme         = $theme['name'] ?? $theme['slug'] ?? '';
                $active_theme_version = $theme['version'] ?? '';
                break;
            }
        }

        // Count active plugins.
        $active_plugins = array_filter( $plugins, function( $p ) {
            return ! empty( $p['active'] ) && $p['active'] == 1;
        });

        // plugin_upgrades is keyed by slug, each value has Name, Version, new_version, etc.
        $plugins_needing_update = array_map( function( $p ) {
            return [
                'name'        => $p['Name'] ?? $p['name'] ?? 'Unknown',
                'current'     => $p['Version'] ?? $p['version'] ?? '',
                'new_version' => $p['update']['new_version'] ?? $p['new_version'] ?? '',
            ];
        }, $p_updates );

        $result = [
            'source'                 => 'mainwp_db',
            'wp_version'             => $site['wp_version'] ?? 'Unknown',
            'wp_update_available'    => ! empty( $wp_upgrades ),
            'wp_update_version'      => is_array( $wp_upgrades ) ? ( $wp_upgrades['current'] ?? null ) : null,
            'plugins_total'          => count( $active_plugins ),
            'plugins_needing_update' => $plugins_needing_update,
            'plugins_updates_count'  => count( $p_updates ),
            'theme_name'             => $active_theme,
            'theme_version'          => $active_theme_version,
            'theme_update_available' => ! empty( $t_updates ),
            'php_version'            => $php_version ?: 'Unknown',
            'last_sync'              => ! empty( $site['dtsSync'] ) ? date( 'c', $site['dtsSync'] ) : null,
            'site_name'              => $site['name'] ?? '',
            'site_url'               => $site['url'] ?? '',
        ];

        // Professional+ gets plugin update detail log.
        if ( $tier === 'basic' ) {
            unset( $result['plugins_needing_update'] );
        }

        return $result;
    }

    /**
     * REST API fallback — uses MainWP REST API.
     */
    private function collect_via_api( string $site_id, string $tier ): array {
        $app_password = get_option( 'wham_mainwp_app_password' );
        if ( empty( $app_password ) ) {
            return $this->empty_result( 'MainWP REST API: No application password configured.' );
        }

        $base_url = trailingslashit( site_url() ) . 'wp-json/mainwp/v2';
        $headers  = [
            'Authorization' => 'Basic ' . base64_encode( 'admin:' . $app_password ),
            'Content-Type'  => 'application/json',
        ];

        // Fetch site info.
        $response = wp_remote_get( $base_url . '/site/' . $site_id, [
            'headers' => $headers,
            'timeout' => 30,
        ]);

        if ( is_wp_error( $response ) ) {
            return $this->empty_result( 'MainWP API error: ' . $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body ) ) {
            return $this->empty_result( 'MainWP API returned empty response for site ' . $site_id );
        }

        return [
            'source'              => 'mainwp_api',
            'wp_version'          => $body['version'] ?? 'Unknown',
            'wp_update_available' => ! empty( $body['wp_upgrades'] ),
            'plugins_total'       => $body['plugins_count'] ?? 0,
            'plugins_updates_count' => $body['plugin_upgrades_count'] ?? 0,
            'theme_name'          => $body['active_theme'] ?? '',
            'theme_version'       => $body['active_theme_version'] ?? '',
            'theme_update_available' => ! empty( $body['theme_upgrades'] ),
            'php_version'         => $body['phpversion'] ?? 'Unknown',
            'last_sync'           => $body['last_sync'] ?? date( 'c' ),
            'site_name'           => $body['name'] ?? '',
            'site_url'            => $body['url'] ?? '',
        ];
    }

    /**
     * List all MainWP child sites with their IDs, names, and URLs.
     *
     * @return array  Keyed by site ID => [ 'name' => ..., 'url' => ..., 'domain' => ... ].
     */
    public function list_sites(): array {
        if ( ! class_exists( '\MainWP\Dashboard\MainWP_DB' ) ) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mainwp_wp';
        $sites = $wpdb->get_results( "SELECT id, name, url FROM {$table} ORDER BY name ASC", ARRAY_A );

        if ( ! $sites ) {
            return [];
        }

        $result = [];
        foreach ( $sites as $site ) {
            $domain = strtolower( parse_url( $site['url'] ?? '', PHP_URL_HOST ) ?: '' );
            $domain = preg_replace( '/^www\./', '', $domain );
            $result[ $site['id'] ] = [
                'name'   => $site['name'] ?? '',
                'url'    => $site['url'] ?? '',
                'domain' => $domain,
            ];
        }

        return $result;
    }

    /**
     * Return a safe empty structure when data can't be collected.
     */
    private function empty_result( string $error = '' ): array {
        return [
            'source'              => 'error',
            'error'               => $error,
            'wp_version'          => 'N/A',
            'wp_update_available' => false,
            'plugins_total'       => 0,
            'plugins_updates_count' => 0,
            'theme_name'          => 'N/A',
            'theme_version'       => 'N/A',
            'theme_update_available' => false,
            'php_version'         => 'N/A',
            'last_sync'           => null,
        ];
    }
}
