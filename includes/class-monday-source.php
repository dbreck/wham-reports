<?php
namespace WHAM_Reports;

defined( 'ABSPATH' ) || exit;

/**
 * Monday.com Data Source — Category G: Development Hours
 *
 * Reads from the WHAM Clients board (ID: 9141194124).
 * Items = clients, Subitems = monthly report entries with time tracking.
 *
 * Key column IDs:
 * - Items:    color_mkqxgshx (Plan Type), numeric_mkvgfs2a (Monthly Included Hours)
 * - Subitems: duration_mksn85dy (Time Tracking), status (Report Status), date0 (Date Sent)
 */
class Monday_Source {

    private const BOARD_ID         = '9141194124';
    private const API_URL          = 'https://api.monday.com/v2';
    private const COL_PLAN_TYPE    = 'color_mkqxgshx';
    private const COL_INCLUDED_HRS = 'numeric_mkvgfs2a';
    private const COL_WEBSITE      = 'link_mkqx3m9';
    private const SUB_TIME_TRACK   = 'duration_mksn85dy';
    private const SUB_STATUS       = 'status';
    private const SUB_DATE_SENT    = 'date0';

    /**
     * Collect dev hours data for a specific client.
     *
     * @param string $monday_item_id  The Monday.com item ID for the client.
     * @param string $period          Report period (e.g., "2026-03").
     * @return array  Dev hours data.
     */
    public function collect( string $monday_item_id, string $period = '' ): array {
        $token = get_option( 'wham_monday_api_token' );
        if ( empty( $token ) ) {
            return $this->empty_result( 'Monday.com API token not configured.' );
        }

        if ( empty( $period ) ) {
            $period = date( 'Y-m' );
        }

        // Fetch the client item with its subitems.
        $query = '
        query {
            items(ids: [' . (int) $monday_item_id . ']) {
                id
                name
                column_values(ids: ["' . self::COL_PLAN_TYPE . '", "' . self::COL_INCLUDED_HRS . '", "' . self::COL_WEBSITE . '"]) {
                    id
                    text
                    value
                }
                subitems {
                    id
                    name
                    column_values(ids: ["' . self::SUB_TIME_TRACK . '", "' . self::SUB_STATUS . '", "' . self::SUB_DATE_SENT . '"]) {
                        id
                        text
                        value
                    }
                }
            }
        }';

        $data = $this->graphql_request( $token, $query );

        if ( isset( $data['error'] ) ) {
            return $this->empty_result( $data['error'] );
        }

        $item = $data['data']['items'][0] ?? null;
        if ( ! $item ) {
            return $this->empty_result( 'Client not found in Monday.com (ID: ' . $monday_item_id . ')' );
        }

        // Parse columns.
        $columns = $this->index_columns( $item['column_values'] );
        $included_hours = (float) ( $columns[ self::COL_INCLUDED_HRS ]['text'] ?? 0 );

        // Find the subitem matching the current period (by name containing month/year).
        $period_label = date( 'F Y', strtotime( $period . '-01' ) ); // e.g., "March 2026"
        $matched_sub  = null;

        foreach ( $item['subitems'] ?? [] as $sub ) {
            // Match by name containing the month and year.
            if ( stripos( $sub['name'], $period_label ) !== false
                || stripos( $sub['name'], $period ) !== false
                || stripos( $sub['name'], date( 'M Y', strtotime( $period . '-01' ) ) ) !== false
            ) {
                $matched_sub = $sub;
                break;
            }
        }

        // Parse time tracking duration.
        $hours_used = 0;
        $work_summary = '';
        $report_status = 'Not Sent';

        if ( $matched_sub ) {
            $sub_cols = $this->index_columns( $matched_sub['column_values'] );

            // Time tracking — value is JSON with duration in seconds.
            $time_val = $sub_cols[ self::SUB_TIME_TRACK ]['value'] ?? '';
            if ( $time_val ) {
                $time_data = json_decode( $time_val, true );
                // Monday.com time_tracking stores running + additional_value.
                $duration_seconds = 0;
                if ( isset( $time_data['duration'] ) ) {
                    $duration_seconds = (int) $time_data['duration'];
                } elseif ( isset( $time_data['additional_value'] ) ) {
                    // Manual entries.
                    $duration_seconds = (int) $time_data['additional_value'];
                }
                // Also check the text representation (e.g., "01:30:00").
                $text_val = $sub_cols[ self::SUB_TIME_TRACK ]['text'] ?? '';
                if ( $duration_seconds === 0 && $text_val ) {
                    $hours_used = $this->parse_duration_text( $text_val );
                } else {
                    $hours_used = round( $duration_seconds / 3600, 2 );
                }
            }

            $report_status = $sub_cols[ self::SUB_STATUS ]['text'] ?? 'Not Sent';
            $work_summary  = $matched_sub['name'] ?? '';
        }

        return [
            'source'          => 'monday_api',
            'monday_item_id'  => $monday_item_id,
            'client_name'     => $item['name'],
            'hours_included'  => $included_hours,
            'hours_used'      => $hours_used,
            'hours_remaining' => max( 0, $included_hours - $hours_used ),
            'work_summary'    => $work_summary,
            'report_status'   => $report_status,
            'subitem_id'      => $matched_sub['id'] ?? null,
            'period'          => $period,
        ];
    }

