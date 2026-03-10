<?php
/**
 * WHAM Report — Swiss Style PDF Template
 * Ultra-clean, minimal, Dieter Rams aesthetic.
 * Maximum whitespace, tight grid, very small labels.
 *
 * Variables available: $report (array with all report data)
 */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/helpers.php';

$client       = $report['client'] ?? [];
$maintenance  = $report['maintenance'] ?? [];
$search       = $report['search'] ?? [];
$analytics    = $report['analytics'] ?? [];
$charts       = $report['charts'] ?? [];
$period_label = $report['period_label'] ?? date( 'F Y' );
$tier         = $report['tier'] ?? 'professional';

// Maintenance data.
$wp_version      = $maintenance['wp_version'] ?? 'N/A';
$wp_up_to_date   = empty( $maintenance['wp_update_available'] );
$plugins_total   = $maintenance['plugins_total'] ?? 0;
$plugins_updates = $maintenance['plugins_updates_count'] ?? 0;
$plugins_updated = $plugins_total - $plugins_updates;
$plugins_needing = $maintenance['plugins_needing_update'] ?? [];
$php_version     = $maintenance['php_version'] ?? 'N/A';
$maint_error     = $maintenance['error'] ?? '';

// GSC data.
$gsc_clicks      = $search['clicks'] ?? 0;
$gsc_impressions = $search['impressions'] ?? 0;
$gsc_ctr         = $search['ctr'] ?? 0;
$gsc_position    = $search['position'] ?? 0;
$gsc_comparison  = $search['comparison'] ?? [];
$gsc_top_queries = $search['top_queries'] ?? [];
$gsc_top_pages   = $search['top_pages'] ?? [];
$gsc_error       = $search['error'] ?? '';

