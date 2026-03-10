<?php
/**
 * WHAM Report — Editorial Style PDF Template
 * Clean, high-end annual report aesthetic. Facts only — no judgments,
 * warnings, recommendations, health scores, dev hours, or theme info.
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

// Maintenance data (no theme).
$wp_version      = $maintenance['wp_version'] ?? 'N/A';
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
<title>WHAM Report &mdash; <?php echo esc_html( $client['name'] ?? '' ); ?> &mdash; <?php echo esc_html( $period_label ); ?></title>
<style>
	* { margin: 0; padding: 0; box-sizing: border-box; }

	body {
		font-family: Helvetica, Arial, sans-serif;
		font-size: 10pt;
		line-height: 1.6;
		color: #2d3748;
		background: #ffffff;
	}

	/* ── Header bar ── */
	.header-bar {
		background-color: #0f172a;
		color: #ffffff;
		padding: 30px 48px 24px 48px;
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
		letter-spacing: 3px;
		color: #ffffff;
		line-height: 1;
	}
	.brand-tagline {
		font-size: 7pt;
		color: #94a3b8;
		letter-spacing: 1px;
		text-transform: uppercase;
		padding-top: 4px;
	}
	.header-right {
		text-align: right;
	}
	.client-name {
		font-size: 14pt;
		font-weight: bold;
		color: #ffffff;
		line-height: 1.2;
	}
	.header-detail {
		font-size: 8pt;
		color: #94a3b8;
		line-height: 1.6;
	}

	/* ── Content area ── */
	.content {
		padding: 28px 48px 16px 48px;
	}

	/* ── Section headers ── */
	.section-title {
		font-size: 12pt;
		font-weight: bold;
		color: #0f172a;
		padding-bottom: 6px;
		margin-bottom: 16px;
		border-bottom: 2px solid #2563eb;
	}

	/* ── Metric cards ── */
	.metrics-table {
		width: 100%;
		border-collapse: separate;
		border-spacing: 10px 0;
		margin-bottom: 18px;
		margin-left: -10px;
	}
	.metric-cell {
		background-color: #f8fafc;
		border: 1px solid #e2e8f0;
		border-radius: 6px;
		padding: 14px 10px;
		text-align: center;
		vertical-align: top;
	}
	.metric-value {
		font-size: 20pt;
		font-weight: bold;
		line-height: 1.1;
		color: #0f172a;
	}
	.metric-label {
		font-size: 7pt;
		text-transform: uppercase;
		letter-spacing: 1px;
		color: #718096;
		padding-top: 5px;
	}
	.metric-change {
		font-size: 8pt;
		padding-top: 3px;
		color: #718096;
	}

	/* ── Maintenance summary table ── */
	.maint-table {
		width: 100%;
		border-collapse: separate;
		border-spacing: 10px 0;
		margin-bottom: 20px;
		margin-left: -10px;
	}
	.maint-cell {
		background-color: #f8fafc;
		border: 1px solid #e2e8f0;
		border-radius: 6px;
		padding: 16px 12px;
		text-align: center;
		vertical-align: top;
		width: 33.33%;
	}
	.maint-value {
		font-size: 16pt;
		font-weight: bold;
		color: #0f172a;
		line-height: 1.2;
	}
	.maint-label {
		font-size: 7pt;
		text-transform: uppercase;
		letter-spacing: 1px;
		color: #718096;
		padding-top: 5px;
	}

	/* ── Data tables ── */
	.data-table {
		width: 100%;
		border-collapse: collapse;
		font-size: 9pt;
		margin-bottom: 14px;
	}
	.data-table th {
		text-align: left;
		font-weight: bold;
		padding: 8px 10px;
		background-color: #f1f5f9;
		color: #0f172a;
		font-size: 7pt;
		text-transform: uppercase;
		letter-spacing: 1px;
		border-bottom: 1px solid #e2e8f0;
	}
	.data-table td {
		padding: 7px 10px;
		border-bottom: 1px solid #e2e8f0;
	}
	.data-table tr.alt td {
		background-color: #f8fafc;
	}
	.data-table tr:last-child td {
		border-bottom: none;
	}

	/* ── Chart wrapper ── */
	.chart-wrap {
		text-align: center;
		margin-bottom: 18px;
	}

	/* ── Footer ── */
	.footer-bar {
		padding: 14px 48px;
		background-color: #0f172a;
		color: #94a3b8;
		font-size: 7pt;
		text-align: center;
	}
	.footer-bar .footer-brand {
		font-weight: bold;
		color: #ffffff;
		letter-spacing: 1px;
	}
