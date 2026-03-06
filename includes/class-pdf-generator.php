<?php
namespace WHAM_Reports;

defined( 'ABSPATH' ) || exit;

/**
 * PDF Generator — Renders report data into branded PDF files.
 *
 * Strategy: Renders PHP template to HTML, then converts to PDF.
 * Primary: DomPDF (pure PHP, zero system deps, Composer-free bundled version).
 * The HTML template is self-contained with inline styles for reliable rendering.
 */
class PDF_Generator {

    /**
     * Generate a PDF report and attach it to a wham_report post.
     *
     * @param array $report_data  The collected report data.
     * @param int   $post_id      The wham_report post ID.
     * @return string|null  The PDF URL on success, null on failure.
     */
    public function generate( array $report_data, int $post_id ): ?string {
        $tier = $report_data['tier'] ?? 'basic';

        // Render HTML from template.
        $html = $this->render_template( $report_data, $tier );
        if ( empty( $html ) ) {
            $this->log( 'PDF template rendered empty HTML.' );
            return null;
        }

        // Generate PDF bytes.
        $pdf_bytes = $this->html_to_pdf( $html );
        if ( ! $pdf_bytes ) {
            $this->log( 'PDF conversion failed.' );
            return null;
        }

        // Save to uploads directory.
        $upload_dir = wp_get_upload_dir();
        $pdf_dir    = $upload_dir['basedir'] . '/wham-reports/' . date( 'Y' );

        if ( ! file_exists( $pdf_dir ) ) {
            wp_mkdir_p( $pdf_dir );
        }

        $client_slug = sanitize_title( $report_data['client']['name'] ?? 'unknown' );
        $period      = $report_data['period'] ?? date( 'Y-m' );
        $filename    = "WHAM-Report-{$client_slug}-{$period}.pdf";
        $filepath    = $pdf_dir . '/' . $filename;

        $written = file_put_contents( $filepath, $pdf_bytes );
        if ( ! $written ) {
            $this->log( "Failed to write PDF: {$filepath}" );
            return null;
        }

        // Build URL.
        $pdf_url = $upload_dir['baseurl'] . '/wham-reports/' . date( 'Y' ) . '/' . $filename;

        // Attach to media library.
        $attachment_id = wp_insert_attachment([
            'post_title'     => $filename,
            'post_mime_type' => 'application/pdf',
            'post_status'    => 'inherit',
            'post_parent'    => $post_id,
        ], $filepath, $post_id );

        if ( ! is_wp_error( $attachment_id ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $filepath ) );
            update_post_meta( $post_id, '_wham_pdf_attachment_id', $attachment_id );
        }

        return $pdf_url;
    }

    /**
     * Render the HTML template for a report.
     */
    private function render_template( array $data, string $tier ): string {
        // Choose template by tier.
        $template_file = ( $tier === 'professional' || $tier === 'premium' )
            ? 'report-professional.php'
            : 'report-basic.php';

        $template_path = WHAM_REPORTS_PATH . 'templates/pdf/' . $template_file;

        if ( ! file_exists( $template_path ) ) {
            $this->log( "PDF template not found: {$template_path}" );
            return '';
        }

        // Extract data vars for the template.
        $report = $data;

        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    /**
     * Convert HTML string to PDF bytes.
     *
     * Uses DomPDF if available, falls back to simple HTML file if not.
     */
    private function html_to_pdf( string $html ): ?string {
        // Strategy 1: DomPDF (if composer autoload exists).
        $dompdf_autoload = WHAM_REPORTS_PATH . 'vendor/autoload.php';
        if ( file_exists( $dompdf_autoload ) ) {
            require_once $dompdf_autoload;

            if ( class_exists( '\\Dompdf\\Dompdf' ) ) {
                try {
                    $dompdf = new \Dompdf\Dompdf([
                        'isRemoteEnabled'    => true,
                        'defaultFont'        => 'sans-serif',
                        'defaultPaperSize'   => 'letter',
                        'defaultPaperOrientation' => 'portrait',
                        'isFontSubsettingEnabled' => true,
                    ]);

                    $dompdf->loadHtml( $html );
                    $dompdf->setPaper( 'letter', 'portrait' );
                    $dompdf->render();

                    return $dompdf->output();
                } catch ( \Exception $e ) {
                    $this->log( 'DomPDF error: ' . $e->getMessage() );
                }
            }
        }

        // Strategy 2: wkhtmltopdf (if installed on server).
        $wkhtmltopdf = $this->find_wkhtmltopdf();
        if ( $wkhtmltopdf ) {
            $tmp_html = tempnam( sys_get_temp_dir(), 'wham_' ) . '.html';
            $tmp_pdf  = tempnam( sys_get_temp_dir(), 'wham_' ) . '.pdf';

            file_put_contents( $tmp_html, $html );

            $cmd = escapeshellcmd( $wkhtmltopdf )
                 . ' --quiet --page-size Letter --margin-top 15mm --margin-bottom 15mm'
                 . ' --margin-left 15mm --margin-right 15mm'
                 . ' ' . escapeshellarg( $tmp_html )
                 . ' ' . escapeshellarg( $tmp_pdf );

            exec( $cmd, $output, $return_code );

            @unlink( $tmp_html );

            if ( $return_code === 0 && file_exists( $tmp_pdf ) ) {
                $pdf_bytes = file_get_contents( $tmp_pdf );
                @unlink( $tmp_pdf );
                return $pdf_bytes;
            }

            @unlink( $tmp_pdf );
            $this->log( "wkhtmltopdf failed with code {$return_code}" );
        }

        // Strategy 3: Save as HTML (last resort).
        $this->log( 'No PDF engine available. Install DomPDF via Composer or wkhtmltopdf.' );
        return null;
    }

    /**
     * Find wkhtmltopdf binary path.
     */
    private function find_wkhtmltopdf(): ?string {
        $paths = [
            '/usr/local/bin/wkhtmltopdf',
            '/usr/bin/wkhtmltopdf',
            WHAM_REPORTS_PATH . 'bin/wkhtmltopdf',
        ];

        foreach ( $paths as $path ) {
            if ( file_exists( $path ) && is_executable( $path ) ) {
                return $path;
            }
        }

        // Try which.
        $which = trim( shell_exec( 'which wkhtmltopdf 2>/dev/null' ) ?? '' );
        if ( $which && file_exists( $which ) ) {
            return $which;
        }

        return null;
    }

    private function log( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[WHAM PDF] ' . $message );
        }
    }
}
