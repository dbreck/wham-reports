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

    /** @var string[] Available style variants for professional/premium tier. */
    private const STYLES = [ 'editorial', 'modern', 'swiss' ];

    /**
     * Generate a PDF report in a specific style and attach it to a wham_report post.
     *
     * @param array  $report_data  The collected report data.
     * @param int    $post_id      The wham_report post ID.
     * @param string $style        Style variant: 'editorial', 'modern', 'swiss', or '' for auto.
     * @return string|null  The PDF URL on success, null on failure.
     */
    public function generate( array $report_data, int $post_id, string $style = '' ): ?string {
        $tier = $report_data['tier'] ?? 'basic';

        // Render HTML from template.
        $html = $this->render_template( $report_data, $tier, $style );
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
        $style_suffix = $style ? "-{$style}" : '';
        $filename    = "WHAM-Report-{$client_slug}-{$period}{$style_suffix}.pdf";
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
            if ( $style ) {
                update_post_meta( $post_id, "_wham_pdf_attachment_id_{$style}", $attachment_id );
            } else {
                update_post_meta( $post_id, '_wham_pdf_attachment_id', $attachment_id );
            }
        }

        return $pdf_url;
    }

    /**
     * Generate all 3 style variants for professional/premium tier reports.
     *
     * @param array $report_data  The collected report data.
     * @param int   $post_id      The wham_report post ID.
     * @return array  Associative array of style => URL.
     */
    public function generate_all_styles( array $report_data, int $post_id ): array {
        $tier = $report_data['tier'] ?? 'basic';
        $urls = [];

        if ( $tier === 'basic' ) {
            // Basic tier: single PDF, no style variants.
            $url = $this->generate( $report_data, $post_id );
            if ( $url ) {
                update_post_meta( $post_id, '_wham_pdf_url', $url );
                $urls['default'] = $url;
            }
            return $urls;
        }

        // Professional/Premium: generate all 3 styles.
        foreach ( self::STYLES as $style ) {
            $url = $this->generate( $report_data, $post_id, $style );
            if ( $url ) {
                update_post_meta( $post_id, "_wham_pdf_url_{$style}", $url );
                $urls[ $style ] = $url;
                $this->log( "  → {$style} PDF generated: {$url}" );
            } else {
                $this->log( "  → {$style} PDF generation failed." );
            }
        }

        // Backward compat: _wham_pdf_url points to editorial (first/default).
        if ( ! empty( $urls['editorial'] ) ) {
            update_post_meta( $post_id, '_wham_pdf_url', $urls['editorial'] );
        } elseif ( ! empty( $urls ) ) {
            update_post_meta( $post_id, '_wham_pdf_url', reset( $urls ) );
        }

        return $urls;
    }

    /**
     * Render the HTML template for a report.
     */
    private function render_template( array $data, string $tier, string $style = '' ): string {
        if ( $tier === 'basic' ) {
            $template_file = 'report-basic.php';
        } elseif ( $style && in_array( $style, self::STYLES, true ) ) {
            $template_file = "report-{$style}.php";
        } else {
            // Fallback to professional template.
            $template_file = 'report-professional.php';
        }

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
                    $this->log( 'DomPDF: Starting render (' . strlen( $html ) . ' bytes HTML).' );

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

                    $output = $dompdf->output();
                    $this->log( 'DomPDF: Success (' . strlen( $output ) . ' bytes PDF).' );
                    return $output;
                } catch ( \Throwable $e ) {
                    $this->log( 'DomPDF error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
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
        $log_file = ( defined( 'WHAM_REPORTS_PATH' ) ? WHAM_REPORTS_PATH : __DIR__ . '/../' ) . 'pdf-debug.log';
        file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . '] ' . $message . "\n", FILE_APPEND );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[WHAM PDF] ' . $message );
        }
    }
}
