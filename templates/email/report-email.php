<?php
/**
 * WHAM Report Email Template — Data-Rich Inline Report
 *
 * Variables available:
 *   $client_name  (string) — Client display name
 *   $period_label (string) — e.g. "March 2026"
 *   $tier         (string) — basic / professional / premium
 *   $report_id    (int)    — wham_report post ID
 *   $report_data  (array)  — Full report data (maintenance, search, analytics, charts)
 */
defined( 'ABSPATH' ) || exit;

$dashboard_url       = $dashboard_url ?? \WHAM_Reports::get_report_dashboard_url( intval( $report_id ) );
$has_pdf_attachment  = null !== \WHAM_Reports::get_report_pdf_path( intval( $report_id ) );
$logo_url            = \WHAM_Reports::get_brand_logo_url();
$period_meta         = $report_data['period'] ?? [];
$period_start        = $period_meta['start_date'] ?? '';
$period_end          = $period_meta['end_date'] ?? '';
$comparison_start    = $period_meta['comparison_start_date'] ?? '';
$comparison_end      = $period_meta['comparison_end_date'] ?? '';
$tier_label          = ucfirst( (string) $tier ) . ' Plan';

// Extract report sections.
$maintenance = $report_data['maintenance'] ?? [];
$search      = $report_data['search'] ?? [];
$analytics   = $report_data['analytics'] ?? [];

// Maintenance data.
$wp_version      = $maintenance['wp_version'] ?? 'N/A';
$plugins_total   = (int) ( $maintenance['plugins_total'] ?? 0 );
$plugins_updates = (int) ( $maintenance['plugins_updates_count'] ?? 0 );
$plugins_current = $plugins_total - $plugins_updates;
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

// Prev GSC.
$prev_clicks      = $gsc_comparison['prev_clicks'] ?? 0;
$prev_impressions = $gsc_comparison['prev_impressions'] ?? 0;
$prev_ctr         = $gsc_comparison['prev_ctr'] ?? 0;
$prev_position    = $gsc_comparison['prev_position'] ?? 0;

/**
 * Format a number with K/M suffix for email display.
 */
if ( ! function_exists( 'wham_email_number' ) ) {
	function wham_email_number( $n ) {
		if ( $n >= 1000000 ) return round( $n / 1000000, 1 ) . 'M';
		if ( $n >= 1000 )    return round( $n / 1000, 1 ) . 'K';
		return number_format( $n );
	}
}

/**
 * MoM change as colored inline text for email.
 */
if ( ! function_exists( 'wham_email_change' ) ) {
	function wham_email_change( $current, $previous, $invert = false ) {
		if ( ! $previous ) return '';
		$change = round( ( ( $current - $previous ) / $previous ) * 100, 1 );
		$prefix = $change >= 0 ? '+' : '';
		$positive = $invert ? ( $change <= 0 ) : ( $change >= 0 );
		$color  = $positive ? '#059669' : '#dc2626';
		return '<span style="font-size:12px;color:' . $color . ';font-weight:600;">' . $prefix . $change . '%</span>';
	}
}

/**
 * Format a YYYY-MM-DD range for email labels.
 */
if ( ! function_exists( 'wham_email_date_range' ) ) {
	function wham_email_date_range( $start, $end ) {
		if ( empty( $start ) || empty( $end ) ) {
			return '';
		}

		$start_ts = strtotime( $start );
		$end_ts   = strtotime( $end );
		if ( ! $start_ts || ! $end_ts ) {
			return '';
		}

		if ( gmdate( 'Y-m', $start_ts ) === gmdate( 'Y-m', $end_ts ) ) {
			return gmdate( 'M j', $start_ts ) . '-' . gmdate( 'j, Y', $end_ts );
		}

		return gmdate( 'M j, Y', $start_ts ) . ' - ' . gmdate( 'M j, Y', $end_ts );
	}
}

$report_window     = wham_email_date_range( $period_start, $period_end );
$comparison_window = wham_email_date_range( $comparison_start, $comparison_end );
$window_meta       = $report_window;

