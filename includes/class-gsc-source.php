<?php
namespace WHAM_Reports;

defined( 'ABSPATH' ) || exit;

/**
 * Google Search Console Data Source — Category F: SEO & Traffic (Search)
 *
 * Uses Google Search Console API via service account credentials.
 * Pulls performance data (clicks, impressions, CTR, position) and top queries.
 */
class GSC_Source {

    private ?string $access_token = null;

    /**
     * Collect GSC data for a property.
     *
     * @param string $gsc_property  The GSC property (e.g., "sc-domain:example.com").
     * @param string $tier          Client tier.
     * @return array  Normalized SEO search data.
     */
    public function collect( string $gsc_property, string $tier = 'basic' ): array {
        if ( empty( $gsc_property ) ) {
            return $this->empty_result( 'No GSC property configured for this client.' );
        }

        $token = $this->get_access_token();
        if ( ! $token ) {
            return $this->empty_result( 'Could not obtain Google API access token.' );
        }

        // Date ranges.
        $end_date   = date( 'Y-m-d', strtotime( '-2 days' ) ); // GSC data has ~2-day delay.
        $start_date = date( 'Y-m-d', strtotime( '-30 days' ) );

        // Previous period for comparison.
        $prev_end   = date( 'Y-m-d', strtotime( '-32 days' ) );
        $prev_start = date( 'Y-m-d', strtotime( '-60 days' ) );

        // 1. Aggregate performance (all tiers).
        $aggregate = $this->query_search_analytics( $gsc_property, $token, $start_date, $end_date );
        if ( isset( $aggregate['error'] ) ) {
            return $this->empty_result( $aggregate['error'] );
        }

        $result = [
            'source'       => 'gsc_api',
            'property'     => $gsc_property,
            'period'       => $start_date . ' to ' . $end_date,
            'clicks'       => $aggregate['clicks'] ?? 0,
            'impressions'  => $aggregate['impressions'] ?? 0,
            'ctr'          => round( ( $aggregate['ctr'] ?? 0 ) * 100, 2 ),
            'position'     => round( $aggregate['position'] ?? 0, 1 ),
        ];

        // Previous period comparison (all tiers — needed by Insights Engine).
        $prev = $this->query_search_analytics( $gsc_property, $token, $prev_start, $prev_end );
        $prev_ctr      = round( ( $prev['ctr'] ?? 0 ) * 100, 2 );
        $prev_position = round( $prev['position'] ?? 0, 1 );
        $result['comparison'] = [
            'prev_clicks'        => $prev['clicks'] ?? 0,
            'prev_impressions'   => $prev['impressions'] ?? 0,
            'prev_ctr'           => $prev_ctr,
            'prev_position'      => $prev_position,
            'clicks_change'      => $this->percent_change( $prev['clicks'] ?? 0, $aggregate['clicks'] ?? 0 ),
            'impressions_change' => $this->percent_change( $prev['impressions'] ?? 0, $aggregate['impressions'] ?? 0 ),
        ];

        // Detailed data (always collected; visibility controlled at render layer).
        // Top queries.
        $top_queries = $this->query_search_analytics( $gsc_property, $token, $start_date, $end_date, [ 'query' ], 10 );
        $result['top_queries'] = $this->format_rows( $top_queries['rows'] ?? [], 'query' );

        // Top pages.
        $top_pages = $this->query_search_analytics( $gsc_property, $token, $start_date, $end_date, [ 'page' ], 5 );
        $result['top_pages'] = $this->format_rows( $top_pages['rows'] ?? [], 'page' );

        // Daily time series for charts.
        $daily_current = $this->query_search_analytics( $gsc_property, $token, $start_date, $end_date, [ 'date' ], 31 );
        if ( ! empty( $daily_current['rows'] ) ) {
            $result['daily_clicks']      = [];
            $result['daily_impressions'] = [];
            $result['daily_labels']      = [];
            foreach ( $daily_current['rows'] as $row ) {
                $date_str = $row['keys'][0] ?? '';
                $result['daily_labels'][]      = date( 'M j', strtotime( $date_str ) );
                $result['daily_clicks'][]      = (int) ( $row['clicks'] ?? 0 );
                $result['daily_impressions'][] = (int) ( $row['impressions'] ?? 0 );
            }
        }

        // Previous period daily data.
        $daily_prev = $this->query_search_analytics( $gsc_property, $token, $prev_start, $prev_end, [ 'date' ], 31 );
        if ( ! empty( $daily_prev['rows'] ) ) {
            $result['prev_daily_clicks']      = [];
            $result['prev_daily_impressions'] = [];
            foreach ( $daily_prev['rows'] as $row ) {
                $result['prev_daily_clicks'][]      = (int) ( $row['clicks'] ?? 0 );
                $result['prev_daily_impressions'][] = (int) ( $row['impressions'] ?? 0 );
            }
        }

        return $result;
    }

