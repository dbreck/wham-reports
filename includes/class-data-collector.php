<?php
namespace WHAM_Reports;

defined( 'ABSPATH' ) || exit;

require_once WHAM_REPORTS_PATH . 'includes/class-mainwp-source.php';
require_once WHAM_REPORTS_PATH . 'includes/class-gsc-source.php';
require_once WHAM_REPORTS_PATH . 'includes/class-ga4-source.php';
require_once WHAM_REPORTS_PATH . 'includes/class-monday-source.php';
require_once WHAM_REPORTS_PATH . 'includes/class-pdf-generator.php';
require_once WHAM_REPORTS_PATH . 'includes/class-chart-generator.php';
require_once WHAM_REPORTS_PATH . 'includes/class-insights-engine.php';

/**
 * Data Collector — Orchestrates data collection from all sources
 * and creates wham_report posts with PDF attachments.
 */
class Data_Collector {

    private MainWP_Source  $mainwp;
    private GSC_Source     $gsc;
    private GA4_Source     $ga4;
    private Monday_Source  $monday;
    private PDF_Generator  $pdf;

    public function __construct() {
        $this->mainwp  = new MainWP_Source();
        $this->gsc     = new GSC_Source();
        $this->ga4     = new GA4_Source();
        $this->monday  = new Monday_Source();
        $this->pdf     = new PDF_Generator();
    }

    /**
     * Generate reports for all active clients.
     */
    public function generate_all_reports( string $period = '' ): array {
        $client_map = \WHAM_Reports::get_client_map();
        $results    = [];

        if ( empty( $period ) ) {
            $period = date( 'Y-m' );
        }

        foreach ( $client_map as $monday_id => $config ) {
            $result = $this->generate_single_report( $monday_id, $period );
            $results[ $monday_id ] = $result;

            // Brief pause to avoid API rate limits.
            usleep( 500000 ); // 0.5s
        }

        $this->log( sprintf( 'Generated %d reports for period %s.', count( $results ), $period ) );

        return $results;
    }