if ( $window_meta && $comparison_window ) {
	$window_meta .= ' | Compared with ' . $comparison_window;
}
?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>WHAM Report — <?php echo esc_html( $period_label ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">

<!-- Wrapper -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eef2f7;">
<tr><td align="center" style="padding:24px 16px;">

<!-- Container 600px -->
<table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff;max-width:640px;width:100%;border:1px solid #dbe4f0;">

	<!-- ============================================================ -->
	<!-- HEADER                                                        -->
	<!-- ============================================================ -->
	<tr>
		<td style="background-color:#ffffff;padding:28px 32px 22px 32px;border-bottom:1px solid #dbe4f0;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td style="vertical-align:middle;">
						<?php if ( $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="WHAM" width="176" style="display:block;width:176px;max-width:100%;height:auto;border:0;">
						<?php else : ?>
							<span style="font-size:26px;font-weight:800;color:#0f172a;letter-spacing:2px;">WHAM</span>
							<br>
							<span style="font-size:10px;text-transform:uppercase;letter-spacing:1.8px;color:#64748b;">Web Hosting &amp; Maintenance</span>
						<?php endif; ?>
					</td>
					<td align="right" style="vertical-align:middle;">
						<span style="display:inline-block;padding:9px 14px;background-color:#0f172a;border:1px solid #243149;font-size:11px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#f8fafc;"><?php echo esc_html( $period_label ); ?></span>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<!-- Client name bar -->
	<tr>
		<td style="background-color:#111827;padding:18px 32px;border-top:4px solid #2563eb;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td style="vertical-align:middle;">
						<span style="font-size:22px;font-weight:700;color:#ffffff;line-height:1.2;"><?php echo esc_html( $client_name ); ?></span>
					</td>
					<td align="right" style="vertical-align:middle;">
						<span style="display:inline-block;padding:6px 10px;border:1px solid #8b5cf6;font-size:10px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#ede9fe;background-color:#1f1738;"><?php echo esc_html( $tier_label ); ?></span>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<!-- ============================================================ -->
	<!-- GREETING                                                      -->
	<!-- ============================================================ -->
	<tr>
		<td style="padding:28px 32px 20px 32px;">
			<p style="font-size:14px;color:#475569;line-height:1.6;margin:0 0 10px 0;">
				Here's your <?php echo esc_html( $period_label ); ?> website report.
			</p>
			<?php if ( $window_meta ) : ?>
			<p style="font-size:12px;color:#64748b;line-height:1.5;margin:0;">
				Report window: <?php echo esc_html( $window_meta ); ?>
			</p>
			<?php endif; ?>
		</td>
	</tr>

	<!-- ============================================================ -->
	<!-- SECTION: MAINTENANCE                                          -->
	<!-- ============================================================ -->
	<tr>
		<td style="padding:0 32px;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td style="background-color:#f6f8fb;padding:12px 16px;border-left:4px solid #2563eb;border-bottom:1px solid #dbe4f0;">
						<span style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#0f172a;">Maintenance</span>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<?php if ( $maint_error ) : ?>
	<tr>
		<td style="padding:16px 32px;">
			<p style="font-size:13px;color:#64748b;margin:0;"><?php echo esc_html( $maint_error ); ?></p>
		</td>
	</tr>
	<?php else : ?>
	<!-- 3-column maintenance metrics -->
	<tr>
		<td style="padding:20px 32px 8px 32px;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td width="33%" align="center" style="padding:16px 8px;border:1px solid #e2e8f0;border-right:none;">
						<span style="font-size:22px;font-weight:700;color:#0f172a;font-family:Courier,monospace;"><?php echo esc_html( $wp_version ); ?></span>
						<br>
						<span style="font-size:10px;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;">WordPress</span>
					</td>
					<td width="34%" align="center" style="padding:16px 8px;border:1px solid #e2e8f0;border-right:none;">
						<span style="font-size:22px;font-weight:700;color:#0f172a;font-family:Courier,monospace;"><?php echo esc_html( $plugins_current . '/' . $plugins_total ); ?></span>
						<br>
						<span style="font-size:10px;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;">Plugins Current</span>
					</td>
					<td width="33%" align="center" style="padding:16px 8px;border:1px solid #e2e8f0;">
						<span style="font-size:22px;font-weight:700;color:#0f172a;font-family:Courier,monospace;"><?php echo esc_html( $php_version ); ?></span>
						<br>
						<span style="font-size:10px;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;">PHP Version</span>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<!-- Plugin updates table (pro tier) -->
	<?php if ( wham_tier_has( $tier, 'maintenance_detail' ) && ! empty( $plugins_needing ) ) : ?>
	<tr>
		<td style="padding:12px 32px 20px 32px;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:13px;">
				<tr>
					<td style="padding:8px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#0f172a;border-bottom:2px solid #0f172a;">Plugin</td>
					<td align="right" style="padding:8px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#0f172a;border-bottom:2px solid #0f172a;">Installed</td>
					<td align="right" style="padding:8px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#0f172a;border-bottom:2px solid #0f172a;">Available</td>
				</tr>
				<?php foreach ( array_slice( $plugins_needing, 0, 8 ) as $plugin ) : ?>
				<tr>
					<td style="padding:6px 12px;border-bottom:1px solid #f1f5f9;color:#334155;"><?php echo esc_html( $plugin['name'] ?? $plugin['plugin'] ?? '' ); ?></td>
					<td align="right" style="padding:6px 12px;border-bottom:1px solid #f1f5f9;color:#64748b;font-family:Courier,monospace;font-size:12px;"><?php echo esc_html( $plugin['current_version'] ?? $plugin['version'] ?? '--' ); ?></td>
					<td align="right" style="padding:6px 12px;border-bottom:1px solid #f1f5f9;color:#64748b;font-family:Courier,monospace;font-size:12px;"><?php echo esc_html( $plugin['new_version'] ?? '--' ); ?></td>
				</tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr>
	<?php endif; ?>
	<?php endif; ?>

	<?php if ( wham_tier_has( $tier, 'gsc_aggregate' ) ) : ?>
	<!-- ============================================================ -->
	<!-- SECTION: SEARCH PERFORMANCE                                   -->
	<!-- ============================================================ -->
	<tr>
		<td style="padding:12px 32px 0 32px;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td style="background-color:#f6f8fb;padding:12px 16px;border-left:4px solid #10b981;border-bottom:1px solid #dbe4f0;">
						<span style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#0f172a;">Search Performance</span>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<?php if ( $gsc_error ) : ?>
	<tr>
		<td style="padding:16px 32px;">
			<p style="font-size:13px;color:#64748b;margin:0;"><?php echo esc_html( $gsc_error ); ?></p>
		</td>
	</tr>
	<?php else : ?>
	<!-- 4-column GSC KPIs -->
	<tr>
		<td style="padding:20px 32px 8px 32px;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td width="25%" align="center" style="padding:14px 4px;border:1px solid #e2e8f0;border-right:none;">
						<span style="font-size:20px;font-weight:700;color:#0f172a;font-family:Courier,monospace;"><?php echo wham_email_number( $gsc_clicks ); ?></span>
						<br>
						<span style="font-size:9px;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;">Clicks</span>
						<?php if ( $prev_clicks ) : ?>
						<br><?php echo wham_email_change( $gsc_clicks, $prev_clicks ); ?>
						<?php endif; ?>
					</td>
					<td width="25%" align="center" style="padding:14px 4px;border:1px solid #e2e8f0;border-right:none;">
						<span style="font-size:20px;font-weight:700;color:#0f172a;font-family:Courier,monospace;"><?php echo wham_email_number( $gsc_impressions ); ?></span>
						<br>
						<span style="font-size:9px;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;">Impressions</span>
						<?php if ( $prev_impressions ) : ?>
						<br><?php echo wham_email_change( $gsc_impressions, $prev_impressions ); ?>
						<?php endif; ?>
					</td>
					<td width="25%" align="center" style="padding:14px 4px;border:1px solid #e2e8f0;border-right:none;">
						<span style="font-size:20px;font-weight:700;color:#0f172a;font-family:Courier,monospace;"><?php echo esc_html( $gsc_ctr ); ?>%</span>
						<br>
						<span style="font-size:9px;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;">Avg CTR</span>
						<?php if ( $prev_ctr ) : ?>
						<br><?php echo wham_email_change( $gsc_ctr, $prev_ctr ); ?>
						<?php endif; ?>
					</td>
					<td width="25%" align="center" style="padding:14px 4px;border:1px solid #e2e8f0;">
						<span style="font-size:20px;font-weight:700;color:#0f172a;font-family:Courier,monospace;"><?php echo esc_html( $gsc_position ); ?></span>
						<br>
						<span style="font-size:9px;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;">Avg Position</span>
						<?php if ( $prev_position ) : ?>
						<br>
						<?php
						$pos_change = $prev_position - $gsc_position;
						$pos_color  = $pos_change >= 0 ? '#059669' : '#dc2626';
						$pos_prefix = $pos_change >= 0 ? '+' : '-';
						?>
						<span style="font-size:12px;color:<?php echo $pos_color; ?>;font-weight:600;"><?php echo $pos_prefix . abs( round( $pos_change, 1 ) ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<!-- GSC Trend Chart -->
	<?php if ( wham_tier_has( $tier, 'gsc_trend' ) && ! empty( $chart_urls['gsc_trend'] ) ) : ?>
	<tr>
		<td style="padding:12px 32px 0 32px;">
			<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.4px;color:#64748b;margin:0;">
				Daily Search Trend<?php echo $report_window ? ' | ' . esc_html( $report_window ) : ''; ?>
			</p>
		</td>
	</tr>
	<tr>
		<td align="center" style="padding:16px 32px;">
			<img src="<?php echo esc_url( $chart_urls['gsc_trend'] ); ?>" alt="Search trend chart" width="536" style="display:block;max-width:100%;height:auto;border:1px solid #e2e8f0;">
		</td>
	</tr>
	<?php endif; ?>

	<!-- Top Queries Table -->
	<?php if ( wham_tier_has( $tier, 'gsc_top_queries' ) && ! empty( $gsc_top_queries ) ) : ?>
	<tr>
		<td style="padding:8px 32px 20px 32px;">
			<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#64748b;margin:0 0 8px 0;">Top Search Queries</p>
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:13px;">
				<tr>
					<td style="padding:6px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#0f172a;border-bottom:2px solid #0f172a;">Query</td>
					<td align="right" style="padding:6px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#0f172a;border-bottom:2px solid #0f172a;">Clicks</td>
					<td align="right" style="padding:6px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#0f172a;border-bottom:2px solid #0f172a;">Impr.</td>
					<td align="right" style="padding:6px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#0f172a;border-bottom:2px solid #0f172a;">CTR</td>
				</tr>
				<?php foreach ( array_slice( $gsc_top_queries, 0, 5 ) as $q ) : ?>
				<tr>
					<td style="padding:5px 12px;border-bottom:1px solid #f1f5f9;color:#334155;"><?php echo esc_html( $q['query'] ?? '' ); ?></td>
					<td align="right" style="padding:5px 12px;border-bottom:1px solid #f1f5f9;color:#64748b;font-family:Courier,monospace;font-size:12px;"><?php echo esc_html( $q['clicks'] ?? 0 ); ?></td>
					<td align="right" style="padding:5px 12px;border-bottom:1px solid #f1f5f9;color:#64748b;font-family:Courier,monospace;font-size:12px;"><?php echo esc_html( $q['impressions'] ?? 0 ); ?></td>
					<td align="right" style="padding:5px 12px;border-bottom:1px solid #f1f5f9;color:#64748b;font-family:Courier,monospace;font-size:12px;"><?php echo esc_html( isset( $q['ctr'] ) ? round( $q['ctr'], 1 ) . '%' : '--' ); ?></td>
				</tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr>
	<?php endif; ?>

	<!-- Top Pages Table -->
	<?php if ( wham_tier_has( $tier, 'gsc_top_pages' ) && ! empty( $gsc_top_pages ) ) : ?>
	<tr>
		<td style="padding:0 32px 20px 32px;">
			<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#64748b;margin:0 0 8px 0;">Top Pages</p>
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:13px;">
				<tr>
					<td style="padding:6px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#0f172a;border-bottom:2px solid #0f172a;">Page</td>
					<td align="right" style="padding:6px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#0f172a;border-bottom:2px solid #0f172a;">Clicks</td>
					<td align="right" style="padding:6px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#0f172a;border-bottom:2px solid #0f172a;">Impr.</td>
				</tr>
				<?php foreach ( array_slice( $gsc_top_pages, 0, 5 ) as $p ) : ?>
				<tr>
					<td style="padding:5px 12px;border-bottom:1px solid #f1f5f9;color:#334155;max-width:300px;word-break:break-all;"><?php echo esc_html( $p['page'] ?? '' ); ?></td>
					<td align="right" style="padding:5px 12px;border-bottom:1px solid #f1f5f9;color:#64748b;font-family:Courier,monospace;font-size:12px;"><?php echo esc_html( $p['clicks'] ?? 0 ); ?></td>
					<td align="right" style="padding:5px 12px;border-bottom:1px solid #f1f5f9;color:#64748b;font-family:Courier,monospace;font-size:12px;"><?php echo esc_html( $p['impressions'] ?? 0 ); ?></td>
				</tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr>
	<?php endif; ?>
	<?php endif; /* gsc_error */ ?>
	<?php endif; /* gsc_aggregate */ ?>

	<?php if ( wham_tier_has( $tier, 'ga4_core' ) ) : ?>
	<!-- ============================================================ -->
	<!-- SECTION: WEBSITE TRAFFIC                                      -->
	<!-- ============================================================ -->
	<tr>
		<td style="padding:12px 32px 0 32px;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td style="background-color:#f6f8fb;padding:12px 16px;border-left:4px solid #8b5cf6;border-bottom:1px solid #dbe4f0;">
						<span style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#0f172a;">Website Traffic</span>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<?php if ( $ga4_error ) : ?>
	<tr>
		<td style="padding:16px 32px;">
			<p style="font-size:13px;color:#64748b;margin:0;"><?php echo esc_html( $ga4_error ); ?></p>
		</td>
	</tr>
	<?php else : ?>
	<!-- 4-column GA4 KPIs -->
	<tr>
		<td style="padding:20px 32px 8px 32px;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td width="25%" align="center" style="padding:14px 4px;border:1px solid #e2e8f0;border-right:none;">
						<span style="font-size:20px;font-weight:700;color:#0f172a;font-family:Courier,monospace;"><?php echo wham_email_number( $ga4_sessions ); ?></span>
						<br>
						<span style="font-size:9px;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;">Sessions</span>
						<?php if ( $ga4_prev_sess ) : ?>
						<br><?php echo wham_email_change( $ga4_sessions, $ga4_prev_sess ); ?>
						<?php endif; ?>
					</td>
					<td width="25%" align="center" style="padding:14px 4px;border:1px solid #e2e8f0;border-right:none;">
						<span style="font-size:20px;font-weight:700;color:#0f172a;font-family:Courier,monospace;"><?php echo wham_email_number( $ga4_users ); ?></span>
						<br>
						<span style="font-size:9px;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;">Users</span>
						<?php if ( $ga4_prev_users ) : ?>
						<br><?php echo wham_email_change( $ga4_users, $ga4_prev_users ); ?>
						<?php endif; ?>
					</td>
					<td width="25%" align="center" style="padding:14px 4px;border:1px solid #e2e8f0;border-right:none;">
						<span style="font-size:20px;font-weight:700;color:#0f172a;font-family:Courier,monospace;"><?php echo wham_email_number( $ga4_pageviews ); ?></span>
						<br>
						<span style="font-size:9px;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;">Pageviews</span>
						<?php if ( $ga4_prev_pv ) : ?>
						<br><?php echo wham_email_change( $ga4_pageviews, $ga4_prev_pv ); ?>
						<?php endif; ?>
					</td>
					<td width="25%" align="center" style="padding:14px 4px;border:1px solid #e2e8f0;">
						<span style="font-size:20px;font-weight:700;color:#0f172a;font-family:Courier,monospace;"><?php echo esc_html( $ga4_bounce ); ?>%</span>
						<br>
						<span style="font-size:9px;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;">Bounce Rate</span>
						<?php if ( $ga4_prev_bounce ) : ?>
						<br><?php echo wham_email_change( $ga4_bounce, $ga4_prev_bounce, true ); ?>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<!-- Traffic Sources Chart -->
	<?php if ( wham_tier_has( $tier, 'ga4_sources' ) && ! empty( $chart_urls['ga4_sources'] ) ) : ?>
	<tr>
		<td style="padding:12px 32px 0 32px;">
			<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.4px;color:#64748b;margin:0;">
				Traffic Sources<?php echo $report_window ? ' | ' . esc_html( $report_window ) : ''; ?>
			</p>
		</td>
	</tr>
	<tr>
		<td align="center" style="padding:16px 32px;">
			<img src="<?php echo esc_url( $chart_urls['ga4_sources'] ); ?>" alt="Traffic sources chart" width="536" style="display:block;max-width:100%;height:auto;border:1px solid #e2e8f0;">
		</td>
	</tr>
	<?php endif; ?>

	<!-- Sessions Trend Chart -->
	<?php if ( wham_tier_has( $tier, 'ga4_trend' ) && ! empty( $chart_urls['ga4_trend'] ) ) : ?>
	<tr>
		<td style="padding:0 32px;">
			<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.4px;color:#64748b;margin:0;">
				Sessions &amp; Users Trend<?php echo $report_window ? ' | ' . esc_html( $report_window ) : ''; ?>
			</p>
		</td>
	</tr>
	<tr>
		<td align="center" style="padding:0 32px 16px 32px;">
			<img src="<?php echo esc_url( $chart_urls['ga4_trend'] ); ?>" alt="Sessions trend chart" width="536" style="display:block;max-width:100%;height:auto;border:1px solid #e2e8f0;">
		</td>
	</tr>
	<?php endif; ?>

	<!-- Top Landing Pages -->
	<?php if ( wham_tier_has( $tier, 'ga4_landing_pages' ) && ! empty( $ga4_pages ) ) : ?>
	<tr>
		<td style="padding:8px 32px 20px 32px;">
			<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#64748b;margin:0 0 8px 0;">Top Landing Pages</p>
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:13px;">
				<tr>
					<td style="padding:6px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#0f172a;border-bottom:2px solid #0f172a;">Page</td>
					<td align="right" style="padding:6px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#0f172a;border-bottom:2px solid #0f172a;">Sessions</td>
				</tr>
				<?php foreach ( array_slice( $ga4_pages, 0, 5 ) as $pg ) : ?>
				<tr>
					<td style="padding:5px 12px;border-bottom:1px solid #f1f5f9;color:#334155;max-width:360px;word-break:break-all;"><?php echo esc_html( $pg['landingPagePlusQueryString'] ?? $pg['page'] ?? '' ); ?></td>
					<td align="right" style="padding:5px 12px;border-bottom:1px solid #f1f5f9;color:#64748b;font-family:Courier,monospace;font-size:12px;"><?php echo esc_html( $pg['metric_0'] ?? $pg['sessions'] ?? 0 ); ?></td>
				</tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr>
	<?php endif; ?>
	<?php endif; /* ga4_error */ ?>
	<?php endif; /* ga4_core */ ?>

	<!-- ============================================================ -->
	<!-- CTA: VIEW REPORT ONLINE                                       -->
	<!-- ============================================================ -->
	<?php if ( $dashboard_url || $has_pdf_attachment ) : ?>
	<tr>
		<td style="padding:16px 32px;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f6f8fb;border:1px solid #dbe4f0;padding:0;">
				<tr>
					<td align="center" style="padding:24px 20px;">
						<?php if ( $dashboard_url ) : ?>
							<a href="<?php echo esc_url( $dashboard_url ); ?>" style="display:inline-block;padding:12px 32px;background-color:#111827;border:1px solid #243149;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;letter-spacing:0.5px;">View Full Report Online</a>
						<?php endif; ?>
						<?php if ( $has_pdf_attachment ) : ?>
						<p style="font-size:12px;color:#64748b;margin:12px 0 0 0;">PDF report is attached to this email.</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?php endif; ?>

	<!-- ============================================================ -->
	<!-- FOOTER                                                        -->
	<!-- ============================================================ -->
	<tr>
		<td style="padding:22px 32px;border-top:1px solid #dbe4f0;background-color:#f9fbfd;">
			<p style="font-size:12px;color:#64748b;margin:0 0 4px 0;">
				Questions about your report? Just reply to this email.
			</p>
			<p style="font-size:11px;color:#94a3b8;margin:0;">
				WHAM | Web Hosting &amp; Maintenance by Clear pH
			</p>
		</td>
	</tr>

</table>
<!-- /Container -->

</td></tr>
</table>
<!-- /Wrapper -->

</body>
</html>
