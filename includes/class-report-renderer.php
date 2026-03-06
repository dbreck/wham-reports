<?php
namespace WHAM_Reports;

defined( 'ABSPATH' ) || exit;

/**
 * Report Renderer — Handles the client-facing dashboard shortcode.
 *
 * Usage: Place [wham_dashboard] on any page. Logged-in wham_client users
 * see their reports; admins see all reports.
 */
class Report_Renderer {

    /**
     * Render the client dashboard.
     */
    public function render_dashboard(): string {
        if ( ! is_user_logged_in() ) {
            return $this->render_login_prompt();
        }

        $user    = wp_get_current_user();
        $is_admin = current_user_can( 'manage_options' );

        // Determine which client(s) this user can see.
        $client_id = get_user_meta( $user->ID, '_wham_monday_client_id', true );

        if ( ! $is_admin && empty( $client_id ) ) {
            return '<div class="wham-dash-notice">Your account is not linked to a client. Please contact your account manager.</div>';
        }

        // Load CSS.
        wp_enqueue_style(
            'wham-dashboard',
            WHAM_REPORTS_URL . 'assets/css/dashboard.css',
            [],
            WHAM_REPORTS_VERSION
        );

        // Check if viewing a specific report.
        $report_id = absint( $_GET['report'] ?? 0 );
        if ( $report_id ) {
            return $this->render_single_report( $report_id, $client_id, $is_admin );
        }

        return $this->render_report_list( $client_id, $is_admin );
    }

    /**
     * Render list of reports for the client.
     */
    private function render_report_list( string $client_id, bool $is_admin ): string {
        $args = [
            'post_type'      => 'wham_report',
            'posts_per_page' => 12,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( ! $is_admin && $client_id ) {
            $args['meta_query'] = [
                [ 'key' => '_wham_client_id', 'value' => $client_id ],
            ];
        }

        $reports = get_posts( $args );

        ob_start();
        include WHAM_REPORTS_PATH . 'templates/dashboard/client-dashboard.php';
        return ob_get_clean();
    }

    /**
     * Render a single report detail view.
     */
    private function render_single_report( int $report_id, string $client_id, bool $is_admin ): string {
        $report_post = get_post( $report_id );

        if ( ! $report_post || $report_post->post_type !== 'wham_report' ) {
            return '<div class="wham-dash-notice">Report not found.</div>';
        }

        // Access check: admin or matching client.
        $report_client_id = get_post_meta( $report_id, '_wham_client_id', true );
        if ( ! $is_admin && $report_client_id !== $client_id ) {
            return '<div class="wham-dash-notice">You do not have access to this report.</div>';
        }

        $report_data = json_decode( get_post_meta( $report_id, '_wham_report_data', true ), true );
        $pdf_url     = get_post_meta( $report_id, '_wham_pdf_url', true );

        ob_start();
        include WHAM_REPORTS_PATH . 'templates/dashboard/report-detail.php';
        return ob_get_clean();
    }

    /**
     * Render login prompt.
     */
    private function render_login_prompt(): string {
        $login_url = wp_login_url( get_permalink() );
        return '<div class="wham-dash-login">
            <h2>Client Portal</h2>
            <p>Please log in to view your reports.</p>
            <a href="' . esc_url( $login_url ) . '" class="wham-btn">Log In</a>
        </div>';
    }
}
