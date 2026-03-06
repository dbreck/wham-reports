<?php
namespace WHAM_Reports;

defined( 'ABSPATH' ) || exit;

require_once WHAM_REPORTS_PATH . 'includes/class-mainwp-source.php';
require_once WHAM_REPORTS_PATH . 'includes/class-gsc-source.php';
require_once WHAM_REPORTS_PATH . 'includes/class-ga4-source.php';
require_once WHAM_REPORTS_PATH . 'includes/class-monday-source.php';
require_once WHAM_REPORTS_PATH . 'includes/class-pdf-generator.php';

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
    public function generate_all_reports(): array {
        $client_map = \WHAM_Reports::get_client_map();
        $results    = [];
        $period     = date( 'Y-m' );

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

        // ── Create Report Post ────────────────────────────────────────

        $title = sprintf( '%s — %s', $client_name, date( 'F Y', strtotime( $period . '-01' ) ) );

        $post_id = wp_insert_post([
            'post_type'   => 'wham_report',
            'post_title'  => $title,
            'post_status' => 'publish',
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

        // ── Generate PDF ──────────────────────────────────────────────

        $pdf_url = $this->pdf->generate( $report_data, $post_id );
        if ( $pdf_url ) {
            update_post_meta( $post_id, '_wham_pdf_url', $pdf_url );
            $this->log( "  → PDF generated: {$pdf_url}" );
        } else {
            $this->log( '  → PDF generation failed.' );
        }

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
            // Update Monday.com status.
            $report_data = json_decode( get_post_meta( $report_id, '_wham_report_data', true ), true );
            $subitem_id  = $report_data['dev_hours']['subitem_id'] ?? '';
            if ( $subitem_id ) {
                $this->monday->update_report_status( $subitem_id, 'Sent', date( 'Y-m-d' ) );
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
     * Simple logging to error log.
     */
    private function log( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[WHAM Reports] ' . $message );
        }
    }
}
