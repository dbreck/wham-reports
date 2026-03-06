<?php
namespace WHAM_Reports;

defined( 'ABSPATH' ) || exit;

/**
 * Google Analytics 4 Data Source — Category F: SEO & Traffic (Analytics)
 *
 * Uses GA4 Data API v1 via service account.
 * Only included for Professional+ tiers (Basic skips analytics).
 */
class GA4_Source {

    private ?string $access_token = null;

    /**
     * Collect GA4 analytics data for a property.
     *
     * @param string $ga4_property_id  The GA4 property ID (numeric, e.g., "123456789").
     * @param string $tier             Client tier.
     * @return array  Normalized analytics data.
     */
    public function collect( string $ga4_property_id, string $tier = 'basic' ): array {
        // Basic tier doesn't get GA4 data per the framework.
        if ( $tier === 'basic' ) {
            return [ 'source' => 'skipped', 'reason' => 'GA4 not included in Basic tier.' ];
        }

        if ( empty( $ga4_property_id ) ) {
            return $this->empty_result( 'No GA4 property ID configured for this client.' );
        }

        $token = $this->get_access_token();
        if ( ! $token ) {
            return $this->empty_result( 'Could not obtain Google API access token for GA4.' );
        }

        $end_date   = date( 'Y-m-d', strtotime( '-1 day' ) );
        $start_date = date( 'Y-m-d', strtotime( '-30 days' ) );

        // 1. Core metrics.
        $core = $this->run_report( $ga4_property_id, $token, $start_date, $end_date, [], [
            'sessions',
            'totalUsers',
            'newUsers',
            'bounceRate',
            'averageSessionDuration',
            'screenPageViews',
        ]);

        if ( isset( $core['error'] ) ) {
            return $this->empty_result( $core['error'] );
        }

        $core_row = $core['rows'][0]['metricValues'] ?? [];
        $result = [
            'source'              => 'ga4_api',
            'property_id'         => $ga4_property_id,
            'period'              => $start_date . ' to ' . $end_date,
            'sessions'            => (int) ( $core_row[0]['value'] ?? 0 ),
            'users'               => (int) ( $core_row[1]['value'] ?? 0 ),
            'new_users'           => (int) ( $core_row[2]['value'] ?? 0 ),
            'bounce_rate'         => round( (float) ( $core_row[3]['value'] ?? 0 ) * 100, 1 ),
            'avg_session_duration' => round( (float) ( $core_row[4]['value'] ?? 0 ), 0 ),
            'pageviews'           => (int) ( $core_row[5]['value'] ?? 0 ),
        ];

        // 2. Traffic by source/medium (Professional+).
        $traffic = $this->run_report( $ga4_property_id, $token, $start_date, $end_date,
            [ 'sessionDefaultChannelGroup' ],
            [ 'sessions', 'totalUsers' ],
            5
        );
        $result['traffic_sources'] = $this->extract_dimension_rows(
            $traffic['rows'] ?? [], 'sessionDefaultChannelGroup'
        );

        // 3. Top landing pages (Professional+).
        $pages = $this->run_report( $ga4_property_id, $token, $start_date, $end_date,
            [ 'landingPagePlusQueryString' ],
            [ 'sessions', 'totalUsers', 'bounceRate' ],
            5
        );
        $result['top_landing_pages'] = $this->extract_dimension_rows(
            $pages['rows'] ?? [], 'landingPagePlusQueryString'
        );

        return $result;
    }

    /**
     * Run a GA4 Data API report.
     */
    private function run_report(
        string $property_id,
        string $token,
        string $start_date,
        string $end_date,
        array $dimensions = [],
        array $metrics = [],
        int $limit = 0
    ): array {
        $url = 'https://analyticsdata.googleapis.com/v1beta/properties/' . $property_id . ':runReport';

        $body = [
            'dateRanges' => [
                [ 'startDate' => $start_date, 'endDate' => $end_date ],
            ],
            'metrics' => array_map( function( $m ) {
                return [ 'name' => $m ];
            }, $metrics ),
        ];

        if ( ! empty( $dimensions ) ) {
            $body['dimensions'] = array_map( function( $d ) {
                return [ 'name' => $d ];
            }, $dimensions );
            $body['orderBys'] = [
                [ 'metric' => [ 'metricName' => $metrics[0] ], 'desc' => true ],
            ];
        }

        if ( $limit > 0 ) {
            $body['limit'] = $limit;
        }

        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ]);

        if ( is_wp_error( $response ) ) {
            return [ 'error' => 'GA4 API request failed: ' . $response->get_error_message() ];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $data['error'] ) ) {
            return [ 'error' => 'GA4 API error: ' . ( $data['error']['message'] ?? 'Unknown error' ) ];
        }

        return $data;
    }

    /**
     * Extract dimension rows into a clean format.
     */
    private function extract_dimension_rows( array $rows, string $dimension_name ): array {
        return array_map( function( $row ) use ( $dimension_name ) {
            $dim     = $row['dimensionValues'][0]['value'] ?? '';
            $metrics = $row['metricValues'] ?? [];

            $entry = [ $dimension_name => $dim ];
            // Map metrics by index — GA4 returns them in order of the request.
            foreach ( $metrics as $i => $m ) {
                $val = $m['value'] ?? 0;
                $entry[ 'metric_' . $i ] = is_numeric( $val ) ? round( (float) $val, 2 ) : $val;
            }
            return $entry;
        }, $rows );
    }

    /**
     * Get OAuth access token from service account credentials.
     */
    private function get_access_token(): ?string {
        if ( $this->access_token ) {
            return $this->access_token;
        }

        $cached = get_transient( 'wham_ga4_access_token' );
        if ( $cached ) {
            $this->access_token = $cached;
            return $cached;
        }

        // Can reuse the same creds as GSC, or separate ones.
        $creds_json = get_option( 'wham_ga4_credentials_json' );
        if ( empty( $creds_json ) ) {
            // Fallback: try GSC creds (same service account).
            $creds_json = get_option( 'wham_gsc_credentials_json' );
        }

        if ( empty( $creds_json ) ) {
            return null;
        }

        $creds = json_decode( $creds_json, true );
        if ( ! $creds || empty( $creds['client_email'] ) || empty( $creds['private_key'] ) ) {
            return null;
        }

        $now    = time();
        $header = base64url_encode( wp_json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) );
        $claims = base64url_encode( wp_json_encode( [
            'iss'   => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ]));

        $signature_input = $header . '.' . $claims;
        openssl_sign( $signature_input, $signature, $creds['private_key'], OPENSSL_ALGO_SHA256 );
        $jwt = $signature_input . '.' . base64url_encode( $signature );

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ],
            'timeout' => 15,
        ]);

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['access_token'] ) ) {
            return null;
        }

        $this->access_token = $data['access_token'];
        set_transient( 'wham_ga4_access_token', $this->access_token, 3500 );

        return $this->access_token;
    }

    private function empty_result( string $error = '' ): array {
        return [
            'source' => 'error',
            'error'  => $error,
            'sessions' => 0,
            'users' => 0,
            'pageviews' => 0,
        ];
    }
}