    /**
     * Generate a report for a single client.
     *
     * @param string $monday_id  Monday.com item ID.
     * @param string $period     Report period (YYYY-MM).
     * @return array  Result with report_id and status.
     */
    public function generate_single_report( string $monday_id, string $period = '' ): array {
        if ( empty( $period ) ) {
            $period = date( 'Y-m' );
        }

        $client_map = \WHAM_Reports::get_client_map();
        $config     = $client_map[ $monday_id ] ?? null;

        if ( ! $config ) {
            return [ 'status' => 'error', 'message' => 'Client not found in mapping: ' . $monday_id ];
        }

        $tier        = $config['tier'] ?? 'basic';
        $client_name = $config['client_name'] ?? 'Unknown';
        $client_url  = $config['client_url'] ?? '';

        $this->log( "Generating report for {$client_name} ({$tier}) — {$period}" );

        // Check for existing report this period.
        $existing = get_posts([
            'post_type'  => 'wham_report',
            'meta_query' => [
                'relation' => 'AND',
                [ 'key' => '_wham_client_id', 'value' => $monday_id ],
                [ 'key' => '_wham_period', 'value' => $period ],
            ],
            'posts_per_page' => 1,
        ]);

        if ( ! empty( $existing ) ) {
            $this->log( "  → Report already exists (ID: {$existing[0]->ID}). Skipping." );
            return [ 'status' => 'skipped', 'report_id' => $existing[0]->ID, 'message' => 'Report already exists.' ];
        }

        // ── Collect Data ──────────────────────────────────────────────

        $report_data = [
            'generated_at' => date( 'c' ),
            'period'       => $period,
            'period_label' => date( 'F Y', strtotime( $period . '-01' ) ),
            'tier'         => $tier,
            'client'       => [
                'name'      => $client_name,
                'url'       => $client_url,
                'monday_id' => $monday_id,
            ],
        ];

        // Category C: Updates & Maintenance (all tiers).
        $mainwp_site_id = $config['mainwp_site_id'] ?? '';
        if ( $mainwp_site_id ) {
            $report_data['maintenance'] = $this->mainwp->collect( $mainwp_site_id, $tier );
            $this->log( '  → MainWP data collected.' );
        } else {
            $report_data['maintenance'] = [ 'source' => 'not_configured', 'error' => 'MainWP site ID not mapped.' ];
            $this->log( '  → MainWP: not configured.' );
        }

        // Category F: SEO & Traffic — GSC (Professional+ or all if we have data).
        $gsc_property = $config['gsc_property'] ?? '';
        if ( $gsc_property ) {
            $report_data['search'] = $this->gsc->collect( $gsc_property, $tier );
            $this->log( '  → GSC data collected.' );
        } else {
            $report_data['search'] = [ 'source' => 'not_configured', 'error' => 'GSC property not mapped.' ];
            $this->log( '  → GSC: not configured.' );
        }

        // Category F: SEO & Traffic — GA4 (Professional+ only).
        $ga4_property = $config['ga4_property'] ?? '';
        if ( $ga4_property && in_array( $tier, [ 'professional', 'premium' ], true ) ) {
            $report_data['analytics'] = $this->ga4->collect( $ga4_property, $tier );
            $this->log( '  → GA4 data collected.' );
        } else {
            $report_data['analytics'] = [ 'source' => 'skipped', 'reason' => $tier === 'basic' ? 'Not included in Basic tier.' : 'GA4 property not mapped.' ];
        }

        // Category G: Dev Hours (all tiers).
        $report_data['dev_hours'] = $this->monday->collect( $monday_id, $period );
        $this->log( '  → Monday.com dev hours collected.' );

        // Generate insights from collected data.
        $report_data['insights'] = Insights_Engine::generate( $report_data );
        $this->log( '  → Insights generated: ' . count( $report_data['insights']['wins'] ?? [] ) . ' wins, ' . count( $report_data['insights']['watch_items'] ?? [] ) . ' watch items.' );

        // Generate chart images.
        $report_data['charts'] = [];

        if ( in_array( $tier, [ 'professional', 'premium' ], true ) ) {
            // GSC trend chart.
            if ( ! empty( $report_data['search']['daily_labels'] ) ) {
                $datasets = [
                    [ 'label' => 'Clicks', 'data' => $report_data['search']['daily_clicks'] ?? [], 'borderColor' => '#3b82f6' ],
                    [ 'label' => 'Impressions', 'data' => $report_data['search']['daily_impressions'] ?? [], 'borderColor' => '#16a34a' ],
                ];
                $report_data['charts']['gsc_trend'] = Chart_Generator::line_chart(
                    $report_data['search']['daily_labels'],
                    $datasets
                );
                $this->log( '  → GSC trend chart generated.' );
            }

            // GA4 traffic sources bar chart.
            if ( ! empty( $report_data['analytics']['traffic_sources'] ) ) {
                $labels = array_column( $report_data['analytics']['traffic_sources'], 'sessionDefaultChannelGroup' );
                $values = array_map( 'intval', array_column( $report_data['analytics']['traffic_sources'], 'metric_0' ) );
                $report_data['charts']['ga4_sources'] = Chart_Generator::bar_chart( $labels, $values );
                $this->log( '  → GA4 sources chart generated.' );
            }

            // GA4 sessions trend.
            if ( ! empty( $report_data['analytics']['daily_labels'] ) ) {
                $datasets = [
                    [ 'label' => 'Sessions', 'data' => $report_data['analytics']['daily_sessions'] ?? [], 'borderColor' => '#3b82f6' ],
                    [ 'label' => 'Users', 'data' => $report_data['analytics']['daily_users'] ?? [], 'borderColor' => '#16a34a' ],
                ];
                $report_data['charts']['ga4_trend'] = Chart_Generator::line_chart(
                    $report_data['analytics']['daily_labels'],
                    $datasets
                );
                $this->log( '  → GA4 trend chart generated.' );
            }
        }

        // Dev hours chart removed in v3.0 — dev hours no longer shown in reports.

        // ── Create Report Post ────────────────────────────────────────

        $title = sprintf( '%s — %s', $client_name, date( 'F Y', strtotime( $period . '-01' ) ) );

        $require_review = get_option( 'wham_require_review', '0' );
        $post_status = $require_review ? 'draft' : 'publish';

        $post_id = wp_insert_post([
            'post_type'   => 'wham_report',
            'post_title'  => $title,
            'post_status' => $post_status,
        ]);

        if ( is_wp_error( $post_id ) ) {
            return [ 'status' => 'error', 'message' => 'Failed to create report post: ' . $post_id->get_error_message() ];
        }

        // Save meta.
        update_post_meta( $post_id, '_wham_client_id', $monday_id );
        update_post_meta( $post_id, '_wham_client_name', $client_name );
        update_post_meta( $post_id, '_wham_client_url', $client_url );
        update_post_meta( $post_id, '_wham_tier', $tier );
        update_post_meta( $post_id, '_wham_period', $period );
        update_post_meta( $post_id, '_wham_report_data', wp_json_encode( $report_data ) );

        $this->log( "  → Report post created (ID: {$post_id})." );

        // ── Generate PDFs (all style variants) ───────────────────────

        $pdf_urls = $this->pdf->generate_all_styles( $report_data, $post_id );
        if ( ! empty( $pdf_urls ) ) {
            $this->log( '  → PDFs generated: ' . implode( ', ', array_keys( $pdf_urls ) ) );
        } else {
            $this->log( '  → PDF generation failed.' );
        }
        $pdf_url = $pdf_urls['editorial'] ?? $pdf_urls['default'] ?? reset( $pdf_urls ) ?: null;

        // ── Update Monday.com Status ──────────────────────────────────

        $subitem_id = $report_data['dev_hours']['subitem_id'] ?? '';
        if ( $subitem_id ) {
            $this->monday->update_report_status( $subitem_id, 'Working on it' );
            $this->log( '  → Monday.com status updated to "Working on it".' );
        }

        return [
            'status'    => 'success',
            'report_id' => $post_id,
            'pdf_url'   => $pdf_url ?? null,
        ];
    }