</style>
</head>
<body>

<!-- ============================================================ -->
<!-- PAGE 1: Header + Maintenance + Search KPIs                    -->
<!-- ============================================================ -->

<!-- Header -->
<div class="header-bar">
	<table class="header-table">
		<tr>
			<td style="width: 50%;">
				<div class="brand-name">WHAM</div>
				<div class="brand-tagline">Web Hosting &amp; Maintenance</div>
			</td>
			<td class="header-right">
				<div class="client-name"><?php echo esc_html( $client['name'] ?? 'Client' ); ?></div>
				<div class="header-detail"><?php echo esc_html( $client['url'] ?? '' ); ?></div>
				<div class="header-detail"><?php echo esc_html( $period_label ); ?></div>
			</td>
		</tr>
	</table>
</div>

<div class="content">

	<!-- Updates & Maintenance -->
	<div class="section-title">Updates &amp; Maintenance</div>

	<?php if ( $maint_error ) : ?>
		<p style="font-size:9pt;color:#718096;margin-bottom:16px;"><?php echo esc_html( $maint_error ); ?></p>
	<?php else : ?>

		<table class="maint-table">
			<tr>
				<td class="maint-cell">
					<div class="maint-value"><?php echo esc_html( $wp_version ); ?></div>
					<div class="maint-label">WordPress</div>
				</td>
				<td class="maint-cell">
					<div class="maint-value"><?php echo esc_html( $plugins_updated ); ?>/<?php echo esc_html( $plugins_total ); ?></div>
					<div class="maint-label">Plugins Updated</div>
				</td>
				<td class="maint-cell">
					<div class="maint-value"><?php echo esc_html( $php_version ); ?></div>
					<div class="maint-label">PHP Version</div>
				</td>
			</tr>
		</table>

		<!-- Plugin updates detail -->
		<?php if ( ! empty( $plugins_needing ) ) : ?>
			<table class="data-table" style="margin-bottom:22px;">
				<thead>
					<tr>
						<th>Plugin</th>
						<th style="text-align:right;">Current Version</th>
						<th style="text-align:right;">Available Version</th>
					</tr>
				</thead>
				<tbody>
					<?php $i = 0; foreach ( $plugins_needing as $plugin ) : ?>
						<tr<?php echo $i % 2 ? ' class="alt"' : ''; ?>>
							<td><?php echo esc_html( $plugin['name'] ?? $plugin['plugin'] ?? '' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $plugin['current_version'] ?? $plugin['version'] ?? 'N/A' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $plugin['new_version'] ?? 'N/A' ); ?></td>
						</tr>
					<?php $i++; endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	<?php endif; ?>

	<!-- Search & Discovery -->
	<div class="section-title">Search &amp; Discovery</div>

	<?php if ( $gsc_error ) : ?>
		<p style="font-size:9pt;color:#718096;margin-bottom:16px;"><?php echo esc_html( $gsc_error ); ?></p>
	<?php else : ?>

		<?php
		$prev_clicks      = $gsc_comparison['prev_clicks'] ?? 0;
		$prev_impressions = $gsc_comparison['prev_impressions'] ?? 0;
		$prev_ctr         = $gsc_comparison['prev_ctr'] ?? 0;
		$prev_position    = $gsc_comparison['prev_position'] ?? 0;
		?>

		<!-- KPI Cards -->
		<table class="metrics-table">
			<tr>
				<td class="metric-cell">
					<div class="metric-value"><?php echo wham_format_number( $gsc_clicks ); ?></div>
					<div class="metric-label">Clicks</div>
					<?php if ( $prev_clicks ) : ?>
						<div class="metric-change"><?php echo wham_mom_badge( $gsc_clicks, $prev_clicks ); ?> vs prior</div>
					<?php endif; ?>
				</td>
				<td class="metric-cell">
					<div class="metric-value"><?php echo wham_format_number( $gsc_impressions ); ?></div>
					<div class="metric-label">Impressions</div>
					<?php if ( $prev_impressions ) : ?>
						<div class="metric-change"><?php echo wham_mom_badge( $gsc_impressions, $prev_impressions ); ?> vs prior</div>
					<?php endif; ?>
				</td>
				<td class="metric-cell">
					<div class="metric-value"><?php echo esc_html( $gsc_ctr ); ?>%</div>
					<div class="metric-label">Avg CTR</div>
					<?php if ( $prev_ctr ) : ?>
						<div class="metric-change"><?php echo wham_mom_badge( $gsc_ctr, $prev_ctr ); ?> vs prior</div>
					<?php endif; ?>
				</td>
				<td class="metric-cell">
					<div class="metric-value"><?php echo esc_html( $gsc_position ); ?></div>
					<div class="metric-label">Avg Position</div>
					<?php if ( $prev_position ) : ?>
						<?php
						// Lower position = better ranking, so invert direction.
						$pos_change = $prev_position - $gsc_position;
						$pos_color  = $pos_change >= 0 ? '#059669' : '#dc2626';
						$pos_prefix = $pos_change >= 0 ? '+' : '-';
						?>
						<div class="metric-change"><span style="font-size:8pt;color:<?php echo $pos_color; ?>;font-weight:bold;"><?php echo $pos_prefix . abs( round( $pos_change, 1 ) ); ?></span> vs prior</div>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<!-- GSC Trend Chart -->
		<?php if ( ! empty( $charts['gsc_trend'] ) ) : ?>
			<?php echo wham_chart_img( $charts['gsc_trend'] ); ?>
		<?php endif; ?>

	<?php endif; ?>

