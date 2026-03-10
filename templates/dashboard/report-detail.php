<?php
/**
 * Client Dashboard — Single Report Detail View (Swiss aesthetic)
 *
 * Variables: $report_post (WP_Post), $report_data (array), $pdf_url (string), $is_admin (bool)
 */
defined( 'ABSPATH' ) || exit;

$client       = $report_data['client'] ?? [];
$maintenance  = $report_data['maintenance'] ?? [];
$search       = $report_data['search'] ?? [];
$analytics    = $report_data['analytics'] ?? [];
$period_label = $report_data['period_label'] ?? '';
$tier         = $report_data['tier'] ?? 'basic';

$back_url = remove_query_arg( 'report' );

// Swiss-style change indicator: +/- prefix, colored text, no pill background.
$render_change = function( $current, $previous, $format = 'number', $invert = false ) {
	if ( ! is_numeric( $current ) || ! is_numeric( $previous ) || 0 == $previous ) {
		return '';
	}
	$diff    = $current - $previous;
	$pct     = round( ( $diff / abs( $previous ) ) * 100, 1 );
	$is_pos  = $diff > 0;
	$is_good = $invert ? ! $is_pos : $is_pos;
	$prefix  = $is_pos ? '+' : '-';
	$class   = $is_good ? 'wham-change-up' : 'wham-change-down';
	if ( abs( $pct ) < 1 ) {
		$class  = 'wham-change-flat';
		$prefix = '';
	}
	return '<span class="wham-change ' . $class . '">' . $prefix . abs( $pct ) . '%</span>';
};

