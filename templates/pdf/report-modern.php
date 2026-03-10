<?php
/**
 * WHAM Report — Modern Style PDF Template
 * Bold, premium, high-contrast. Facts only — no judgments, warnings,
 * recommendations, health scores, dev hours, or theme data.
 *
 * DomPDF constraints: tables only, no flexbox/grid/JS, base64 images,
 * ASCII text only (no Unicode).
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
$wp_version            = $maintenance['wp_version'] ?? 'N/A';
$plugins_total         = $maintenance['plugins_total'] ?? 0;
$plugins_updates_count = $maintenance['plugins_updates_count'] ?? 0;
$php_version           = $maintenance['php_version'] ?? 'N/A';
$plugins_needing_update = $maintenance['plugins_needing_update'] ?? [];
$maint_error           = $maintenance['error'] ?? '';

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

// GSC comparison values.
$prev_clicks      = $gsc_comparison['prev_clicks'] ?? 0;
$prev_impressions = $gsc_comparison['prev_impressions'] ?? 0;
$prev_ctr         = $gsc_comparison['prev_ctr'] ?? 0;
$prev_position    = $gsc_comparison['prev_position'] ?? 0;
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
		font-size: 10pt;
		line-height: 1.5;
		color: #1e293b;
		background: #fff;
	}

	/* ---- Header bar ---- */
	.header-bar {
		background-color: #0f172a;
		color: #ffffff;
		padding: 28px 44px 0 44px;
	}
	.header-table {
		width: 100%;
		border-collapse: collapse;
	}
	.header-table td {
		vertical-align: bottom;
		padding: 0 0 18px 0;
	}
	.brand-name {
		font-size: 32pt;
		font-weight: bold;
		letter-spacing: 3px;
		color: #ffffff;
		line-height: 1;
	}
	.brand-tagline {
		font-size: 7pt;
		color: #64748b;
		letter-spacing: 1px;
		text-transform: uppercase;
		padding-top: 3px;
	}
	.header-right { text-align: right; }
	.client-name {
		font-size: 15pt;
		font-weight: bold;
		color: #ffffff;
		line-height: 1.2;
	}
	.header-detail {
		font-size: 8pt;
		color: #64748b;
		line-height: 1.6;
	}
	.plan-badge {
		display: inline-block;
		background-color: #1e3a5f;
		color: #7dd3fc;
		font-size: 7pt;
		font-weight: bold;
		text-transform: uppercase;
		letter-spacing: 1px;
		padding: 2px 10px;
		border-radius: 3px;
		margin-top: 4px;
	}
	/* Accent strip under header */
	.header-accent {
		height: 3px;
		background-color: #2563eb;
		/* DomPDF doesn't support CSS gradients, so we use a table strip */
	}
	.header-accent-table {
		width: 100%;
		border-collapse: collapse;
		height: 3px;
	}
	.header-accent-table td {
		height: 3px;
		padding: 0;
	}

	/* ---- Content area ---- */
	.content { padding: 22px 44px 16px 44px; }

	/* ---- Section header bars ---- */
	.section-bar {
		background-color: #0f172a;
		color: #ffffff;
		padding: 8px 14px;
		font-size: 11pt;
		font-weight: bold;
		text-transform: uppercase;
		letter-spacing: 2px;
		margin-bottom: 16px;
	}

	/* ---- Metric cards ---- */
	.metrics-table {
		width: 100%;
		border-collapse: separate;
		border-spacing: 8px 0;
		margin-bottom: 16px;
		margin-left: -8px;
	}
	.metric-cell {
		background-color: #f0f4f8;
		border: 1px solid #e2e8f0;
		border-top: 4px solid #2563eb;
		border-radius: 6px;
		padding: 14px 8px;
		text-align: center;
		vertical-align: top;
	}
	.metric-cell-green { border-top-color: #059669; }
	.metric-cell-amber { border-top-color: #d97706; }
	.metric-cell-red   { border-top-color: #dc2626; }
	.metric-value {
		font-size: 28pt;
		font-weight: bold;
		line-height: 1.1;
		color: #0f172a;
	}
	.metric-label {
		font-size: 7pt;
		text-transform: uppercase;
		letter-spacing: 1px;
		color: #64748b;
		padding-top: 4px;
	}
	.metric-change {
		font-size: 8pt;
		padding-top: 2px;
		color: #64748b;
	}

	/* ---- Data tables ---- */
	.data-table {
		width: 100%;
		border-collapse: collapse;
		font-size: 9pt;
		margin-bottom: 12px;
	}
	.data-table th {
		text-align: left;
		font-weight: bold;
		padding: 8px 10px;
		background-color: #0f172a;
		color: #ffffff;
		font-size: 7pt;
		text-transform: uppercase;
		letter-spacing: 1px;
	}
	.data-table td {
		padding: 7px 10px;
		border-bottom: 1px solid #e2e8f0;
	}
	.data-table tr.alt td {
		background-color: #f0f4f8;
	}
	.data-table tr:last-child td { border-bottom: none; }

	/* ---- Chart wrap ---- */
	.chart-wrap {
		text-align: center;
		margin-bottom: 14px;
	}

	/* ---- Notice ---- */
	.notice {
		background-color: #fef9e7;
		border: 1px solid #fde68a;
		border-radius: 4px;
		padding: 8px 12px;
		font-size: 8pt;
		color: #92400e;
		margin-bottom: 10px;
	}

	/* ---- Footer bar ---- */
	.footer-bar {
		padding: 14px 44px;
		background-color: #0f172a;
		color: #64748b;
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
<!-- PAGE 1: Header + Maintenance + Search KPIs + GSC Chart        -->
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
				<div class="header-detail"><?php echo esc_html( $period_label ); ?> Report</div>
				<div class="plan-badge"><?php echo esc_html( ucfirst( $tier ) ); ?> Plan</div>
			</td>
		</tr>
	</table>
</div>
<!-- Accent strip: navy -> blue -> green -->
<table class="header-accent-table">
	<tr>
		<td style="width:33%;background-color:#0f172a;"></td>
		<td style="width:34%;background-color:#2563eb;"></td>
		<td style="width:33%;background-color:#059669;"></td>
	</tr>
</table>

<div class="content">

	<!-- MAINTENANCE -->
	<?php if ( wham_tier_has( $tier, 'maintenance' ) ) : ?>
	<div class="section-bar">Site Maintenance</div>

	<?php if ( $maint_error ) : ?>
		<div class="notice"><?php echo esc_html( $maint_error ); ?></div>
	<?php else : ?>

		<!-- 3-column maintenance metrics (no theme) -->
		<table class="metrics-table">
			<tr>
				<td class="metric-cell metric-cell-green">
					<div class="metric-value"><?php echo esc_html( $wp_version ); ?></div>
					<div class="metric-label">WordPress</div>
				</td>
				<td class="metric-cell <?php echo $plugins_updates_count === 0 ? 'metric-cell-green' : 'metric-cell-amber'; ?>">
					<div class="metric-value"><?php echo intval( $plugins_total - $plugins_updates_count ); ?>/<?php echo intval( $plugins_total ); ?></div>
					<div class="metric-label">Plugins Updated</div>
				</td>
				<td class="metric-cell metric-cell-green">
					<div class="metric-value"><?php echo esc_html( $php_version ); ?></div>
					<div class="metric-label">PHP Version</div>
				</td>
			</tr>
		</table>

		<!-- Plugin detail table -->
		<?php if ( wham_tier_has( $tier, 'maintenance_detail' ) && ! empty( $plugins_needing_update ) ) : ?>
			<table class="data-table">
				<thead>
					<tr>
						<th>Plugin</th>
						<th style="text-align:right;">Current</th>
						<th style="text-align:right;">Available</th>
					</tr>
				</thead>
				<tbody>
					<?php $i = 0; foreach ( $plugins_needing_update as $plugin ) : ?>
						<tr<?php echo $i % 2 ? ' class="alt"' : ''; ?>>
							<td><?php echo esc_html( $plugin['name'] ?? $plugin['plugin'] ?? '' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $plugin['current_version'] ?? $plugin['version'] ?? '--' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $plugin['new_version'] ?? '--' ); ?></td>
						</tr>
					<?php $i++; endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	<?php endif; ?>
	<?php endif; /* maintenance */ ?>

	<!-- SEARCH PERFORMANCE -->
	<?php if ( wham_tier_has( $tier, 'gsc_aggregate' ) ) : ?>
	<div class="section-bar" style="margin-top:10px;">Search Performance</div>

	<?php if ( $gsc_error ) : ?>
		<div class="notice"><?php echo esc_html( $gsc_error ); ?></div>
	<?php else : ?>

		<!-- Search KPI cards -->
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
		<?php if ( wham_tier_has( $tier, 'gsc_trend' ) && ! empty( $charts['gsc_trend'] ) ) : ?>
			<?php echo wham_chart_img( $charts['gsc_trend'] ); ?>
		<?php endif; ?>

	<?php endif; ?>
	<?php endif; /* gsc_aggregate */ ?>

</div><!-- .content -->

<!-- Footer -->
<div class="footer-bar">
	<span class="footer-brand">WHAM</span> &mdash; Web Hosting &amp; Maintenance by Clear pH &nbsp;&bull;&nbsp; <?php echo esc_html( $period_label ); ?>
</div>

<div style="page-break-after: always;"></div>

<!-- ============================================================ -->
<!-- PAGE 2: Search Query Table + Pages Table                      -->
<!-- ============================================================ -->

<?php if ( wham_tier_has( $tier, 'gsc_top_queries' ) || wham_tier_has( $tier, 'gsc_top_pages' ) ) : ?>
<div style="page-break-before: always;"></div>

<div class="content">

	<div class="section-bar">Search Details</div>

	<?php if ( ! $gsc_error ) : ?>

		<!-- Top Queries Table -->
		<?php if ( wham_tier_has( $tier, 'gsc_top_queries' ) && ! empty( $gsc_top_queries ) ) : ?>
			<table class="data-table">
				<thead>
					<tr>
						<th>Top Search Queries</th>
						<th style="text-align:right;">Clicks</th>
						<th style="text-align:right;">Impressions</th>
						<th style="text-align:right;">CTR</th>
						<th style="text-align:right;">Position</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $gsc_top_queries, 0, 10 ) as $i => $q ) : ?>
						<tr<?php echo $i % 2 ? ' class="alt"' : ''; ?>>
							<td><?php echo esc_html( $q['query'] ?? '' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $q['clicks'] ?? 0 ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $q['impressions'] ?? 0 ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( isset( $q['ctr'] ) ? round( $q['ctr'], 1 ) . '%' : '--' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( isset( $q['position'] ) ? round( $q['position'], 1 ) : '--' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p style="font-size:9pt;color:#64748b;">No search query data available for this period.</p>
		<?php endif; ?>

		<!-- Top Pages Table -->
		<?php if ( wham_tier_has( $tier, 'gsc_top_pages' ) && ! empty( $gsc_top_pages ) ) : ?>
			<table class="data-table" style="margin-top:12px;">
				<thead>
					<tr>
						<th>Top Pages</th>
						<th style="text-align:right;">Clicks</th>
						<th style="text-align:right;">Impressions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $gsc_top_pages, 0, 8 ) as $i => $p ) : ?>
						<tr<?php echo $i % 2 ? ' class="alt"' : ''; ?>>
							<td style="word-break:break-all;max-width:300px;"><?php echo esc_html( $p['page'] ?? '' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $p['clicks'] ?? 0 ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $p['impressions'] ?? 0 ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p style="font-size:9pt;color:#64748b;">No page-level data available for this period.</p>
		<?php endif; ?>

	<?php else : ?>
		<div class="notice"><?php echo esc_html( $gsc_error ); ?></div>
	<?php endif; ?>

</div>

<!-- Footer -->
<div class="footer-bar">
	<span class="footer-brand">WHAM</span> &mdash; Web Hosting &amp; Maintenance by Clear pH &nbsp;&bull;&nbsp; <?php echo esc_html( $period_label ); ?>
</div>
<?php endif; /* gsc_top_queries || gsc_top_pages */ ?>

<div style="page-break-after: always;"></div>

<!-- ============================================================ -->
<!-- PAGE 3: Traffic KPIs + Charts + Landing Pages                 -->
<!-- ============================================================ -->

<?php if ( wham_tier_has( $tier, 'ga4_core' ) ) : ?>
<div style="page-break-before: always;"></div>

<div class="content">

	<div class="section-bar">Website Traffic</div>

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
		<?php if ( wham_tier_has( $tier, 'ga4_sources' ) && ! empty( $charts['ga4_sources'] ) ) : ?>
			<div style="font-size:9pt;font-weight:bold;color:#0f172a;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Traffic Sources</div>
			<?php echo wham_chart_img( $charts['ga4_sources'] ); ?>
		<?php endif; ?>

		<!-- Sessions Trend Chart -->
		<?php if ( wham_tier_has( $tier, 'ga4_trend' ) && ! empty( $charts['ga4_trend'] ) ) : ?>
			<div style="font-size:9pt;font-weight:bold;color:#0f172a;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Sessions Trend</div>
			<?php echo wham_chart_img( $charts['ga4_trend'] ); ?>
		<?php endif; ?>

		<!-- Top Landing Pages Table -->
		<?php if ( wham_tier_has( $tier, 'ga4_landing_pages' ) && ! empty( $ga4_pages ) ) : ?>
			<table class="data-table">
				<thead>
					<tr>
						<th>Top Landing Pages</th>
						<th style="text-align:right;">Sessions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $ga4_pages, 0, 8 ) as $i => $pg ) : ?>
						<tr<?php echo $i % 2 ? ' class="alt"' : ''; ?>>
							<td style="word-break:break-all;max-width:350px;"><?php echo esc_html( $pg['landingPagePlusQueryString'] ?? $pg['page'] ?? '' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $pg['metric_0'] ?? $pg['sessions'] ?? 0 ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p style="font-size:9pt;color:#64748b;">No landing page data available for this period.</p>
		<?php endif; ?>

	<?php endif; ?>

</div>

<!-- Footer -->
<div class="footer-bar">
	<span class="footer-brand">WHAM</span> &mdash; Web Hosting &amp; Maintenance by Clear pH &nbsp;&bull;&nbsp; <?php echo esc_html( $period_label ); ?>
</div>
<?php endif; /* ga4_core */ ?>

</body>
</html>
