<?php
/**
 * WHAM Report — Professional/Premium Tier PDF Template
 * Multi-page narrative report with charts and insights.
 *
 * Variables available: $report (array with all report data)
 */
defined( 'ABSPATH' ) || exit;

$client       = $report['client'] ?? [];
$maintenance  = $report['maintenance'] ?? [];
$search       = $report['search'] ?? [];
$analytics    = $report['analytics'] ?? [];
$dev_hours    = $report['dev_hours'] ?? [];
$insights     = $report['insights'] ?? [];
$charts       = $report['charts'] ?? [];
$period_label = $report['period_label'] ?? date( 'F Y' );
$tier         = $report['tier'] ?? 'professional';

// Maintenance data.
$wp_version          = $maintenance['wp_version'] ?? 'N/A';
$wp_up_to_date       = empty( $maintenance['wp_update_available'] );
$plugins_total       = $maintenance['plugins_total'] ?? 0;
$plugins_updates     = $maintenance['plugins_updates_count'] ?? 0;
$plugins_updated     = $plugins_total - $plugins_updates;
$plugins_needing     = $maintenance['plugins_needing_update'] ?? [];
$theme_name          = $maintenance['theme_name'] ?? 'N/A';
$theme_version       = $maintenance['theme_version'] ?? '';
$theme_current       = empty( $maintenance['theme_update_available'] );
$php_version         = $maintenance['php_version'] ?? 'N/A';
$maint_error         = $maintenance['error'] ?? '';

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
$ga4_sessions     = $analytics['sessions'] ?? 0;
$ga4_users        = $analytics['users'] ?? 0;
$ga4_pageviews    = $analytics['pageviews'] ?? 0;
$ga4_bounce       = $analytics['bounce_rate'] ?? 0;
$ga4_prev_sess    = $analytics['previous_sessions'] ?? 0;
$ga4_prev_users   = $analytics['previous_users'] ?? 0;
$ga4_prev_pv      = $analytics['previous_pageviews'] ?? 0;
$ga4_prev_bounce  = $analytics['previous_bounce_rate'] ?? 0;
$ga4_sources      = $analytics['traffic_sources'] ?? [];
$ga4_pages        = $analytics['top_landing_pages'] ?? [];
$ga4_error        = $analytics['error'] ?? '';

// Dev hours.
$hours_included  = $dev_hours['hours_included'] ?? 0;
$hours_used      = $dev_hours['hours_used'] ?? 0;
$hours_remaining = $dev_hours['hours_remaining'] ?? 0;
$work_summary    = $dev_hours['work_summary'] ?? '';
$dev_error       = $dev_hours['error'] ?? '';

// Insights data.
$wins            = $insights['wins'] ?? [];
$watch_items     = $insights['watch_items'] ?? [];
$recommendations = $insights['recommendations'] ?? [];
$health_scores   = $insights['health_scores'] ?? [];
$overall_health  = $insights['overall_health'] ?? 'green';
$exec_summary    = $insights['executive_summary'] ?? '';

// Helper: format large numbers.
if ( ! function_exists( 'wham_format_number' ) ) {
	function wham_format_number( $n ) {
		if ( $n >= 1000000 ) return round( $n / 1000000, 1 ) . 'M';
		if ( $n >= 1000 )    return round( $n / 1000, 1 ) . 'K';
		return number_format( $n );
	}
}

// Helper: format duration in seconds to readable.
if ( ! function_exists( 'wham_format_duration' ) ) {
	function wham_format_duration( $seconds ) {
		$m = floor( $seconds / 60 );
		$s = $seconds % 60;
		return $m . 'm ' . $s . 's';
	}
}

// MoM change badge helper.
if ( ! function_exists( 'wham_mom_badge' ) ) {
	function wham_mom_badge( $current, $previous, $suffix = '' ) {
		if ( ! $previous ) return '';
		$change = round( ( ( $current - $previous ) / $previous ) * 100, 1 );
		$arrow  = $change >= 0 ? '&#9650;' : '&#9660;';
		$color  = $change >= 0 ? '#16a34a' : '#dc2626';
		return '<span style="font-size:8pt;color:' . $color . ';font-weight:bold;">' . $arrow . ' ' . abs( $change ) . '%' . $suffix . '</span>';
	}
}

