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
     * Direct database access — MainWP stores child site data in wp_mainwp_wp.
     */
    private function collect_via_db( string $site_id, string $tier ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'mainwp_wp';
        $site  = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $site_id ),
            ARRAY_A
        );

        if ( ! $site ) {
            return $this->empty_result( 'Site not found in MainWP (ID: ' . $site_id . ')' );
        }

        // MainWP stores plugin/theme info as serialized data.
        $plugins_raw  = maybe_unserialize( $site['plugins'] ?? '' );
        $themes_raw   = maybe_unserialize( $site['themes'] ?? '' );
        $plugin_updates = maybe_unserialize( $site['plugin_upgrades'] ?? '' );
        $theme_updates  = maybe_unserialize( $site['theme_upgrades'] ?? '' );
        $wp_upgrades    = maybe_unserialize( $site['wp_upgrades'] ?? '' );

        $plugins        = is_array( $plugins_raw ) ? $plugins_raw : [];
        $themes         = is_array( $themes_raw ) ? $themes_raw : [];
        $p_updates      = is_array( $plugin_updates ) ? $plugin_updates : [];
        $t_updates      = is_array( $theme_updates ) ? $theme_updates : [];

        // Active theme detection.
        $active_theme = '';
        $active_theme_version = '';
        foreach ( $themes as $theme ) {
            if ( ! empty( $theme['active'] ) && $theme['active'] == 1 ) {
                $active_theme = $theme['name'] ?? $theme['slug'] ?? '';
                $active_theme_version = $theme['version'] ?? '';
                break;
            }
        }

        // Count active plugins.
        $active_plugins = array_filter( $plugins, function( $p ) {
            return ! empty( $p['active'] ) && $p['active'] == 1;
        });

        $result = [
            'source'              => 'mainwp_db',
            'wp_version'          => $site['version'] ?? 'Unknown',
            'wp_update_available' => ! empty( $wp_upgrades ),
            'wp_update_version'   => $wp_upgrades['current'] ?? null,
            'plugins_total'       => count( $active_plugins ),
            'plugins_needing_update' => array_map( function( $p ) {
                return [
                    'name'        => $p['Name'] ?? $p['name'] ?? 'Unknown',
                    'current'     => $p['Version'] ?? $p['version'] ?? '',
                    'new_version' => $p['update']['new_version'] ?? $p['new_version'] ?? '',
                ];
            }, $p_updates ),
            'plugins_updates_count' => count( $p_updates ),
            'theme_name'          => $active_theme,
            'theme_version'       => $active_theme_version,
            'theme_update_available' => ! empty( $t_updates ),
            'php_version'         => $site['phpversion'] ?? 'Unknown',
            'last_sync'           => date( 'c', $site['dtsSync'] ?? time() ),
            'site_name'           => $site['name'] ?? '',
            'site_url'            => $site['url'] ?? '',
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