    /**
     * Query GSC Search Analytics API.
     */
    private function query_search_analytics(
        string $property,
        string $token,
        string $start_date,
        string $end_date,
        array $dimensions = [],
        int $row_limit = 0
    ): array {
        $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode( $property ) . '/searchAnalytics/query';

        $body = [
            'startDate' => $start_date,
            'endDate'   => $end_date,
        ];

        if ( ! empty( $dimensions ) ) {
            $body['dimensions'] = $dimensions;
        }

        if ( $row_limit > 0 ) {
            $body['rowLimit'] = $row_limit;
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
            return [ 'error' => 'GSC API request failed: ' . $response->get_error_message() ];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $data['error'] ) ) {
            return [ 'error' => 'GSC API error: ' . ( $data['error']['message'] ?? 'Unknown error' ) ];
        }

        // For aggregate queries (no dimensions), extract totals from the single row.
        if ( empty( $dimensions ) && ! empty( $data['rows'][0] ) ) {
            return $data['rows'][0];
        }

        return $data;
    }

    /**
     * Format dimension rows into clean arrays.
     */
    private function format_rows( array $rows, string $dimension_key ): array {
        return array_map( function( $row ) use ( $dimension_key ) {
            return [
                $dimension_key  => $row['keys'][0] ?? '',
                'clicks'        => $row['clicks'] ?? 0,
                'impressions'   => $row['impressions'] ?? 0,
                'ctr'           => round( ( $row['ctr'] ?? 0 ) * 100, 2 ),
                'position'      => round( $row['position'] ?? 0, 1 ),
            ];
        }, $rows );
    }

    /**
     * Get OAuth access token from service account credentials.
     */
    private function get_access_token(): ?string {
        if ( $this->access_token ) {
            return $this->access_token;
        }

        // Check for cached token.
        $cached = get_transient( 'wham_gsc_access_token' );
        if ( $cached ) {
            $this->access_token = $cached;
            return $cached;
        }

        $creds_json = get_option( 'wham_gsc_credentials_json' );
        if ( empty( $creds_json ) ) {
            return null;
        }

        $creds = json_decode( $creds_json, true );
        if ( ! $creds || empty( $creds['client_email'] ) || empty( $creds['private_key'] ) ) {
            return null;
        }

        // Build JWT.
        $now    = time();
        $header = base64url_encode( wp_json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) );
        $claims = base64url_encode( wp_json_encode( [
            'iss'   => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ]));

        $signature_input = $header . '.' . $claims;
        openssl_sign( $signature_input, $signature, $creds['private_key'], OPENSSL_ALGO_SHA256 );
        $jwt = $signature_input . '.' . base64url_encode( $signature );

        // Exchange JWT for access token.
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
        set_transient( 'wham_gsc_access_token', $this->access_token, 3500 );

        return $this->access_token;
    }

    private function percent_change( float $old, float $new ): float {
        if ( $old == 0 ) return $new > 0 ? 100 : 0;
        return round( ( ( $new - $old ) / $old ) * 100, 1 );
    }

    private function empty_result( string $error = '' ): array {
        return [
            'source' => 'error',
            'error'  => $error,
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0,
            'position' => 0,
        ];
    }
}

/**
 * URL-safe base64 encoding (no padding).
 */
if ( ! function_exists( __NAMESPACE__ . '\\base64url_encode' ) ) {
    function base64url_encode( string $data ): string {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }
}