// Health color helper.
if ( ! function_exists( 'wham_health_color' ) ) {
	function wham_health_color( $score ) {
		$map = [ 'green' => '#16a34a', 'amber' => '#d97706', 'red' => '#dc2626' ];
		return $map[ $score ] ?? '#718096';
	}
}

// Health background helper.
if ( ! function_exists( 'wham_health_bg' ) ) {
	function wham_health_bg( $score ) {
		$map = [ 'green' => '#dcfce7', 'amber' => '#fef3c7', 'red' => '#fee2e2' ];
		return $map[ $score ] ?? '#f7fafc';
	}
}
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
		color: #2d3748;
		background: #fff;
	}

	/* Header */
	.header-bar {
		background-color: #1a2332;
		color: #ffffff;
		padding: 28px 44px 22px 44px;
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
		font-size: 30pt;
		font-weight: bold;
		letter-spacing: 2px;
		color: #ffffff;
		line-height: 1;
	}
	.brand-tagline {
		font-size: 7pt;
		color: #8899aa;
		letter-spacing: 1px;
		text-transform: uppercase;
		padding-top: 3px;
	}
	.header-right { text-align: right; }
	.client-name {
		font-size: 14pt;
		font-weight: bold;
		color: #ffffff;
		line-height: 1.2;
	}
	.header-detail {
		font-size: 8pt;
		color: #8899aa;
		line-height: 1.6;
	}
	.plan-badge {
		display: inline-block;
		background-color: #2a3f56;
		color: #8cb4d8;
		font-size: 7pt;
		font-weight: bold;
		text-transform: uppercase;
		letter-spacing: 1px;
		padding: 2px 10px;
		border-radius: 3px;
		margin-top: 4px;
	}

	/* Content */
	.content { padding: 24px 44px 16px 44px; }

	/* Section titles */
	.section-title {
		font-size: 13pt;
		font-weight: bold;
		text-transform: uppercase;
		letter-spacing: 1px;
		color: #1a2332;
		padding-bottom: 6px;
		margin-bottom: 14px;
		border-bottom: 3px solid #3b82f6;
	}

	/* Health score cards */
	.health-cards {
		width: 100%;
		border-collapse: separate;
		border-spacing: 8px 0;
		margin-bottom: 18px;
		margin-left: -8px;
	}
	.health-card {
		border-radius: 6px;
		padding: 14px 10px;
		text-align: center;
		vertical-align: top;
		width: 25%;
	}
	.health-card-label {
		font-size: 8pt;
		font-weight: bold;
		text-transform: uppercase;
		letter-spacing: 1px;
	}
	.health-card-status {
		font-size: 16pt;
		font-weight: bold;
		padding-top: 4px;
	}

	/* Metric cards via table */
	.metrics-table {
		width: 100%;
		border-collapse: separate;
		border-spacing: 8px 0;
		margin-bottom: 14px;
		margin-left: -8px;
	}
	.metric-cell {
		background-color: #f7fafc;
		border: 1px solid #e2e8f0;
		border-radius: 6px;
		padding: 12px 8px;
		text-align: center;
		vertical-align: top;
	}
	.metric-value {
		font-size: 20pt;
		font-weight: bold;
		line-height: 1.1;
		color: #1a2332;
	}
	.metric-value-green { color: #16a34a; }
	.metric-value-amber { color: #d97706; }
	.metric-value-red { color: #dc2626; }
	.metric-label {
		font-size: 7pt;
		text-transform: uppercase;
		letter-spacing: 1px;
		color: #718096;
		padding-top: 4px;
	}
	.metric-change {
		font-size: 8pt;
		padding-top: 2px;
		color: #718096;
	}

	/* Data tables */
	.data-table {
		width: 100%;
		border-collapse: collapse;
		font-size: 9pt;
		margin-bottom: 10px;
	}
	.data-table th {
		text-align: left;
		font-weight: bold;
		padding: 8px 10px;
		background-color: #1a2332;
		color: #ffffff;
		font-size: 7pt;
		text-transform: uppercase;
		letter-spacing: 1px;
	}
	.data-table td {
		padding: 6px 10px;
		border-bottom: 1px solid #e2e8f0;
	}
	.data-table tr.alt td {
		background-color: #f7fafc;
	}
	.data-table tr:last-child td { border-bottom: none; }

	/* Wins / watch items */
	.wins-list, .watch-list {
		padding: 0;
		margin: 0 0 14px 0;
		list-style: none;
	}
	.wins-list li, .watch-list li {
		padding: 4px 0 4px 20px;
		font-size: 10pt;
		line-height: 1.5;
		position: relative;
	}
	.wins-list li:before {
		content: "";
		position: absolute;
		left: 0;
		top: 10px;
		width: 10px;
		height: 10px;
		background-color: #16a34a;
		border-radius: 50%;
	}
	.watch-list li:before {
		content: "";
		position: absolute;
		left: 0;
		top: 10px;
		width: 10px;
		height: 10px;
		background-color: #d97706;
		border-radius: 50%;
	}

	/* Callout box */
	.callout-box {
		background-color: #eff6ff;
		border: 1px solid #bfdbfe;
		border-left: 4px solid #3b82f6;
		border-radius: 4px;
		padding: 14px 18px;
		margin-bottom: 14px;
	}
	.callout-title {
		font-size: 10pt;
		font-weight: bold;
		color: #1a2332;
		margin-bottom: 4px;
	}
	.callout-text {
		font-size: 9pt;
		color: #4a5568;
		line-height: 1.6;
	}

	/* Executive summary */
	.exec-summary {
		font-size: 11pt;
		line-height: 1.7;
		color: #2d3748;
		margin-bottom: 18px;
		padding: 12px 16px;
		background-color: #f7fafc;
		border-left: 4px solid #1a2332;
		border-radius: 0 4px 4px 0;
	}

	/* Chart image */
	.chart-wrap {
		text-align: center;
		margin-bottom: 14px;
	}

	/* Hours bar */
	.hours-bar-outer {
		background-color: #e2e8f0;
		border-radius: 6px;
		height: 20px;
		margin: 10px 0 6px 0;
		overflow: hidden;
		width: 100%;
	}
	.hours-bar-inner {
		height: 100%;
		border-radius: 6px;
	}
	.hours-pct-label {
		font-size: 8pt;
		color: #718096;
		text-align: center;
	}

	/* Work summary */
	.work-summary {
		background-color: #f7fafc;
		border-left: 4px solid #1a2332;
		padding: 10px 14px;
		font-size: 9pt;
		margin-top: 10px;
		color: #2d3748;
	}

	/* Notice */
	.notice {
		background-color: #fffbeb;
		border: 1px solid #fde68a;
		border-radius: 4px;
		padding: 8px 12px;
		font-size: 8pt;
		color: #92400e;
		margin-bottom: 10px;
	}

	/* Recommendation cards */
	.rec-item {
		margin-bottom: 16px;
		padding-bottom: 14px;
		border-bottom: 1px solid #e2e8f0;
	}
	.rec-number {
		display: inline-block;
		background-color: #1a2332;
		color: #ffffff;
		font-size: 9pt;
		font-weight: bold;
		width: 24px;
		height: 24px;
		text-align: center;
		line-height: 24px;
		border-radius: 50%;
		margin-right: 8px;
		vertical-align: middle;
	}
	.rec-title {
		font-size: 11pt;
		font-weight: bold;
		color: #1a2332;
		display: inline;
		vertical-align: middle;
	}
	.rec-rationale {
		font-size: 9pt;
		color: #4a5568;
		line-height: 1.6;
		margin-top: 6px;
		padding-left: 32px;
	}
	.rec-impact {
		font-size: 9pt;
		color: #16a34a;
		font-weight: bold;
		margin-top: 4px;
		padding-left: 32px;
	}

	/* Two-column layout table */
	.two-col-table {
		width: 100%;
		border-collapse: separate;
		border-spacing: 10px 0;
		margin-left: -10px;
	}
	.two-col-table > tr > td,
	.two-col-table > tbody > tr > td {
		width: 50%;
		vertical-align: top;
		padding: 0;
	}

	/* Status badges */
	.badge-green { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 7pt; font-weight: bold; text-transform: uppercase; background-color: #dcfce7; color: #166534; }
	.badge-amber { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 7pt; font-weight: bold; text-transform: uppercase; background-color: #fef3c7; color: #92400e; }
	.badge-red { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 7pt; font-weight: bold; text-transform: uppercase; background-color: #fee2e2; color: #991b1b; }

	/* Footer */
	.footer-bar {
		padding: 14px 44px;
		background-color: #1a2332;
		color: #8899aa;
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
<!-- PAGE 1: Cover + Executive Summary                            -->
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

<div class="content">

	<!-- Executive Summary -->
	<?php if ( $exec_summary ) : ?>
		<div class="exec-summary"><?php echo esc_html( $exec_summary ); ?></div>
	<?php endif; ?>

	<!-- Health Score Cards -->
	<?php
	$health_labels = [
		'security'  => 'Security',
		'seo'       => 'SEO',
		'traffic'   => 'Traffic',
		'dev_hours' => 'Dev Activity',
	];
	$health_icons = [
		'green' => '&#10003;',
		'amber' => '&#9888;',
		'red'   => '&#10007;',
	];
	?>
	<table class="health-cards">
		<tr>
			<?php foreach ( $health_labels as $key => $label ) :
				$score = $health_scores[ $key ] ?? 'green';
				$bg    = wham_health_bg( $score );
				$color = wham_health_color( $score );
				$icon  = $health_icons[ $score ] ?? '';
			?>
				<td class="health-card" style="background-color: <?php echo $bg; ?>;">
					<div class="health-card-status" style="color: <?php echo $color; ?>;"><?php echo $icon; ?></div>
					<div class="health-card-label" style="color: <?php echo $color; ?>;"><?php echo esc_html( $label ); ?></div>
				</td>
			<?php endforeach; ?>
		</tr>
	</table>

	<!-- Top Wins -->
	<?php if ( ! empty( $wins ) ) : ?>
		<div style="font-size:10pt;font-weight:bold;color:#1a2332;margin-bottom:6px;">Top Wins This Month</div>
		<table style="width:100%;border-collapse:collapse;margin-bottom:14px;">
			<?php foreach ( array_slice( $wins, 0, 3 ) as $win ) : ?>
				<tr>
					<td style="width:18px;vertical-align:top;padding:4px 8px 4px 0;">
						<span style="display:inline-block;width:10px;height:10px;background-color:#16a34a;border-radius:50;">&nbsp;</span>
					</td>
					<td style="font-size:10pt;padding:3px 0;color:#2d3748;"><?php echo esc_html( $win ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endif; ?>

	<!-- Watch Items -->
	<?php if ( ! empty( $watch_items ) ) : ?>
		<div style="font-size:10pt;font-weight:bold;color:#1a2332;margin-bottom:6px;">Items to Watch</div>
		<table style="width:100%;border-collapse:collapse;margin-bottom:14px;">
			<?php foreach ( array_slice( $watch_items, 0, 3 ) as $watch ) : ?>
				<tr>
					<td style="width:18px;vertical-align:top;padding:4px 8px 4px 0;">
						<span style="display:inline-block;width:10px;height:10px;background-color:#d97706;border-radius:50;">&nbsp;</span>
					</td>
					<td style="font-size:10pt;padding:3px 0;color:#2d3748;"><?php echo esc_html( $watch ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endif; ?>

	<!-- Key Recommendation Callout -->
	<?php if ( ! empty( $recommendations[0] ) ) : ?>
		<div class="callout-box">
			<div class="callout-title">Key Recommendation: <?php echo esc_html( $recommendations[0]['title'] ?? '' ); ?></div>
			<div class="callout-text"><?php echo esc_html( $recommendations[0]['rationale'] ?? '' ); ?></div>
		</div>
	<?php endif; ?>

</div><!-- .content -->

<!-- Footer -->
<div class="footer-bar">
	<span class="footer-brand">WHAM</span> &mdash; Web Hosting &amp; Maintenance by Clear Phosphor &nbsp;&bull;&nbsp; <?php echo esc_html( $period_label ); ?>
</div>

<div style="page-break-after: always;"></div>

<!-- ============================================================ -->
<!-- PAGE 2: Search & Discovery (GSC)                             -->
<!-- ============================================================ -->

<div style="page-break-before: always;"></div>

<div class="content">
	<div class="section-title">Search &amp; Discovery</div>

	<?php if ( $gsc_error ) : ?>
		<div class="notice"><?php echo esc_html( $gsc_error ); ?></div>
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
						$pos_change    = $prev_position - $gsc_position;
						$pos_color     = $pos_change >= 0 ? '#16a34a' : '#dc2626';
						$pos_arrow     = $pos_change >= 0 ? '&#9650;' : '&#9660;';
						?>
						<div class="metric-change"><span style="font-size:8pt;color:<?php echo $pos_color; ?>;font-weight:bold;"><?php echo $pos_arrow; ?> <?php echo abs( round( $pos_change, 1 ) ); ?></span> vs prior</div>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<!-- GSC Trend Chart -->
		<?php if ( ! empty( $charts['gsc_trend'] ) ) : ?>
			<div class="chart-wrap">
				<img src="file://<?php echo $charts['gsc_trend']; ?>" style="width:100%;max-width:520px;">
			</div>
		<?php endif; ?>

		<!-- Top Queries Table -->
		<?php if ( ! empty( $gsc_top_queries ) ) : ?>
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
					<?php foreach ( array_slice( $gsc_top_queries, 0, 8 ) as $i => $q ) : ?>
						<tr<?php echo $i % 2 ? ' class="alt"' : ''; ?>>
							<td><?php echo esc_html( $q['query'] ?? '' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $q['clicks'] ?? 0 ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $q['impressions'] ?? 0 ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( isset( $q['ctr'] ) ? round( $q['ctr'], 1 ) . '%' : '—' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( isset( $q['position'] ) ? round( $q['position'], 1 ) : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<!-- Top Pages Table -->
		<?php if ( ! empty( $gsc_top_pages ) ) : ?>
			<table class="data-table">
				<thead>
					<tr>
						<th>Top Pages</th>
						<th style="text-align:right;">Clicks</th>
						<th style="text-align:right;">Impressions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $gsc_top_pages, 0, 5 ) as $i => $p ) : ?>
						<tr<?php echo $i % 2 ? ' class="alt"' : ''; ?>>
							<td style="word-break:break-all;max-width:300px;"><?php echo esc_html( $p['page'] ?? '' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $p['clicks'] ?? 0 ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $p['impressions'] ?? 0 ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	<?php endif; ?>
</div>

<!-- Footer -->
<div class="footer-bar">
	<span class="footer-brand">WHAM</span> &mdash; Web Hosting &amp; Maintenance by Clear Phosphor &nbsp;&bull;&nbsp; <?php echo esc_html( $period_label ); ?>
</div>

<!-- ============================================================ -->
<!-- PAGE 3: Website Traffic (GA4)                                -->
<!-- ============================================================ -->

<div style="page-break-before: always;"></div>

<div class="content">
	<div class="section-title">Website Traffic</div>

	<?php if ( $ga4_error ) : ?>
		<div class="notice"><?php echo esc_html( $ga4_error ); ?></div>
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
						$bounce_color  = $bounce_change <= 0 ? '#16a34a' : '#dc2626';
						$bounce_arrow  = $bounce_change <= 0 ? '&#9660;' : '&#9650;';
						?>
						<div class="metric-change"><span style="font-size:8pt;color:<?php echo $bounce_color; ?>;font-weight:bold;"><?php echo $bounce_arrow; ?> <?php echo abs( $bounce_change ); ?>%</span> vs prior</div>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<!-- Traffic Sources Chart -->
		<?php if ( ! empty( $charts['ga4_sources'] ) ) : ?>
			<div style="font-size:9pt;font-weight:bold;color:#1a2332;margin-bottom:6px;">Traffic Sources</div>
			<div class="chart-wrap">
				<img src="file://<?php echo $charts['ga4_sources']; ?>" style="width:100%;max-width:520px;">
			</div>
		<?php endif; ?>

		<!-- Sessions Trend Chart -->
		<?php if ( ! empty( $charts['ga4_trend'] ) ) : ?>
			<div style="font-size:9pt;font-weight:bold;color:#1a2332;margin-bottom:6px;">Sessions Trend</div>
			<div class="chart-wrap">
				<img src="file://<?php echo $charts['ga4_trend']; ?>" style="width:100%;max-width:520px;">
			</div>
		<?php endif; ?>

		<!-- Top Landing Pages Table -->
		<?php if ( ! empty( $ga4_pages ) ) : ?>
			<table class="data-table">
				<thead>
					<tr>
						<th>Top Landing Pages</th>
						<th style="text-align:right;">Sessions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $ga4_pages, 0, 5 ) as $i => $pg ) : ?>
						<tr<?php echo $i % 2 ? ' class="alt"' : ''; ?>>
							<td style="word-break:break-all;max-width:350px;"><?php echo esc_html( $pg['landingPagePlusQueryString'] ?? $pg['page'] ?? '' ); ?></td>
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
	<span class="footer-brand">WHAM</span> &mdash; Web Hosting &amp; Maintenance by Clear Phosphor &nbsp;&bull;&nbsp; <?php echo esc_html( $period_label ); ?>
</div>

<!-- ============================================================ -->
<!-- PAGE 4: Technical Health + Dev Hours                         -->
<!-- ============================================================ -->

<div style="page-break-before: always;"></div>

<div class="content">

	<!-- Technical Health -->
	<div class="section-title">Technical Health</div>

	<?php if ( $maint_error ) : ?>
		<div class="notice"><?php echo esc_html( $maint_error ); ?></div>
	<?php else : ?>

		<table class="metrics-table">
			<tr>
				<td class="metric-cell">
					<div class="metric-value <?php echo $wp_up_to_date ? 'metric-value-green' : 'metric-value-amber'; ?>"><?php echo esc_html( $wp_version ); ?></div>
					<div class="metric-label">WordPress</div>
					<div class="metric-change"><span class="<?php echo $wp_up_to_date ? 'badge-green' : 'badge-amber'; ?>"><?php echo $wp_up_to_date ? 'Current' : 'Update Available'; ?></span></div>
				</td>
				<td class="metric-cell">
					<div class="metric-value <?php echo 0 === $plugins_updates ? 'metric-value-green' : 'metric-value-amber'; ?>"><?php echo $plugins_updated; ?>/<?php echo $plugins_total; ?></div>
					<div class="metric-label">Plugins Updated</div>
					<div class="metric-change"><span class="<?php echo 0 === $plugins_updates ? 'badge-green' : 'badge-amber'; ?>"><?php echo 0 === $plugins_updates ? 'All Current' : $plugins_updates . ' Pending'; ?></span></div>
				</td>
				<td class="metric-cell">
					<div class="metric-value <?php echo $theme_current ? 'metric-value-green' : 'metric-value-amber'; ?>"><?php echo esc_html( $theme_version ); ?></div>
					<div class="metric-label"><?php echo esc_html( $theme_name ); ?></div>
					<div class="metric-change"><span class="<?php echo $theme_current ? 'badge-green' : 'badge-amber'; ?>"><?php echo $theme_current ? 'Current' : 'Update Available'; ?></span></div>
				</td>
				<td class="metric-cell">
					<div class="metric-value metric-value-green"><?php echo esc_html( $php_version ); ?></div>
					<div class="metric-label">PHP</div>
				</td>
			</tr>
		</table>

		<!-- Plugin Update Detail Table -->
		<?php if ( ! empty( $plugins_needing ) ) : ?>
			<table class="data-table" style="margin-bottom:18px;">
				<thead>
					<tr>
						<th>Plugin</th>
						<th style="text-align:right;">Current Version</th>
						<th style="text-align:right;">Available Version</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $plugins_needing as $i => $plugin ) : ?>
						<tr<?php echo $i % 2 ? ' class="alt"' : ''; ?>>
							<td><?php echo esc_html( $plugin['name'] ?? $plugin['plugin'] ?? '' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $plugin['current_version'] ?? $plugin['version'] ?? '—' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( $plugin['new_version'] ?? '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	<?php endif; ?>

	<!-- Development Activity -->
	<div class="section-title" style="margin-top:20px;">Development Activity</div>

	<?php if ( $dev_error ) : ?>
		<div class="notice"><?php echo esc_html( $dev_error ); ?></div>
	<?php else : ?>

		<?php
		$pct       = $hours_included > 0 ? min( 100, round( ( $hours_used / $hours_included ) * 100 ) ) : 0;
		$bar_color = $pct > 90 ? '#dc2626' : ( $pct > 70 ? '#d97706' : '#16a34a' );
		?>

		<!-- Hours KPI Cards -->
		<table class="metrics-table">
			<tr>
				<td class="metric-cell">
					<div class="metric-value"><?php echo esc_html( $hours_used ); ?></div>
					<div class="metric-label">Hours Used</div>
				</td>
				<td class="metric-cell">
					<div class="metric-value"><?php echo esc_html( $hours_included ); ?></div>
					<div class="metric-label">Included</div>
				</td>
				<td class="metric-cell">
					<div class="metric-value <?php echo $hours_remaining > 0 ? 'metric-value-green' : 'metric-value-red'; ?>"><?php echo esc_html( $hours_remaining ); ?></div>
					<div class="metric-label">Remaining</div>
				</td>
			</tr>
		</table>

		<!-- Dev Hours Doughnut Chart -->
		<?php if ( ! empty( $charts['dev_hours'] ) ) : ?>
			<div class="chart-wrap">
				<img src="file://<?php echo $charts['dev_hours']; ?>" style="width:100%;max-width:240px;">
			</div>
		<?php endif; ?>

		<!-- Progress Bar (table-based) -->
		<table style="width:100%;border-collapse:collapse;">
			<tr>
				<td style="padding:0;">
					<div class="hours-bar-outer">
						<div class="hours-bar-inner" style="width: <?php echo $pct; ?>%; background-color: <?php echo $bar_color; ?>;"></div>
					</div>
				</td>
			</tr>
		</table>
		<div class="hours-pct-label"><?php echo $pct; ?>% of included hours used</div>

		<?php if ( $work_summary ) : ?>
			<div class="work-summary">
				<strong>Work Performed:</strong> <?php echo esc_html( $work_summary ); ?>
			</div>
		<?php endif; ?>

	<?php endif; ?>

</div>

<!-- Footer -->
<div class="footer-bar">
	<span class="footer-brand">WHAM</span> &mdash; Web Hosting &amp; Maintenance by Clear Phosphor &nbsp;&bull;&nbsp; <?php echo esc_html( $period_label ); ?>
</div>

<!-- ============================================================ -->
<!-- PAGE 5: Recommendations (only if non-empty)                  -->
<!-- ============================================================ -->

<?php if ( ! empty( $recommendations ) ) : ?>

<div style="page-break-before: always;"></div>

<div class="content">
	<div class="section-title">Recommendations</div>

	<?php foreach ( array_slice( $recommendations, 0, 4 ) as $i => $rec ) : ?>
		<div class="rec-item">
			<span class="rec-number"><?php echo $i + 1; ?></span>
			<span class="rec-title"><?php echo esc_html( $rec['title'] ?? '' ); ?></span>
			<?php if ( ! empty( $rec['rationale'] ) ) : ?>
				<div class="rec-rationale"><?php echo esc_html( $rec['rationale'] ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $rec['impact'] ) ) : ?>
				<div class="rec-impact">Expected Impact: <?php echo esc_html( $rec['impact'] ); ?></div>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>

<!-- Footer -->
<div class="footer-bar">
	<span class="footer-brand">WHAM</span> &mdash; Web Hosting &amp; Maintenance by Clear Phosphor &nbsp;&bull;&nbsp; <?php echo esc_html( $period_label ); ?>
</div>

<?php endif; ?>

</body>
</html>
