<?php
/**
 * Client Dashboard — Report List View
 *
 * Variables: $reports (array of WP_Post), $client_id, $is_admin, $selected_client, $clients
 */
defined( 'ABSPATH' ) || exit;

$user = wp_get_current_user();

// Determine display name.
$display_name = '';
if ( $is_admin && $selected_client && isset( $clients[ $selected_client ] ) ) {
	$display_name = $clients[ $selected_client ];
} elseif ( ! empty( $reports ) ) {
	$display_name = get_post_meta( $reports[0]->ID, '_wham_client_name', true );
}
if ( ! $display_name ) {
	$display_name = $user->display_name;
}

// Summary stats.
$total_reports  = count( $reports );
$latest_date    = '';
if ( ! empty( $reports ) ) {
	$latest_period = get_post_meta( $reports[0]->ID, '_wham_period', true );
	if ( $latest_period ) {
		$latest_date = date( 'F Y', strtotime( $latest_period . '-01' ) );
	}
}

$base_url = get_permalink();
?>
<div class="wham-dashboard">

	<div class="wham-dash-header">
		<div class="wham-dash-header-left">
			<h1>Your Reports</h1>
			<p class="wham-dash-subtitle">Welcome back, <?php echo esc_html( $display_name ); ?></p>
		</div>
		<div class="wham-dash-brand">
			<strong>WHAM</strong>
			<span>Web Hosting &amp; Maintenance</span>
		</div>
	</div>

	<?php if ( $is_admin && ! empty( $clients ) ) : ?>
	<div class="wham-client-switcher">
		<div class="wham-switcher-inner">
			<label for="wham-client-select">View reports for:</label>
			<select id="wham-client-select" onchange="if(this.value){window.location='<?php echo esc_url( add_query_arg( 'client', '__CID__', $base_url ) ); ?>'.replace('__CID__',this.value)}else{window.location='<?php echo esc_url( $base_url ); ?>'}">
				<option value="">All Clients</option>
				<?php foreach ( $clients as $mid => $cname ) : ?>
					<option value="<?php echo esc_attr( $mid ); ?>" <?php selected( $selected_client, $mid ); ?>><?php echo esc_html( $cname ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php if ( $selected_client && isset( $clients[ $selected_client ] ) ) : ?>
			<div class="wham-viewing-as">
				Viewing as: <strong><?php echo esc_html( $clients[ $selected_client ] ); ?></strong>
				<a href="<?php echo esc_url( $base_url ); ?>" class="wham-clear-filter">Clear filter</a>
			</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( $total_reports > 0 ) : ?>
	<div class="wham-summary-bar">
		<div class="wham-summary-stat">
			<span class="wham-summary-val"><?php echo esc_html( $total_reports ); ?></span>
			<span class="wham-summary-lbl"><?php echo $total_reports === 1 ? 'Report' : 'Reports'; ?></span>
		</div>
		<?php if ( $latest_date ) : ?>
		<div class="wham-summary-stat">
			<span class="wham-summary-val"><?php echo esc_html( $latest_date ); ?></span>
			<span class="wham-summary-lbl">Most Recent</span>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( empty( $reports ) ) : ?>
		<div class="wham-dash-empty">
			<div class="wham-empty-icon">
				<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
			</div>
			<h4>No reports yet</h4>
			<p>Your first report will appear here once it has been generated.</p>
		</div>
	<?php else : ?>

	<div class="wham-report-grid">
		<?php foreach ( $reports as $rpt ) :
			$period       = get_post_meta( $rpt->ID, '_wham_period', true );
			$period_label = $period ? date( 'F Y', strtotime( $period . '-01' ) ) : '';
			$period_month = $period ? date( 'F', strtotime( $period . '-01' ) ) : '';
			$period_year  = $period ? date( 'Y', strtotime( $period . '-01' ) ) : '';
			$tier         = get_post_meta( $rpt->ID, '_wham_tier', true );
			$pdf_url      = get_post_meta( $rpt->ID, '_wham_pdf_url', true );
			if ( ! $pdf_url ) {
				$pdf_url = get_post_meta( $rpt->ID, '_wham_pdf_url_swiss', true );
			}
			$rpt_client   = get_post_meta( $rpt->ID, '_wham_client_name', true );
			$generated    = get_the_date( 'M j, Y', $rpt );
			$detail_url   = add_query_arg( 'report', $rpt->ID, $base_url );

			// Preserve client filter in detail link.
			if ( $selected_client ) {
				$detail_url = add_query_arg( 'client', $selected_client, $detail_url );
			}

			$tier_class = 'wham-tier-' . sanitize_html_class( $tier );
		?>
		<div class="wham-report-card">
			<div class="wham-report-card-header">
				<div class="wham-report-period-wrap">
					<span class="wham-report-month"><?php echo esc_html( $period_month ); ?></span>
					<span class="wham-report-year"><?php echo esc_html( $period_year ); ?></span>
				</div>
				<span class="wham-report-tier <?php echo esc_attr( $tier_class ); ?>"><?php echo esc_html( ucfirst( $tier ) ); ?></span>
			</div>
			<?php if ( $is_admin && $rpt_client ) : ?>
				<div class="wham-report-client"><?php echo esc_html( $rpt_client ); ?></div>
			<?php endif; ?>
			<div class="wham-report-generated">Generated <?php echo esc_html( $generated ); ?></div>
			<div class="wham-report-card-actions">
				<a href="<?php echo esc_url( $detail_url ); ?>" class="wham-btn wham-btn-primary wham-btn-sm">View Report</a>
				<?php if ( $pdf_url ) : ?>
					<a href="<?php echo esc_url( $pdf_url ); ?>" class="wham-btn wham-btn-outline wham-btn-sm" target="_blank">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
						PDF
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<?php endif; ?>

</div>