// GA4 data.
$ga4_sessions    = $analytics['sessions'] ?? 0;
$ga4_users       = $analytics['users'] ?? 0;
$ga4_pageviews   = $analytics['pageviews'] ?? 0;
$ga4_bounce      = $analytics['bounce_rate'] ?? 0;
$ga4_prev_sess   = $analytics['previous_sessions'] ?? 0;
$ga4_prev_users  = $analytics['previous_users'] ?? 0;
$ga4_prev_pv     = $analytics['previous_pageviews'] ?? 0;
$ga4_prev_bounce = $analytics['previous_bounce_rate'] ?? 0;
$ga4_sources     = $analytics['traffic_sources'] ?? [];
$ga4_pages       = $analytics['top_landing_pages'] ?? [];
$ga4_error       = $analytics['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>WHAM Report — <?php echo esc_html( $client['name'] ?? '' ); ?> — <?php echo esc_html( $period_label ); ?></title>
<style>
	* { margin: 0; padding: 0; box-sizing: border-box; }

	body {
		font-family: Helvetica, Arial, sans-serif;
		font-size: 9pt;
		line-height: 1.6;
		color: #1e293b;
		background: #ffffff;
	}

	/* ---- Header ---- */
	.header {
		padding: 44px 52px 20px 52px;
		border-bottom: 1px solid #e2e8f0;
	}
	.header-table {
		width: 100%;
		border-collapse: collapse;
	}
	.header-table td {
		vertical-align: bottom;
		padding: 0;
	}
	.brand-name {
		font-size: 28pt;
		font-weight: bold;
		letter-spacing: 6px;
		color: #0f172a;
		line-height: 1;
		text-transform: uppercase;
	}
	.brand-sub {
		font-size: 6pt;
		color: #94a3b8;
		letter-spacing: 2px;
		text-transform: uppercase;
		padding-top: 4px;
	}
	.header-right {
		text-align: right;
	}
	.client-name {
		font-size: 13pt;
		font-weight: bold;
		color: #0f172a;
		line-height: 1.2;
		letter-spacing: 0.5px;
	}
	.header-meta {
		font-size: 7pt;
		color: #94a3b8;
		letter-spacing: 1px;
		text-transform: uppercase;
		line-height: 1.8;
	}

	/* ---- Content ---- */
	.content {
		padding: 32px 52px 20px 52px;
	}

	/* ---- Section Titles ---- */
	.section-title {
		font-size: 11pt;
		font-weight: bold;
		text-transform: uppercase;
		letter-spacing: 2px;
		color: #0f172a;
		padding-bottom: 8px;
		margin-bottom: 28px;
		border-bottom: 1px solid #e2e8f0;
	}

	/* ---- Metric Cards (table) ---- */
	.metrics-table {
		width: 100%;
		border-collapse: separate;
		border-spacing: 16px 0;
		margin-bottom: 40px;
		margin-left: -16px;
	}
	.metric-cell {
		padding: 16px 8px 12px 8px;
		text-align: center;
		vertical-align: top;
		border-bottom: 1px solid #e2e8f0;
	}
	.metric-value {
		font-family: Courier, monospace;
		font-size: 24pt;
		font-weight: bold;
		line-height: 1.1;
		color: #0f172a;
	}
	.metric-label {
		font-size: 6pt;
		text-transform: uppercase;
		letter-spacing: 2px;
		color: #94a3b8;
		padding-top: 8px;
	}
	.metric-change {
		padding-top: 4px;
	}

	/* ---- Data Tables ---- */
	.data-table {
		width: 100%;
		border-collapse: collapse;
		font-size: 8pt;
		margin-bottom: 40px;
	}
	.data-table th {
		text-align: left;
		font-weight: bold;
		padding: 10px 12px;
		color: #0f172a;
		font-size: 6pt;
		text-transform: uppercase;
		letter-spacing: 2px;
		border-bottom: 2px solid #0f172a;
		background: none;
	}
	.data-table td {
		padding: 8px 12px;
		border-bottom: 1px solid #e2e8f0;
		color: #334155;
	}
	.data-table tr:last-child td {
		border-bottom: none;
	}

	/* ---- Chart ---- */
	.chart-wrap {
		text-align: center;
		margin-bottom: 40px;
	}

	/* ---- Footer ---- */
	.footer {
		padding: 16px 52px;
		border-top: 1px solid #e2e8f0;
		text-align: center;
		font-size: 6pt;
		color: #94a3b8;
		letter-spacing: 1px;
		text-transform: uppercase;
	}

	/* ---- Notice ---- */
	.notice {
		border: 1px solid #e2e8f0;
		padding: 12px 16px;
		font-size: 8pt;
		color: #64748b;
		margin-bottom: 40px;
	}
</style>
</head>
<body>

<!-- ============================================================ -->
<!-- PAGE 1: Header + Maintenance + Search KPIs + GSC Chart        -->
<!-- ============================================================ -->

<!-- Header -->
<div class="header">
	<table class="header-table">
		<tr>
			<td style="width: 50%;">
				<div class="brand-name">WHAM</div>
				<div class="brand-sub">Web Hosting &amp; Maintenance</div>
			</td>
			<td class="header-right">
				<div class="client-name"><?php echo esc_html( $client['name'] ?? 'Client' ); ?></div>
				<div class="header-meta"><?php echo esc_html( $client['url'] ?? '' ); ?></div>
				<div class="header-meta"><?php echo esc_html( $period_label ); ?></div>
			</td>
		</tr>
	</table>
</div>

<div class="content">

	<!-- Maintenance -->
	<div class="section-title">Maintenance</div>

	<?php if ( $maint_error ) : ?>
		<div class="notice"><?php echo esc_html( $maint_error ); ?></div>
	<?php else : ?>

		<table class="metrics-table">
			<tr>
				<td class="metric-cell">
					<div class="metric-value"><?php echo esc_html( $wp_version ); ?></div>
					<div class="metric-label">WordPress</div>
				</td>
				<td class="metric-cell">
					<div class="metric-value"><?php echo esc_html( $plugins_updated . '/' . $plugins_total ); ?></div>
					<div class="metric-label">Plugins Current</div>
				</td>
				<td class="metric-cell">
					<div class="metric-value"><?php echo esc_html( $php_version ); ?></div>
					<div class="metric-label">PHP Version</div>
				</td>
			</tr>
		</table>

		<!-- Plugin Detail Table -->
		<?php if ( ! empty( $plugins_needing ) ) : ?>
			<table class="data-table">
				<thead>
					<tr>
						<th>Plugin</th>
						<th style="text-align:right;">Installed</th>
						<th style="text-align:right;">Available</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $plugins_needing as $plugin ) : ?>
						<tr>
							<td><?php echo esc_html( $plugin['name'] ?? $plugin['plugin'] ?? '' ); ?></td>
							<td style="text-align:right;font-family:Courier,monospace;font-size:8pt;"><?php echo esc_html( $plugin['current_version'] ?? $plugin['version'] ?? '--' ); ?></td>
							<td style="text-align:right;font-family:Courier,monospace;font-size:8pt;"><?php echo esc_html( $plugin['new_version'] ?? '--' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	<?php endif; ?>

	<!-- Search Performance -->
	<div class="section-title">Search Performance</div>

	<?php if ( $gsc_error ) : ?>
		<div class="notice"><?php echo esc_html( $gsc_error ); ?></div>
	<?php else : ?>

		<?php
		$prev_clicks      = $gsc_comparison['prev_clicks'] ?? 0;
		$prev_impressions = $gsc_comparison['prev_impressions'] ?? 0;
		$prev_ctr         = $gsc_comparison['prev_ctr'] ?? 0;
		$prev_position    = $gsc_comparison['prev_position'] ?? 0;
		?>

		<table class="metrics-table">
			<tr>
				<td class="metric-cell">
					<div class="metric-value"><?php echo wham_format_number( $gsc_clicks ); ?></div>
					<div class="metric-label">Clicks</div>
					<?php if ( $prev_clicks ) : ?>
						<div class="metric-change"><?php echo wham_mom_badge( $gsc_clicks, $prev_clicks ); ?></div>
					<?php endif; ?>
				</td>
				<td class="metric-cell">
					<div class="metric-value"><?php echo wham_format_number( $gsc_impressions ); ?></div>
					<div class="metric-label">Impressions</div>
					<?php if ( $prev_impressions ) : ?>
						<div class="metric-change"><?php echo wham_mom_badge( $gsc_impressions, $prev_impressions ); ?></div>
					<?php endif; ?>
				</td>
				<td class="metric-cell">
					<div class="metric-value"><?php echo esc_html( $gsc_ctr ); ?>%</div>
					<div class="metric-label">Avg CTR</div>
					<?php if ( $prev_ctr ) : ?>
						<div class="metric-change"><?php echo wham_mom_badge( $gsc_ctr, $prev_ctr ); ?></div>
					<?php endif; ?>
				</td>
				<td class="metric-cell">
					<div class="metric-value"><?php echo esc_html( $gsc_position ); ?></div>
					<div class="metric-label">Avg Position</div>
					<?php if ( $prev_position ) : ?>
						<?php
						$pos_change = $prev_position - $gsc_position;
						$pos_color  = $pos_change >= 0 ? '#059669' : '#dc2626';
						$pos_prefix = $pos_change >= 0 ? '+' : '-';
						?>
						<div class="metric-change"><span style="font-size:8pt;color:<?php echo $pos_color; ?>;font-weight:bold;"><?php echo $pos_prefix . abs( round( $pos_change, 1 ) ); ?></span></div>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<!-- GSC Trend Chart -->
		<?php if ( ! empty( $charts['gsc_trend'] ) ) : ?>
			<?php echo wham_chart_img( $charts['gsc_trend'] ); ?>
		<?php endif; ?>

	<?php endif; ?>

</div>

<!-- Footer -->
<div class="footer">
	WHAM -- Web Hosting &amp; Maintenance by Clear pH -- <?php echo esc_html( $period_label ); ?>
</div>

<!-- ============================================================ -->
<!-- PAGE 2: Search Tables (Queries + Pages)                       -->
<!-- ============================================================ -->

<div style="page-break-before: always;"></div>

<div class="content">

	<div class="section-title">Search Queries</div>

	<?php if ( ! $gsc_error && ! empty( $gsc_top_queries ) ) : ?>
		<table class="data-table">
			<thead>
				<tr>
					<th>Query</th>
					<th style="text-align:right;">Clicks</th>
					<th style="text-align:right;">Impressions</th>
					<th style="text-align:right;">CTR</th>
					<th style="text-align:right;">Position</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_slice( $gsc_top_queries, 0, 10 ) as $q ) : ?>
					<tr>
						<td><?php echo esc_html( $q['query'] ?? '' ); ?></td>
						<td style="text-align:right;font-family:Courier,monospace;"><?php echo esc_html( $q['clicks'] ?? 0 ); ?></td>
						<td style="text-align:right;font-family:Courier,monospace;"><?php echo esc_html( $q['impressions'] ?? 0 ); ?></td>
						<td style="text-align:right;font-family:Courier,monospace;"><?php echo esc_html( isset( $q['ctr'] ) ? round( $q['ctr'], 1 ) . '%' : '--' ); ?></td>
						<td style="text-align:right;font-family:Courier,monospace;"><?php echo esc_html( isset( $q['position'] ) ? round( $q['position'], 1 ) : '--' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php elseif ( ! $gsc_error ) : ?>
		<div class="notice">No query data available for this period.</div>
	<?php endif; ?>

	<div class="section-title">Top Pages</div>

	<?php if ( ! $gsc_error && ! empty( $gsc_top_pages ) ) : ?>
		<table class="data-table">
			<thead>
				<tr>
					<th>Page</th>
					<th style="text-align:right;">Clicks</th>
					<th style="text-align:right;">Impressions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_slice( $gsc_top_pages, 0, 10 ) as $p ) : ?>
					<tr>
						<td style="word-break:break-all;max-width:320px;"><?php echo esc_html( $p['page'] ?? '' ); ?></td>
						<td style="text-align:right;font-family:Courier,monospace;"><?php echo esc_html( $p['clicks'] ?? 0 ); ?></td>
						<td style="text-align:right;font-family:Courier,monospace;"><?php echo esc_html( $p['impressions'] ?? 0 ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php elseif ( ! $gsc_error ) : ?>
		<div class="notice">No page data available for this period.</div>
	<?php endif; ?>

</div>

<!-- Footer -->
<div class="footer">
	WHAM -- Web Hosting &amp; Maintenance by Clear pH -- <?php echo esc_html( $period_label ); ?>
</div>

<!-- ============================================================ -->
<!-- PAGE 3: Traffic (GA4) KPIs + Charts + Landing Pages           -->
<!-- ============================================================ -->

<div style="page-break-before: always;"></div>

<div class="content">

	<div class="section-title">Website Traffic</div>

	<?php if ( $ga4_error ) : ?>
		<div class="notice"><?php echo esc_html( $ga4_error ); ?></div>
	<?php else : ?>

		<!-- Traffic KPI Cards -->
		<table class="metrics-table">
			<tr>
				<td class="metric-cell">
					<div class="metric-value"><?php echo wham_format_number( $ga4_sessions ); ?></div>
					<div class="metric-label">Sessions</div>
					<?php if ( $ga4_prev_sess ) : ?>
						<div class="metric-change"><?php echo wham_mom_badge( $ga4_sessions, $ga4_prev_sess ); ?></div>
					<?php endif; ?>
				</td>
				<td class="metric-cell">
					<div class="metric-value"><?php echo wham_format_number( $ga4_users ); ?></div>
					<div class="metric-label">Users</div>
					<?php if ( $ga4_prev_users ) : ?>
						<div class="metric-change"><?php echo wham_mom_badge( $ga4_users, $ga4_prev_users ); ?></div>
					<?php endif; ?>
				</td>
				<td class="metric-cell">
					<div class="metric-value"><?php echo wham_format_number( $ga4_pageviews ); ?></div>
					<div class="metric-label">Pageviews</div>
					<?php if ( $ga4_prev_pv ) : ?>
						<div class="metric-change"><?php echo wham_mom_badge( $ga4_pageviews, $ga4_prev_pv ); ?></div>
					<?php endif; ?>
				</td>
				<td class="metric-cell">
					<div class="metric-value"><?php echo esc_html( $ga4_bounce ); ?>%</div>
					<div class="metric-label">Bounce Rate</div>
					<?php if ( $ga4_prev_bounce ) : ?>
						<?php
						$bounce_change = round( ( ( $ga4_bounce - $ga4_prev_bounce ) / $ga4_prev_bounce ) * 100, 1 );
						$bounce_color  = $bounce_change <= 0 ? '#059669' : '#dc2626';
						$bounce_prefix = $bounce_change <= 0 ? '-' : '+';
						?>
						<div class="metric-change"><span style="font-size:8pt;color:<?php echo $bounce_color; ?>;font-weight:bold;"><?php echo $bounce_prefix . abs( $bounce_change ); ?>%</span></div>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<!-- Traffic Sources Chart -->
		<?php if ( ! empty( $charts['ga4_sources'] ) ) : ?>
			<?php echo wham_chart_img( $charts['ga4_sources'] ); ?>
		<?php endif; ?>

		<!-- Sessions Trend Chart -->
		<?php if ( ! empty( $charts['ga4_trend'] ) ) : ?>
			<?php echo wham_chart_img( $charts['ga4_trend'] ); ?>
		<?php endif; ?>

		<!-- Top Landing Pages -->
		<?php if ( ! empty( $ga4_pages ) ) : ?>
			<div class="section-title" style="margin-top:20px;">Landing Pages</div>

			<table class="data-table">
				<thead>
					<tr>
						<th>Page</th>
						<th style="text-align:right;">Sessions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $ga4_pages, 0, 10 ) as $pg ) : ?>
						<tr>
							<td style="word-break:break-all;max-width:360px;"><?php echo esc_html( $pg['landingPagePlusQueryString'] ?? $pg['page'] ?? '' ); ?></td>
							<td style="text-align:right;font-family:Courier,monospace;"><?php echo esc_html( $pg['metric_0'] ?? $pg['sessions'] ?? 0 ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	<?php endif; ?>

</div>

<!-- Footer -->
<div class="footer">
	WHAM -- Web Hosting &amp; Maintenance by Clear pH -- <?php echo esc_html( $period_label ); ?>
</div>

</body>
</html>
