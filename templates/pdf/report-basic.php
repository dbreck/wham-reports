<?php
/**
 * WHAM Report — Basic Tier PDF Template
 * Single-page factual summary: maintenance + search clicks.
 * Uses Editorial style (cleanest for 1-page).
 *
 * Variables available: $report (array with all report data)
 */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/helpers.php';

$client       = $report['client'] ?? [];
$maintenance  = $report['maintenance'] ?? [];
$search       = $report['search'] ?? [];
$period_label = $report['period_label'] ?? date( 'F Y' );

// Maintenance data.
$wp_version      = $maintenance['wp_version'] ?? 'N/A';
$plugins_total   = (int) ( $maintenance['plugins_total'] ?? 0 );
$plugins_updates = (int) ( $maintenance['plugins_updates_count'] ?? 0 );
$plugins_updated = $plugins_total - $plugins_updates;
$php_version     = $maintenance['php_version'] ?? 'N/A';

// Search data.
$clicks        = $search['clicks'] ?? null;
$clicks_change = $search['comparison']['clicks_change'] ?? null;
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
		background-color: #0f172a;
		color: #ffffff;
		padding: 28px 50px 24px 50px;
	}
	.header-table { width: 100%; border-collapse: collapse; }
	.header-table td { vertical-align: bottom; padding: 0; }
	.brand-name { font-size: 30pt; font-weight: bold; letter-spacing: 2px; color: #ffffff; line-height: 1; }
	.brand-tagline { font-size: 7pt; color: #8899aa; letter-spacing: 1px; text-transform: uppercase; padding-top: 3px; }
	.header-right { text-align: right; }
	.client-name { font-size: 14pt; font-weight: bold; color: #ffffff; line-height: 1.2; }
	.header-detail { font-size: 8pt; color: #8899aa; line-height: 1.6; }
	.plan-badge { display: inline-block; background-color: #1e293b; color: #8cb4d8; font-size: 7pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; padding: 2px 10px; border-radius: 3px; margin-top: 4px; }

	/* Content */
	.content { padding: 28px 50px 20px 50px; }

	/* Section titles */
	.section-title {
		font-size: 12pt;
		font-weight: bold;
		color: #0f172a;
		padding-bottom: 6px;
		margin-bottom: 16px;
		border-bottom: 2px solid #2563eb;
	}

	/* Metric cards */
	.metrics-table { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 16px; margin-left: -8px; }
	.metric-cell { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 10px; text-align: center; vertical-align: top; }
	.metric-value { font-size: 20pt; font-weight: bold; line-height: 1.1; color: #0f172a; }
	.metric-label { font-size: 7pt; text-transform: uppercase; letter-spacing: 1px; color: #718096; padding-top: 4px; }
	.metric-change { font-size: 8pt; padding-top: 2px; color: #718096; }

	/* Footer */
	.footer-bar { padding: 14px 50px; background-color: #0f172a; color: #8899aa; font-size: 7pt; text-align: center; }
	.footer-bar .footer-brand { font-weight: bold; color: #ffffff; letter-spacing: 1px; }
</style>
</head>
<body>

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
				<div class="plan-badge">Basic Plan</div>
			</td>
		</tr>
	</table>
</div>

<div class="content">

	<!-- Updates & Maintenance -->
	<div class="section-title">Updates &amp; Maintenance</div>

	<table class="metrics-table">
		<tr>
			<td class="metric-cell">
				<div class="metric-value"><?php echo esc_html( $wp_version ); ?></div>
				<div class="metric-label">WordPress</div>
			</td>
			<td class="metric-cell">
				<div class="metric-value"><?php echo esc_html( $plugins_updated . '/' . $plugins_total ); ?></div>
				<div class="metric-label">Plugins Updated</div>
			</td>
			<td class="metric-cell">
				<div class="metric-value"><?php echo esc_html( $php_version ); ?></div>
				<div class="metric-label">PHP Version</div>
			</td>
		</tr>
	</table>

	<!-- Search Clicks (if available) -->
	<?php if ( null !== $clicks ) : ?>
	<div class="section-title" style="margin-top: 24px;">Search Performance</div>

	<table class="metrics-table">
		<tr>
			<td class="metric-cell" style="width: 50%;">
				<div class="metric-value"><?php echo wham_format_number( $clicks ); ?></div>
				<div class="metric-label">Search Clicks</div>
				<?php if ( null !== $clicks_change && $clicks_change != 0 ) : ?>
					<div class="metric-change">
						<?php
						$prefix = $clicks_change > 0 ? '+' : '-';
						$color  = $clicks_change > 0 ? '#059669' : '#dc2626';
						?>
						<span style="font-size:8pt;color:<?php echo $color; ?>;font-weight:bold;"><?php echo $prefix . abs( round( $clicks_change ) ); ?>%</span> vs prior month
					</div>
				<?php endif; ?>
			</td>
			<td class="metric-cell" style="width: 50%;">
				<div class="metric-value"><?php echo wham_format_number( $search['impressions'] ?? 0 ); ?></div>
				<div class="metric-label">Search Impressions</div>
			</td>
		</tr>
	</table>
	<?php endif; ?>

</div>

<!-- Footer -->
<div class="footer-bar">
	<span class="footer-brand">WHAM</span> &mdash; Web Hosting &amp; Maintenance by Clear pH &nbsp;&bull;&nbsp; <?php echo esc_html( $period_label ); ?>
	<br>Questions? Contact us at support@clearph.com
</div>

</body>
</html>