    /**
     * Generate reports for all clients and optionally send emails.
     *
     * @param string $period       Report period (YYYY-MM), defaults to current month.
     * @param array  $excluded_ids Monday IDs to skip.
     * @param bool   $send_email   Whether to email each report after generation.
     * @return array Results keyed by monday_id.
     */
    public function generate_and_send( string $period = '', array $excluded_ids = [], bool $send_email = false ): array {
        $client_map = \WHAM_Reports::get_client_map();
        $results    = [];

        if ( empty( $period ) ) {
            $period = date( 'Y-m' );
        }

        foreach ( $client_map as $monday_id => $config ) {
            if ( in_array( $monday_id, $excluded_ids, true ) ) {
                $results[ $monday_id ] = [ 'status' => 'excluded' ];
                $this->log( "Skipping excluded client: {$monday_id}" );
                continue;
            }

            $result = $this->generate_single_report( $monday_id, $period );
            $results[ $monday_id ] = $result;

            if ( $send_email && 'success' === ( $result['status'] ?? '' ) && ! empty( $result['report_id'] ) ) {
                $this->send_report_email( (int) $result['report_id'] );
            }

            usleep( 500000 ); // 0.5s pause for rate limits.
        }

        $this->log( sprintf( 'generate_and_send: %d reports processed for period %s.', count( $results ), $period ) );

        return $results;
    }