</div><!-- .content -->

<!-- Footer -->
<div class="footer-bar">
	<span class="footer-brand">WHAM</span> &mdash; Web Hosting &amp; Maintenance by Clear pH
</div>

<!-- ============================================================ -->
<!-- PAGE 2: Search Tables                                         -->
<!-- ============================================================ -->

<div style="page-break-before: always;"></div>

<div class="content">

	<?php if ( ! $gsc_error ) : ?>

		<!-- Top Search Queries -->
		<?php if ( ! empty( $gsc_top_queries ) ) : ?>
			<div class="section-title">Top Search Queries</div>
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
					<?php foreach ( array_slice( $gsc_top_queries, 0, 8 ) as $i => $q ) : ?>
						<tr<?php echo $i % 2 ? ' class="alt"' : ''; ?>>
							<td><?php echo esc_html( $q['query'] ?? '' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $q['clicks'] ?? 0 ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $q['impressions'] ?? 0 ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( isset( $q['ctr'] ) ? round( $q['ctr'], 1 ) . '%' : 'N/A' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( isset( $q['position'] ) ? round( $q['position'], 1 ) : 'N/A' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<!-- Top Pages -->
		<?php if ( ! empty( $gsc_top_pages ) ) : ?>
			<div class="section-title" style="margin-top:24px;">Top Pages</div>
			<table class="data-table">
				<thead>
					<tr>
						<th>Page</th>
						<th style="text-align:right;">Clicks</th>
						<th style="text-align:right;">Impressions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $gsc_top_pages, 0, 5 ) as $i => $p ) : ?>
						<tr<?php echo $i % 2 ? ' class="alt"' : ''; ?>>
							<td style="word-break:break-all;max-width:320px;"><?php echo esc_html( $p['page'] ?? '' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $p['clicks'] ?? 0 ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $p['impressions'] ?? 0 ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	<?php else : ?>
		<p style="font-size:9pt;color:#718096;">Search data unavailable for this period.</p>
	<?php endif; ?>

</div>

<!-- Footer -->
<div class="footer-bar">
	<span class="footer-brand">WHAM</span> &mdash; Web Hosting &amp; Maintenance by Clear pH
</div>

<!-- ============================================================ -->
<!-- PAGE 3: Website Traffic (GA4)                                 -->
<!-- ============================================================ -->

<div style="page-break-before: always;"></div>

<div class="content">

	<div class="section-title">Website Traffic</div>

	<?php if ( $ga4_error ) : ?>
		<p style="font-size:9pt;color:#718096;margin-bottom:16px;"><?php echo esc_html( $ga4_error ); ?></p>
	<?php else : ?>

		<!-- KPI Cards -->
		<table class="metrics-table">
			<tr>
				<td class="metric-cell">
					<div class="metric-value"><?php echo wham_format_number( $ga4_sessions ); ?></div>
					<div class="metric-label">Sessions</div>
					<?php if ( $ga4_prev_sess ) : ?>
						<div class="metric-change"><?php echo wham_mom_badge( $ga4_sessions, $ga4_prev_sess ); ?> vs prior</div>
					<?php endif; ?>
				</td>
				<td class="metric-cell">
					<div class="metric-value"><?php echo wham_format_number( $ga4_users ); ?></div>
					<div class="metric-label">Users</div>
					<?php if ( $ga4_prev_users ) : ?>
						<div class="metric-change"><?php echo wham_mom_badge( $ga4_users, $ga4_prev_users ); ?> vs prior</div>
					<?php endif; ?>
				</td>
				<td class="metric-cell">
					<div class="metric-value"><?php echo wham_format_number( $ga4_pageviews ); ?></div>
					<div class="metric-label">Pageviews</div>
					<?php if ( $ga4_prev_pv ) : ?>
						<div class="metric-change"><?php echo wham_mom_badge( $ga4_pageviews, $ga4_prev_pv ); ?> vs prior</div>
					<?php endif; ?>
				</td>
				<td class="metric-cell">
					<div class="metric-value"><?php echo esc_html( $ga4_bounce ); ?>%</div>
					<div class="metric-label">Bounce Rate</div>
					<?php if ( $ga4_prev_bounce ) : ?>
						<?php
						// For bounce rate, lower is better — invert the color logic.
						$bounce_change = round( ( ( $ga4_bounce - $ga4_prev_bounce ) / $ga4_prev_bounce ) * 100, 1 );
						$bounce_color  = $bounce_change <= 0 ? '#059669' : '#dc2626';
						$bounce_prefix = $bounce_change <= 0 ? '-' : '+';
						?>
						<div class="metric-change"><span style="font-size:8pt;color:<?php echo $bounce_color; ?>;font-weight:bold;"><?php echo $bounce_prefix . abs( $bounce_change ); ?>%</span> vs prior</div>
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
			<table class="data-table" style="margin-top:8px;">
				<thead>
					<tr>
						<th>Top Landing Pages</th>
						<th style="text-align:right;">Sessions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $ga4_pages, 0, 5 ) as $i => $pg ) : ?>
						<tr<?php echo $i % 2 ? ' class="alt"' : ''; ?>>
							<td style="word-break:break-all;max-width:360px;"><?php echo esc_html( $pg['landingPagePlusQueryString'] ?? $pg['page'] ?? '' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $pg['metric_0'] ?? $pg['sessions'] ?? 0 ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	<?php endif; ?>

</div>

<!-- Footer -->
<div class="footer-bar">
	<span class="footer-brand">WHAM</span> &mdash; Web Hosting &amp; Maintenance by Clear pH
</div>

</body>
</html>