// PDF URL (check Swiss first for backward compat with v3.0.0 reports, then default).
if ( ! $pdf_url ) {
	$pdf_url = get_post_meta( $report_post->ID, '_wham_pdf_url_swiss', true );
}
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

	<!-- Maintenance -->
	<div class="wham-dash-section">
		<div class="wham-section-header">
			<h3>Maintenance</h3>
		</div>
		<?php if ( ! empty( $maintenance['error'] ) ) : ?>
			<p class="wham-dash-muted"><?php echo esc_html( $maintenance['error'] ); ?></p>
		<?php else : ?>
		<div class="wham-metric-grid wham-metric-grid-3">
			<div class="wham-metric">
				<span class="wham-metric-val"><?php echo esc_html( $maintenance['wp_version'] ?? 'N/A' ); ?></span>
				<span class="wham-metric-lbl">WordPress</span>
			</div>
			<?php
				$p_total   = (int) ( $maintenance['plugins_total'] ?? 0 );
				$p_pending = (int) ( $maintenance['plugins_updates_count'] ?? 0 );
				$p_updated = $p_total - $p_pending;
			?>
			<div class="wham-metric">
				<span class="wham-metric-val"><?php echo esc_html( $p_updated . '/' . $p_total ); ?></span>
				<span class="wham-metric-lbl">Plugins Current</span>
			</div>
			<div class="wham-metric">
				<span class="wham-metric-val"><?php echo esc_html( $maintenance['php_version'] ?? 'N/A' ); ?></span>
				<span class="wham-metric-lbl">PHP Version</span>
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
						<td class="wham-td-mono"><?php echo esc_html( $plugin['version'] ?? '' ); ?></td>
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

	<!-- Search Performance -->
	<?php
	$search_source = $search['source'] ?? '';
	if ( $search_source !== 'skipped' && $search_source !== 'not_configured' ) :
		$search_comp = $search['comparison'] ?? [];
		$prev_search = [
			'clicks'      => $search_comp['prev_clicks'] ?? null,
			'impressions' => $search_comp['prev_impressions'] ?? null,
			'ctr'         => $search_comp['prev_ctr'] ?? null,
			'position'    => $search_comp['prev_position'] ?? null,
		];
	?>
	<div class="wham-dash-section">
		<div class="wham-section-header">
			<h3>Search Performance</h3>
		</div>
		<?php if ( ! empty( $search['error'] ) ) : ?>
			<p class="wham-dash-muted"><?php echo esc_html( $search['error'] ); ?></p>
		<?php else : ?>
		<div class="wham-metric-grid wham-metric-grid-4">
			<div class="wham-metric">
				<span class="wham-metric-val"><?php echo esc_html( number_format( $search['clicks'] ?? 0 ) ); ?></span>
				<?php echo $render_change( $search['clicks'] ?? 0, $prev_search['clicks'] ?? null ); ?>
				<span class="wham-metric-lbl">Clicks</span>
			</div>
			<div class="wham-metric">
				<span class="wham-metric-val"><?php echo esc_html( number_format( $search['impressions'] ?? 0 ) ); ?></span>
				<?php echo $render_change( $search['impressions'] ?? 0, $prev_search['impressions'] ?? null ); ?>
				<span class="wham-metric-lbl">Impressions</span>
			</div>
			<div class="wham-metric">
				<span class="wham-metric-val"><?php echo esc_html( $search['ctr'] ?? 0 ); ?>%</span>
				<?php echo $render_change( $search['ctr'] ?? 0, $prev_search['ctr'] ?? null ); ?>
				<span class="wham-metric-lbl">Avg CTR</span>
			</div>
			<div class="wham-metric">
				<span class="wham-metric-val"><?php echo esc_html( $search['position'] ?? 0 ); ?></span>
				<?php echo $render_change( $search['position'] ?? 0, $prev_search['position'] ?? null, 'number', true ); ?>
				<span class="wham-metric-lbl">Avg Position</span>
			</div>
		</div>

		<?php if ( ! empty( $search['daily_labels'] ) ) : ?>
		<h4>Search Trend</h4>
		<div class="wham-chart-wrap">
			<canvas id="wham-gsc-trend" height="280"></canvas>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $search['top_queries'] ) ) : ?>
		<h4>Search Queries</h4>
		<div class="wham-table-wrap">
			<table class="wham-dash-table">
				<thead><tr><th>Query</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Position</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $search['top_queries'], 0, 10 ) as $q ) : ?>
					<tr>
						<td class="wham-td-query"><?php echo esc_html( $q['query'] ?? '' ); ?></td>
						<td class="wham-td-mono"><?php echo esc_html( $q['clicks'] ?? 0 ); ?></td>
						<td class="wham-td-mono"><?php echo esc_html( number_format( $q['impressions'] ?? 0 ) ); ?></td>
						<td class="wham-td-mono"><?php echo esc_html( $q['ctr'] ?? '' ); ?>%</td>
						<td class="wham-td-mono"><?php echo esc_html( $q['position'] ?? '' ); ?></td>
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
						<td class="wham-td-mono"><?php echo esc_html( $p['clicks'] ?? 0 ); ?></td>
						<td class="wham-td-mono"><?php echo esc_html( number_format( $p['impressions'] ?? 0 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- Website Traffic -->
	<?php
	$analytics_source = $analytics['source'] ?? '';
	if ( $analytics_source !== 'skipped' && $analytics_source !== 'error' ) :
		$prev_analytics = [
			'sessions'    => $analytics['previous_sessions'] ?? null,
			'users'       => $analytics['previous_users'] ?? null,
			'pageviews'   => $analytics['previous_pageviews'] ?? null,
			'bounce_rate' => $analytics['previous_bounce_rate'] ?? null,
		];
	?>
	<div class="wham-dash-section">
		<div class="wham-section-header">
			<h3>Website Traffic</h3>
		</div>
		<div class="wham-metric-grid wham-metric-grid-4">
			<div class="wham-metric">
				<span class="wham-metric-val"><?php echo esc_html( number_format( $analytics['sessions'] ?? 0 ) ); ?></span>
				<?php echo $render_change( $analytics['sessions'] ?? 0, $prev_analytics['sessions'] ?? null ); ?>
				<span class="wham-metric-lbl">Sessions</span>
			</div>
			<div class="wham-metric">
				<span class="wham-metric-val"><?php echo esc_html( number_format( $analytics['users'] ?? 0 ) ); ?></span>
				<?php echo $render_change( $analytics['users'] ?? 0, $prev_analytics['users'] ?? null ); ?>
				<span class="wham-metric-lbl">Users</span>
			</div>
			<div class="wham-metric">
				<span class="wham-metric-val"><?php echo esc_html( number_format( $analytics['pageviews'] ?? 0 ) ); ?></span>
				<?php echo $render_change( $analytics['pageviews'] ?? 0, $prev_analytics['pageviews'] ?? null ); ?>
				<span class="wham-metric-lbl">Pageviews</span>
			</div>
			<div class="wham-metric">
				<span class="wham-metric-val"><?php echo esc_html( $analytics['bounce_rate'] ?? 0 ); ?>%</span>
				<?php echo $render_change( $analytics['bounce_rate'] ?? 0, $prev_analytics['bounce_rate'] ?? null, 'number', true ); ?>
				<span class="wham-metric-lbl">Bounce Rate</span>
			</div>
		</div>

		<?php if ( ! empty( $analytics['traffic_sources'] ) ) : ?>
		<h4>Traffic Sources</h4>
		<div class="wham-chart-wrap">
			<canvas id="wham-ga4-sources" height="280"></canvas>
		</div>
		<div class="wham-table-wrap">
			<table class="wham-dash-table">
				<thead><tr><th>Source</th><th>Sessions</th><th>Users</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $analytics['traffic_sources'], 0, 8 ) as $src ) : ?>
					<tr>
						<td><?php echo esc_html( $src['source'] ?? $src['channel'] ?? $src['sessionDefaultChannelGroup'] ?? '' ); ?></td>
						<td class="wham-td-mono"><?php echo esc_html( number_format( $src['sessions'] ?? $src['metric_0'] ?? 0 ) ); ?></td>
						<td class="wham-td-mono"><?php echo esc_html( number_format( $src['users'] ?? $src['metric_1'] ?? 0 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $analytics['daily_labels'] ) ) : ?>
		<h4>Sessions &amp; Users Trend</h4>
		<div class="wham-chart-wrap">
			<canvas id="wham-ga4-trend" height="280"></canvas>
		</div>
		<?php endif; ?>

		<?php
		$landing_pages = $analytics['top_pages'] ?? $analytics['top_landing_pages'] ?? [];
		if ( ! empty( $landing_pages ) ) : ?>
		<h4>Landing Pages</h4>
		<div class="wham-table-wrap">
			<table class="wham-dash-table">
				<thead><tr><th>Page</th><th>Sessions</th><th>Users</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $landing_pages, 0, 8 ) as $pg ) : ?>
					<tr>
						<?php
						$page_path = $pg['page'] ?? $pg['path'] ?? $pg['landingPagePlusQueryString'] ?? '';
						if ( strpos( $page_path, 'http' ) === 0 ) {
							$page_path = parse_url( $page_path, PHP_URL_PATH ) ?: $page_path;
						}
						if ( $page_path === '/' ) $page_path = 'Home';
						?>
						<td class="wham-td-query"><?php echo esc_html( $page_path ); ?></td>
						<td class="wham-td-mono"><?php echo esc_html( number_format( $pg['sessions'] ?? $pg['metric_0'] ?? 0 ) ); ?></td>
						<td class="wham-td-mono"><?php echo esc_html( number_format( $pg['users'] ?? $pg['metric_1'] ?? 0 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- Chart.js — muted color palette -->
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		var blue    = '#5b8def';
		var green   = '#4eba8b';
		var amber   = '#e0a458';
		var purple  = '#9b8ec4';
		var rose    = '#d47c8a';
		var teal    = '#5bb5b5';
		var slate   = '#7e8fa6';
		var peach   = '#d9967a';
		var gridColor = '#e2e8f0';
		var textColor = '#64748b';

		var axisOpts = {
			y: { beginAtZero: true, grid: { color: gridColor, drawBorder: false }, ticks: { font: { size: 11, family: "'DM Mono', monospace" }, color: textColor } },
			x: { grid: { display: false }, ticks: { maxTicksLimit: 10, font: { size: 11, family: "'DM Mono', monospace" }, color: textColor } }
		};
		var legendOpts = { position: 'top', labels: { usePointStyle: true, boxWidth: 8, font: { size: 12, family: "'DM Sans', sans-serif" }, color: '#334155', padding: 20 } };

		// GSC Trend
		(function() {
			var el = document.getElementById('wham-gsc-trend');
			if (!el) return;
			new Chart(el, {
				type: 'line',
				data: {
					labels: <?php echo wp_json_encode( $search['daily_labels'] ?? [] ); ?>,
					datasets: [
						{ label: 'Clicks', data: <?php echo wp_json_encode( $search['daily_clicks'] ?? [] ); ?>, borderColor: blue, backgroundColor: 'rgba(91,141,239,0.1)', fill: true, tension: 0.35, pointRadius: 2, pointHoverRadius: 5, borderWidth: 2.5 },
						{ label: 'Impressions', data: <?php echo wp_json_encode( $search['daily_impressions'] ?? [] ); ?>, borderColor: green, backgroundColor: 'rgba(78,186,139,0.06)', fill: true, tension: 0.35, pointRadius: 2, pointHoverRadius: 5, borderWidth: 2 }
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: { intersect: false, mode: 'index' },
					plugins: { legend: legendOpts },
					scales: axisOpts
				}
			});
		})();

		// GA4 Traffic Sources
		(function() {
			var el = document.getElementById('wham-ga4-sources');
			if (!el) return;
			<?php
			$source_labels = [];
			$source_values = [];
			if ( ! empty( $analytics['traffic_sources'] ) ) {
				foreach ( array_slice( $analytics['traffic_sources'], 0, 8 ) as $src ) {
					$source_labels[] = $src['source'] ?? $src['channel'] ?? $src['sessionDefaultChannelGroup'] ?? '';
					$source_values[] = $src['sessions'] ?? $src['metric_0'] ?? 0;
				}
			}
			?>
			new Chart(el, {
				type: 'bar',
				data: {
					labels: <?php echo wp_json_encode( $source_labels ); ?>,
					datasets: [{
						data: <?php echo wp_json_encode( $source_values ); ?>,
						backgroundColor: [blue, green, amber, purple, teal, rose, slate, peach],
						borderRadius: 4,
						barPercentage: 0.65
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: { legend: { display: false } },
					scales: axisOpts
				}
			});
		})();

		// GA4 Sessions & Users Trend
		(function() {
			var el = document.getElementById('wham-ga4-trend');
			if (!el) return;
			new Chart(el, {
				type: 'line',
				data: {
					labels: <?php echo wp_json_encode( $analytics['daily_labels'] ?? [] ); ?>,
					datasets: [
						{ label: 'Sessions', data: <?php echo wp_json_encode( $analytics['daily_sessions'] ?? [] ); ?>, borderColor: purple, backgroundColor: 'rgba(155,142,196,0.1)', fill: true, tension: 0.35, pointRadius: 2, pointHoverRadius: 5, borderWidth: 2.5 },
						{ label: 'Users', data: <?php echo wp_json_encode( $analytics['daily_users'] ?? [] ); ?>, borderColor: teal, backgroundColor: 'rgba(91,181,181,0.06)', fill: true, tension: 0.35, pointRadius: 2, pointHoverRadius: 5, borderWidth: 2 }
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: { intersect: false, mode: 'index' },
					plugins: { legend: legendOpts },
					scales: axisOpts
				}
			});
		})();
	});
	</script>

</div>
