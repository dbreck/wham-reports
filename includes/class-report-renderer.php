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

		$user     = wp_get_current_user();
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

		// Admin client-switcher: determine selected client filter.
		$selected_client = '';
		if ( $is_admin && isset( $_GET['client'] ) ) {
			$selected_client = sanitize_text_field( wp_unslash( $_GET['client'] ) );
		}

		// Build clients list for admin switcher.
		$clients = [];
		if ( $is_admin ) {
			$client_map = \WHAM_Reports::get_client_map();
			foreach ( $client_map as $mid => $info ) {
				$clients[ $mid ] = $info['client_name'] ?? $mid;
			}
			asort( $clients );
		}

		return $this->render_report_list( $client_id, $is_admin, $selected_client, $clients );
	}

	/**
	 * Render list of reports for the client.
	 */
	private function render_report_list( string $client_id, bool $is_admin, string $selected_client = '', array $clients = [] ): string {
		$args = [
			'post_type'      => 'wham_report',
			'posts_per_page' => 24,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $is_admin && $selected_client ) {
			// Admin filtering by a specific client.
			$args['meta_query'] = [
				[ 'key' => '_wham_client_id', 'value' => $selected_client ],
			];
		} elseif ( ! $is_admin && $client_id ) {
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
			<div class="wham-login-icon">
				<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#1a2332" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
			</div>
			<h2>Client Portal</h2>
			<p>Log in to access your monthly reports and analytics.</p>
			<a href="' . esc_url( $login_url ) . '" class="wham-btn wham-btn-primary">Log In</a>
		</div>';
	}
}