    /**
     * Fetch all active clients from the Monday.com board.
     *
     * @return array  List of client items with basic info.
     */
    public function get_active_clients(): array {
        $token = get_option( 'wham_monday_api_token' );
        if ( empty( $token ) ) {
            return [];
        }

        $query = '
        query {
            boards(ids: [' . self::BOARD_ID . ']) {
                groups {
                    id
                    title
                }
                items_page(limit: 50) {
                    items {
                        id
                        name
                        group {
                            id
                            title
                        }
                        column_values(ids: ["' . self::COL_PLAN_TYPE . '", "' . self::COL_INCLUDED_HRS . '", "' . self::COL_WEBSITE . '"]) {
                            id
                            text
                            value
                        }
                    }
                }
            }
        }';

        $data = $this->graphql_request( $token, $query );
        if ( isset( $data['error'] ) ) {
            return [];
        }

        $items = $data['data']['boards'][0]['items_page']['items'] ?? [];

        // Filter to "Active Clients" group only.
        return array_filter( $items, function( $item ) {
            $group = $item['group']['title'] ?? '';
            return stripos( $group, 'Active' ) !== false;
        });
    }

    /**
     * Update a subitem's report status on Monday.com.
     *
     * @param string $subitem_id  The subitem ID.
     * @param string $status      Status label (e.g., "Sent", "Working on it").
     * @param string $date_sent   Date in YYYY-MM-DD format (optional).
     */
    public function update_report_status( string $subitem_id, string $status, string $date_sent = '' ): bool {
        $token = get_option( 'wham_monday_api_token' );
        if ( empty( $token ) || empty( $subitem_id ) ) {
            return false;
        }

        // Build column values JSON.
        $col_values = [ self::SUB_STATUS => [ 'label' => $status ] ];

        if ( $date_sent ) {
            $col_values[ self::SUB_DATE_SENT ] = [ 'date' => $date_sent ];
        }

        $mutation = '
        mutation {
            change_multiple_column_values(
                board_id: ' . self::BOARD_ID . ',
                item_id: ' . (int) $subitem_id . ',
                column_values: ' . wp_json_encode( wp_json_encode( $col_values ) ) . '
            ) {
                id
            }
        }';

        $data = $this->graphql_request( $token, $mutation );
        return ! isset( $data['error'] );
    }

    /**
     * Send a GraphQL request to Monday.com.
     */
    private function graphql_request( string $token, string $query ): array {
        $response = wp_remote_post( self::API_URL, [
            'headers' => [
                'Authorization' => $token,
                'Content-Type'  => 'application/json',
                'API-Version'   => '2024-10',
            ],
            'body'    => wp_json_encode( [ 'query' => $query ] ),
            'timeout' => 30,
        ]);

        if ( is_wp_error( $response ) ) {
            return [ 'error' => 'Monday.com API: ' . $response->get_error_message() ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body['errors'] ) ) {
            return [ 'error' => 'Monday.com API: ' . ( $body['errors'][0]['message'] ?? 'Unknown error' ) ];
        }

        return $body;
    }

    /**
     * Index column_values by column ID.
     */
    private function index_columns( array $columns ): array {
        $indexed = [];
        foreach ( $columns as $col ) {
            $indexed[ $col['id'] ] = $col;
        }
        return $indexed;
    }

    /**
     * Parse duration text like "01:30:00" or "0h 45m" into decimal hours.
     */
    private function parse_duration_text( string $text ): float {
        // Try HH:MM:SS format.
        if ( preg_match( '/(\d+):(\d+):(\d+)/', $text, $m ) ) {
            return (int) $m[1] + ( (int) $m[2] / 60 ) + ( (int) $m[3] / 3600 );
        }
        // Try "Xh Ym" format.
        $hours = 0;
        if ( preg_match( '/(\d+)\s*h/i', $text, $m ) ) {
            $hours += (int) $m[1];
        }
        if ( preg_match( '/(\d+)\s*m/i', $text, $m ) ) {
            $hours += (int) $m[1] / 60;
        }
        return round( $hours, 2 );
    }

    private function empty_result( string $error = '' ): array {
        return [
            'source'         => 'error',
            'error'          => $error,
            'hours_included' => 0,
            'hours_used'     => 0,
            'hours_remaining' => 0,
            'work_summary'   => '',
        ];
    }
}
