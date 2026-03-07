<?php
/**
 * Client Dashboard — Single Report Detail View
 *
 * Variables: $report_post (WP_Post), $report_data (array), $pdf_url (string), $is_admin (bool)
 */
defined( 'ABSPATH' ) || exit;

$client       = $report_data['client'] ?? [];
$maintenance  = $report_data['maintenance'] ?? [];
$search       = $report_data['search'] ?? [];
$analytics    = $report_data['analytics'] ?? [];
$dev_hours    = $report_data['dev_hours'] ?? [];
$period_label = $report_data['period_label'] ?? '';
$tier         = $report_data['tier'] ?? 'basic';

$back_url = remove_query_arg( 'report' );

// Chart file path to URL helper.
$chart_to_url = function( $path ) {
	if ( empty( $path ) || ! file_exists( $path ) ) {
		return '';
	}
	$upload_dir = wp_get_upload_dir();
	return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $path );
};

$insights = $report_data['insights'] ?? [];
$charts   = $report_data['charts'] ?? [];

// Comparison helper.
$render_change = function( $current, $previous, $format = 'number', $invert = false ) {
	if ( ! is_numeric( $current ) || ! is_numeric( $previous ) || 0 == $previous ) {
		return '';
	}
	$diff    = $current - $previous;
	$pct     = round( ( $diff / abs( $previous ) ) * 100, 1 );
	$is_pos  = $diff > 0;
	$is_good = $invert ? ! $is_pos : $is_pos;
	$arrow   = $is_pos ? '&#9650;' : '&#9660;';
	$class   = $is_good ? 'wham-change-up' : 'wham-change-down';
	if ( abs( $pct ) < 1 ) {
		$class = 'wham-change-flat';
		$arrow = '&#9644;';
	}
	return '<span class="wham-change ' . $class . '">' . $arrow . ' ' . abs( $pct ) . '%</span>';
};
?>
<div class="wham-dashboard wham-detail">

	<div class="wham-detail-header">
		<div class="wham-detail-header-left">
			<a href="<?php echo esc_url( $back_url ); ?>" class="wham-back-link">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
				All Reports
			</a>
			<h1><?php echo esc_html( $period_label ); ?></h1>
			<p class="wham-dash-subtitle">
				<?php echo esc_html( $client['name'] ?? '' ); ?>
				<span class="wham-report-tier wham-tier-<?php echo esc_attr( sanitize_html_class( $tier ) ); ?>"><?php echo esc_html( ucfirst( $tier ) ); ?></span>
			</p>
		</div>
		<div class="wham-detail-header-right">
			<?php if ( $pdf_url ) : ?>
				<a href="<?php echo esc_url( $pdf_url ); ?>" class="wham-btn wham-btn-primary" target="_blank">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:6px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
					Download PDF
				</a>
			<?php endif; ?>
		</div>
	</div>

	<!-- Executive Summary & Health Scores -->
	<?php if ( ! empty( $insights ) ) : ?>
	<div class="wham-dash-section">
		<div class="wham-section-header">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1a2332" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
			<h3>Report Summary</h3>
		</div>

		<?php if ( ! empty( $insights['health_scores'] ) ) : ?>
		<div class="wham-health-grid">
			<?php
			$score_labels = [
				'security'  => 'Security',
				'seo'       => 'SEO',
				'traffic'   => 'Traffic',
				'dev_hours' => 'Dev Hours',
			];
			$score_status = [
				'green' => 'Healthy',
				'amber' => 'Monitor',
				'red'   => 'Needs Attention',
			];
			foreach ( $insights['health_scores'] as $key => $score ) :
				if ( empty( $score_labels[ $key ] ) ) continue;
			?>
			<div class="wham-health-card wham-health-<?php echo esc_attr( $score ); ?>">
				<div class="wham-health-label"><?php echo esc_html( $score_labels[ $key ] ); ?></div>
				<div class="wham-health-status"><?php echo esc_html( $score_status[ $score ] ?? ucfirst( $score ) ); ?></div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $insights['executive_summary'] ) ) : ?>
		<div class="wham-exec-summary"><?php echo esc_html( $insights['executive_summary'] ); ?></div>
		<?php endif; ?>

		<?php if ( ! empty( $insights['wins'] ) ) : ?>
		<h4>Wins This Month</h4>
		<ul class="wham-insights-list wham-wins-list">
			<?php foreach ( $insights['wins'] as $win ) : ?>
			<li><?php echo esc_html( $win ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>

		<?php if ( ! empty( $insights['watch_items'] ) ) : ?>
		<h4>Areas to Watch</h4>
		<ul class="wham-insights-list wham-watch-list">
			<?php foreach ( $insights['watch_items'] as $item ) : ?>
			<li><?php echo esc_html( $item ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- Maintenance Section -->
	<div class="wham-dash-section">
		<div class="wham-section-header">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1a2332" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
			<h3>Updates &amp; Maintenance</h3>
		</div>
		<?php if ( ! empty( $maintenance['error'] ) ) : ?>
			<p class="wham-dash-muted"><?php echo esc_html( $maintenance['error'] ); ?></p>
		<?php else : ?>
		<div class="wham-metric-grid wham-metric-grid-4">
			<div class="wham-metric wham-metric-blue">
				<span class="wham-metric-val"><?php echo esc_html( $maintenance['wp_version'] ?? 'N/A' ); ?></span>
				<span class="wham-metric-lbl">WordPress</span>
			</div>
			<div class="wham-metric wham-metric-green">
				<span class="wham-metric-val"><?php echo esc_html( ( $maintenance['plugins_updated'] ?? 0 ) . '/' . ( $maintenance['plugins_total'] ?? 0 ) ); ?></span>
				<span class="wham-metric-lbl">Plugins Updated</span>
			</div>
			<div class="wham-metric wham-metric-purple">
				<span class="wham-metric-val"><?php echo esc_html( $maintenance['php_version'] ?? 'N/A' ); ?></span>
				<span class="wham-metric-lbl">PHP Version</span>
			</div>
			<div class="wham-metric">
				<span class="wham-metric-val"><?php echo esc_html( $maintenance['theme_name'] ?? 'N/A' ); ?></span>
				<span class="wham-metric-sub"><?php echo esc_html( $maintenance['theme_version'] ?? '' ); ?></span>
				<span class="wham-metric-lbl">Theme</span>
			</div>
		</div>

		<?php if ( $tier !== 'basic' && ! empty( $maintenance['plugin_details'] ) ) : ?>
		<h4>Plugin Updates</h4>
		<div class="wham-table-wrap">
			<table class="wham-dash-table">
				<thead><tr><th>Plugin</th><th>Version</th><th>Status</th></tr></thead>
				<tbody>
				<?php foreach ( $maintenance['plugin_details'] as $plugin ) : ?>
					<tr>
						<td><?php echo esc_html( $plugin['name'] ?? '' ); ?></td>
						<td><code><?php echo esc_html( $plugin['version'] ?? '' ); ?></code></td>
						<td>
							<?php if ( ! empty( $plugin['update_available'] ) ) : ?>
								<span class="wham-status-badge wham-status-warning">Update Available</span>
							<?php else : ?>
								<span class="wham-status-badge wham-status-good">Up to Date</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
		<?php endif; ?>
	</div>

	<!-- Search Console Section -->
	<?php
	$search_source = $search['source'] ?? '';
	if ( $search_source !== 'skipped' && $search_source !== 'not_configured' ) :
		$prev_search = $search['previous_period'] ?? [];
	?>
	<div class="wham-dash-section">
		<div class="wham-section-header">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1a2332" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			<h3>Search Performance</h3>
		</div>
		<?php if ( ! empty( $search['error'] ) ) : ?>
			<p class="wham-dash-muted"><?php echo esc_html( $search['error'] ); ?></p>
		<?php else : ?>
		<div class="wham-metric-grid wham-metric-grid-4">
			<div class="wham-metric wham-metric-blue">
				<span class="wham-metric-val"><?php echo esc_html( number_format( $search['clicks'] ?? 0 ) ); ?></span>
				<?php echo $render_change( $search['clicks'] ?? 0, $prev_search['clicks'] ?? null ); ?>
				<span class="wham-metric-lbl">Clicks</span>
			</div>
			<div class="wham-metric wham-metric-green">
				<span class="wham-metric-val"><?php echo esc_html( number_format( $search['impressions'] ?? 0 ) ); ?></span>
				<?php echo $render_change( $search['impressions'] ?? 0, $prev_search['impressions'] ?? null ); ?>
				<span class="wham-metric-lbl">Impressions</span>
			</div>
			<div class="wham-metric wham-metric-amber">
				<span class="wham-metric-val"><?php echo esc_html( $search['ctr'] ?? 0 ); ?>%</span>
				<?php echo $render_change( $search['ctr'] ?? 0, $prev_search['ctr'] ?? null ); ?>
				<span class="wham-metric-lbl">CTR</span>
			</div>
			<div class="wham-metric wham-metric-purple">
				<span class="wham-metric-val"><?php echo esc_html( $search['position'] ?? 0 ); ?></span>
				<?php echo $render_change( $search['position'] ?? 0, $prev_search['position'] ?? null, 'number', true ); ?>
				<span class="wham-metric-lbl">Avg Position</span>
			</div>
		</div>

		<?php
		$gsc_chart_url = $chart_to_url( $charts['gsc_trend'] ?? '' );
		if ( $gsc_chart_url ) : ?>
		<div class="wham-chart-wrap">
			<img src="<?php echo esc_url( $gsc_chart_url ); ?>" alt="Search performance trend" class="wham-chart-img">
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $search['top_queries'] ) ) : ?>
		<h4>Top Search Queries</h4>
		<div class="wham-table-wrap">
			<table class="wham-dash-table">
				<thead><tr><th>Query</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Position</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $search['top_queries'], 0, 10 ) as $q ) : ?>
					<tr>
						<td class="wham-td-query"><?php echo esc_html( $q['query'] ?? '' ); ?></td>
						<td><?php echo esc_html( $q['clicks'] ?? 0 ); ?></td>
						<td><?php echo esc_html( number_format( $q['impressions'] ?? 0 ) ); ?></td>
						<td><?php echo esc_html( $q['ctr'] ?? '' ); ?>%</td>
						<td><?php echo esc_html( $q['position'] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $search['top_pages'] ) ) : ?>
		<h4>Top Pages</h4>
		<div class="wham-table-wrap">
			<table class="wham-dash-table">
				<thead><tr><th>Page</th><th>Clicks</th><th>Impressions</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $search['top_pages'], 0, 10 ) as $p ) : ?>
					<tr>
						<td class="wham-td-query"><?php echo esc_html( $p['page'] ?? '' ); ?></td>
						<td><?php echo esc_html( $p['clicks'] ?? 0 ); ?></td>
						<td><?php echo esc_html( number_format( $p['impressions'] ?? 0 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- GA4 Analytics Section -->
	<?php
	$analytics_source = $analytics['source'] ?? '';
	if ( $analytics_source !== 'skipped' && $analytics_source !== 'error' ) :
		$prev_analytics = $analytics['previous_period'] ?? [];
	?>
	<div class="wham-dash-section">
		<div class="wham-section-header">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1a2332" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
			<h3>Website Analytics</h3>
		</div>
		<div class="wham-metric-grid wham-metric-grid-4">
			<div class="wham-metric wham-metric-blue">
				<span class="wham-metric-val"><?php echo esc_html( number_format( $analytics['sessions'] ?? 0 ) ); ?></span>
				<?php echo $render_change( $analytics['sessions'] ?? 0, $prev_analytics['sessions'] ?? null ); ?>
				<span class="wham-metric-lbl">Sessions</span>
			</div>
			<div class="wham-metric wham-metric-green">
				<span class="wham-metric-val"><?php echo esc_html( number_format( $analytics['users'] ?? 0 ) ); ?></span>
				<?php echo $render_change( $analytics['users'] ?? 0, $prev_analytics['users'] ?? null ); ?>
				<span class="wham-metric-lbl">Users</span>
			</div>
			<div class="wham-metric wham-metric-purple">
				<span class="wham-metric-val"><?php echo esc_html( number_format( $analytics['pageviews'] ?? 0 ) ); ?></span>
				<?php echo $render_change( $analytics['pageviews'] ?? 0, $prev_analytics['pageviews'] ?? null ); ?>
				<span class="wham-metric-lbl">Pageviews</span>
			</div>
			<div class="wham-metric wham-metric-amber">
				<span class="wham-metric-val"><?php echo esc_html( $analytics['bounce_rate'] ?? 0 ); ?>%</span>
				<?php echo $render_change( $analytics['bounce_rate'] ?? 0, $prev_analytics['bounce_rate'] ?? null, 'number', true ); ?>
				<span class="wham-metric-lbl">Bounce Rate</span>
			</div>
		</div>

		<?php
		$ga4_sources_url = $chart_to_url( $charts['ga4_sources'] ?? '' );
		$ga4_trend_url = $chart_to_url( $charts['ga4_trend'] ?? '' );
		if ( $ga4_sources_url ) : ?>
		<div class="wham-chart-wrap">
			<img src="<?php echo esc_url( $ga4_sources_url ); ?>" alt="Traffic sources" class="wham-chart-img">
		</div>
		<?php endif; ?>
		<?php if ( $ga4_trend_url ) : ?>
		<div class="wham-chart-wrap">
			<img src="<?php echo esc_url( $ga4_trend_url ); ?>" alt="Sessions trend" class="wham-chart-img">
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $analytics['traffic_sources'] ) ) : ?>
		<h4>Traffic Sources</h4>
		<div class="wham-table-wrap">
			<table class="wham-dash-table">
				<thead><tr><th>Source</th><th>Sessions</th><th>Users</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $analytics['traffic_sources'], 0, 8 ) as $src ) : ?>
					<tr>
						<td><?php echo esc_html( $src['source'] ?? $src['channel'] ?? '' ); ?></td>
						<td><?php echo esc_html( number_format( $src['sessions'] ?? 0 ) ); ?></td>
						<td><?php echo esc_html( number_format( $src['users'] ?? 0 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $analytics['top_pages'] ) ) : ?>
		<h4>Top Landing Pages</h4>
		<div class="wham-table-wrap">
			<table class="wham-dash-table">
				<thead><tr><th>Page</th><th>Views</th><th>Sessions</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $analytics['top_pages'], 0, 8 ) as $pg ) : ?>
					<tr>
						<td class="wham-td-query"><?php echo esc_html( $pg['page'] ?? $pg['path'] ?? '' ); ?></td>
						<td><?php echo esc_html( number_format( $pg['views'] ?? $pg['pageviews'] ?? 0 ) ); ?></td>
						<td><?php echo esc_html( number_format( $pg['sessions'] ?? 0 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- Dev Hours Section -->
	<div class="wham-dash-section">
		<div class="wham-section-header">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1a2332" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
			<h3>Development Hours</h3>
		</div>
		<?php if ( ! empty( $dev_hours['error'] ) ) : ?>
			<p class="wham-dash-muted"><?php echo esc_html( $dev_hours['error'] ); ?></p>
		<?php else : ?>
		<?php
			$hrs_incl = $dev_hours['hours_included'] ?? 0;
			$hrs_used = $dev_hours['hours_used'] ?? 0;
			$hrs_rem  = $dev_hours['hours_remaining'] ?? 0;
			$pct      = $hrs_incl > 0 ? min( 100, round( ( $hrs_used / $hrs_incl ) * 100 ) ) : 0;

			// Color coding: green < 60%, amber 60-85%, red > 85%.
			$bar_class = 'wham-bar-green';
			if ( $pct >= 85 ) {
				$bar_class = 'wham-bar-red';
			} elseif ( $pct >= 60 ) {
				$bar_class = 'wham-bar-amber';
			}
		?>
		<div class="wham-hours-display">
			<div class="wham-hours-stats">
				<div class="wham-hours-stat">
					<span class="wham-hours-stat-val"><?php echo esc_html( $hrs_used ); ?></span>
					<span class="wham-hours-stat-lbl">Used</span>
				</div>
				<div class="wham-hours-stat">
					<span class="wham-hours-stat-val"><?php echo esc_html( $hrs_incl ); ?></span>
					<span class="wham-hours-stat-lbl">Included</span>
				</div>
				<div class="wham-hours-stat">
					<span class="wham-hours-stat-val"><?php echo esc_html( $hrs_rem ); ?></span>
					<span class="wham-hours-stat-lbl">Remaining</span>
				</div>
			</div>
			<div class="wham-hours-bar-wrap">
				<div class="wham-hours-bar">
					<div class="wham-hours-fill <?php echo esc_attr( $bar_class ); ?>" style="width:<?php echo intval( $pct ); ?>%;"></div>
				</div>
				<span class="wham-hours-pct"><?php echo esc_html( $pct ); ?>% used</span>
			</div>
		</div>

		<?php
		$hours_chart_url = $chart_to_url( $charts['dev_hours'] ?? '' );
		if ( $hours_chart_url ) : ?>
		<div class="wham-chart-wrap">
			<img src="<?php echo esc_url( $hours_chart_url ); ?>" alt="Hours breakdown" class="wham-chart-img" style="max-width:300px;">
		</div>
		<?php endif; ?>

		<?php if ( $dev_hours['work_summary'] ?? '' ) : ?>
			<div class="wham-work-summary">
				<strong>Work performed:</strong>
				<?php echo esc_html( $dev_hours['work_summary'] ); ?>
			</div>
		<?php endif; ?>
		<?php endif; ?>
	</div>

	<!-- Recommendations -->
	<?php if ( ! empty( $insights['recommendations'] ) ) : ?>
	<div class="wham-dash-section">
		<div class="wham-section-header">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1a2332" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
			<h3>Recommendations</h3>
		</div>
		<?php foreach ( $insights['recommendations'] as $i => $rec ) : ?>
		<div class="wham-recommendation">
			<span class="wham-recommendation-num"><?php echo (int) $i + 1; ?></span>
			<span class="wham-recommendation-title"><?php echo esc_html( $rec['title'] ?? '' ); ?></span>
			<?php if ( ! empty( $rec['rationale'] ) ) : ?>
			<p class="wham-recommendation-rationale"><?php echo esc_html( $rec['rationale'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $rec['impact'] ) ) : ?>
			<p class="wham-recommendation-impact">Expected impact: <?php echo esc_html( $rec['impact'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

</div>