    /**
     * Send a report email to the client.
     *
     * @param int $report_id  The wham_report post ID.
     * @return bool
     */
    public function send_report_email( int $report_id ): bool {
        $client_map = \WHAM_Reports::get_client_map();
        $client_id  = get_post_meta( $report_id, '_wham_client_id', true );
        $config     = $client_map[ $client_id ] ?? [];
        $email      = $config['client_email'] ?? '';

        if ( empty( $email ) ) {
            $this->log( "Cannot send email for report {$report_id}: no client email." );
            return false;
        }

        $client_name = get_post_meta( $report_id, '_wham_client_name', true );
        $period      = get_post_meta( $report_id, '_wham_period', true );
        $period_label = date( 'F Y', strtotime( $period . '-01' ) );
        $pdf_url     = get_post_meta( $report_id, '_wham_pdf_url', true );
        $tier        = get_post_meta( $report_id, '_wham_tier', true );

        $sender_name  = get_option( 'wham_sender_name', 'WHAM Reports' );
        $sender_email = get_option( 'wham_sender_email', get_option( 'admin_email' ) );

        $subject = "WHAM Report — {$period_label}";

        // Load report data for inline email content.
        $report_data = json_decode( get_post_meta( $report_id, '_wham_report_data', true ), true );

        // Convert chart file paths to public URLs for email img tags.
        $chart_urls = [];
        if ( ! empty( $report_data['charts'] ) ) {
            foreach ( $report_data['charts'] as $key => $path ) {
                if ( ! empty( $path ) && file_exists( $path ) ) {
                    $chart_urls[ $key ] = Chart_Generator::get_chart_url( $path );
                }
            }
        }

        // Load email template.
        ob_start();
        include WHAM_REPORTS_PATH . 'templates/email/report-email.php';
        $body = ob_get_clean();

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$sender_name} <{$sender_email}>",
        ];

        // Attach PDF if available.
        $attachments = [];
        if ( $pdf_url ) {
            $pdf_path = $this->url_to_path( $pdf_url );
            if ( $pdf_path && file_exists( $pdf_path ) ) {
                $attachments[] = $pdf_path;
            }
        }

        $sent = wp_mail( $email, $subject, $body, $headers, $attachments );

        if ( $sent ) {
            // Update Monday.com status if subitem exists.
            if ( ! empty( $report_data['monday']['subitem_id'] ) ) {
                $this->monday->update_report_status( $report_data['monday']['subitem_id'], 'Sent', date( 'Y-m-d' ) );
            } elseif ( ! empty( $report_data['dev_hours']['subitem_id'] ) ) {
                $this->monday->update_report_status( $report_data['dev_hours']['subitem_id'], 'Sent', date( 'Y-m-d' ) );
            }
            $this->log( "Email sent to {$email} for report {$report_id}." );
        } else {
            $this->log( "Failed to send email to {$email} for report {$report_id}." );
        }

        return $sent;
    }

    /**
     * Convert a WordPress URL to a file path.
     */
    private function url_to_path( string $url ): ?string {
        $upload_dir = wp_get_upload_dir();
        $base_url   = $upload_dir['baseurl'];
        $base_dir   = $upload_dir['basedir'];

        if ( strpos( $url, $base_url ) === 0 ) {
            return str_replace( $base_url, $base_dir, $url );
        }

        return null;
    }

    /**
     * Finalize a draft report: apply overrides, publish, and optionally send email.
     *
     * @param int  $post_id    The wham_report post ID.
     * @param bool $send_email Whether to email the report after publishing.
     * @return array Result with status and report_id or error message.
     */
    public function finalize_report( int $post_id, bool $send_email = false ): array {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'wham_report' ) {
            return [ 'status' => 'error', 'message' => 'Report not found.' ];
        }

        // Apply overrides if any.
        $overrides_json = get_post_meta( $post_id, '_wham_report_overrides', true );
        if ( $overrides_json ) {
            $report_data = json_decode( get_post_meta( $post_id, '_wham_report_data', true ), true );
            $overrides   = json_decode( $overrides_json, true );

            if ( is_array( $overrides ) && is_array( $report_data ) ) {
                if ( ! empty( $overrides['executive_summary'] ) ) {
                    $report_data['insights']['executive_summary'] = $overrides['executive_summary'];
                }
                if ( ! empty( $overrides['wins'] ) ) {
                    $report_data['insights']['wins'] = $overrides['wins'];
                }
                if ( ! empty( $overrides['watch_items'] ) ) {
                    $report_data['insights']['watch_items'] = $overrides['watch_items'];
                }
                if ( ! empty( $overrides['recommendations'] ) ) {
                    $report_data['insights']['recommendations'] = $overrides['recommendations'];
                }

                update_post_meta( $post_id, '_wham_report_data', wp_json_encode( $report_data ) );

                // Re-generate PDFs with updated data.
                $this->pdf->generate_all_styles( $report_data, $post_id );
            }
        }

        wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
        $this->log( "Report {$post_id} finalized (published)." );

        if ( $send_email ) {
            $this->send_report_email( $post_id );
        }

        return [ 'status' => 'success', 'report_id' => $post_id ];
    }

    /**
     * Simple logging to error log.
     */
    private function log( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[WHAM Reports] ' . $message );
        }
    }
}
