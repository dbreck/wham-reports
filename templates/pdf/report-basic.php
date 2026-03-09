<?php
/**
 * WHAM Report — Basic Tier PDF Template
 * One-page health scorecard with traffic-light indicators.
 *
 * Variables available: $report (array with all report data)
 */
defined( 'ABSPATH' ) || exit;

$client       = $report['client'] ?? [];
$maintenance  = $report['maintenance'] ?? [];
$dev_hours    = $report['dev_hours'] ?? [];
$search       = $report['search'] ?? [];
$insights     = $report['insights'] ?? [];
$period_label = $report['period_label'] ?? date( 'F Y' );

// Maintenance data.
$wp_version     = $maintenance['wp_version'] ?? 'N/A';
$wp_up_to_date  = empty( $maintenance['wp_update_available'] );
$plugins_total  = $maintenance['plugins_total'] ?? 0;
$plugins_updates = (int) ( $maintenance['plugins_updates_count'] ?? 0 );
$theme_name     = $maintenance['theme_name'] ?? 'N/A';
$theme_current  = empty( $maintenance['theme_update_available'] );
$php_version    = $maintenance['php_version'] ?? 'N/A';

// Dev hours.
$hours_included  = (float) ( $dev_hours['hours_included'] ?? 0 );
$hours_used      = (float) ( $dev_hours['hours_used'] ?? 0 );
$hours_remaining = (float) ( $dev_hours['hours_remaining'] ?? 0 );

// Search data (may be empty for basic tier).
$clicks        = $search['clicks'] ?? null;
$clicks_change = $search['comparison']['clicks_change'] ?? null;

// Health scores.
$health_scores  = $insights['health_scores'] ?? [];
$overall_health = $insights['overall_health'] ?? 'green';
$exec_summary   = $insights['executive_summary'] ?? '';

// Helper: map score to colors.
$color_map = [
	'green' => [ 'bg' => '#f0fdf4', 'dot' => '#059669', 'border' => '#bbf7d0' ],
	'amber' => [ 'bg' => '#fffbeb', 'dot' => '#d97706', 'border' => '#fde68a' ],
	'red'   => [ 'bg' => '#fee2e2', 'dot' => '#dc2626', 'border' => '#fecaca' ],
];
$banner_map = [
	'green' => [ 'bg' => '#059669', 'label' => 'EXCELLENT' ],
	'amber' => [ 'bg' => '#d97706', 'label' => 'GOOD' ],
	'red'   => [ 'bg' => '#dc2626', 'label' => 'NEEDS ATTENTION' ],
];

// Card 1: Site Security.
$sec_score = $health_scores['security'] ?? 'green';
$sec_colors = $color_map[ $sec_score ] ?? $color_map['green'];
if ( 'green' === $sec_score ) {
	$sec_status = 'All systems current';
} elseif ( 'amber' === $sec_score ) {
	$sec_status = 'Minor updates pending';
} else {
	$sec_status = 'Updates pending';
}

// Card 2: WordPress Core.
$wp_score  = $wp_up_to_date ? 'green' : 'amber';
$wp_colors = $color_map[ $wp_score ];
$wp_status = $wp_up_to_date ? 'Up to Date' : 'Update Available';

// Card 3: PHP & Server.
$php_colors = $color_map['green'];
$php_status = 'Current';

// Card 4: Search Visibility.
$search_score = $health_scores['seo'] ?? 'green';
if ( null === $clicks ) {
	$search_score  = 'green';
	$search_metric = 'N/A';
	$search_status = 'Not configured';
} else {
	$search_metric = number_format( $clicks );
	if ( null !== $clicks_change && $clicks_change != 0 ) {
		$prefix        = $clicks_change > 0 ? '+' : '-';
		$search_status = $search_metric . ' clicks (' . $prefix . abs( round( $clicks_change ) ) . '%)';
	} else {
		$search_status = $search_metric . ' clicks';
	}
}
$search_colors = $color_map[ $search_score ] ?? $color_map['green'];

// Card 5: Dev Hours.
$dev_score = $health_scores['dev_hours'] ?? 'green';
$dev_colors = $color_map[ $dev_score ] ?? $color_map['green'];
$dev_pct = $hours_included > 0 ? min( 100, round( ( $hours_used / $hours_included ) * 100 ) ) : 0;
$dev_bar_color = $dev_colors['dot'];

// Card 6: Overall Health.
$overall_colors = $color_map[ $overall_health ] ?? $color_map['green'];
$overall_label  = $banner_map[ $overall_health ]['label'] ?? 'EXCELLENT';

// Banner.
$banner_bg    = $banner_map[ $overall_health ]['bg'] ?? '#16a34a';
$banner_label = $banner_map[ $overall_health ]['label'] ?? 'EXCELLENT';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>WHAM Report — <?php echo esc_html( $client['name'] ?? '' ); ?> — <?php echo esc_html( $period_label ); ?></title>
<style>
    /* Reset */
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: Helvetica, Arial, sans-serif;
        font-size: 10pt;
        line-height: 1.4;
        color: #2d3748;
        background: #fff;
    }

    .page { padding: 0; }

    /* Header */
    .header-bar {
        background-color: #1a2332;
        color: #ffffff;
        padding: 30px 50px 25px 50px;
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
        font-size: 32pt;
        font-weight: bold;
        letter-spacing: 2px;
        color: #ffffff;
        line-height: 1;
    }
    .brand-tagline {
        font-size: 8pt;
        color: #8899aa;
        letter-spacing: 1px;
        text-transform: uppercase;
        padding-top: 4px;
    }
    .header-right {
        text-align: right;
    }
    .client-name {
        font-size: 16pt;
        font-weight: bold;
        color: #ffffff;
        line-height: 1.2;
    }
    .header-detail {
        font-size: 9pt;
        color: #8899aa;
        line-height: 1.6;
    }
    .plan-badge {
        display: inline-block;
        background-color: #2a3f56;
        color: #8cb4d8;
        font-size: 8pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 3px 12px;
        border-radius: 3px;
        margin-top: 6px;
    }

    /* Health banner */
    .health-banner {
        padding: 14px 50px;
        text-align: center;
    }
    .health-banner-text {
        font-size: 16pt;
        font-weight: bold;
        color: #ffffff;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    /* Content area */
    .content { padding: 24px 50px 16px 50px; }

    /* Cards grid */
    .cards-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 10px;
        margin-left: -10px;
    }
    .card-cell {
        width: 33%;
        border-radius: 6px;
        padding: 14px 14px 12px 14px;
        vertical-align: top;
    }
    .card-header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 6px;
    }
    .card-header-table td {
        padding: 0;
        vertical-align: middle;
    }
    .dot {
        width: 14px;
        height: 14px;
        border-radius: 7px;
        display: inline-block;
    }
    .card-label {
        font-size: 9pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #1a2332;
    }
    .card-status {
        font-size: 9pt;
        color: #4a5568;
        line-height: 1.3;
    }
    .card-metric {
        font-size: 18pt;
        font-weight: bold;
        color: #1a2332;
        line-height: 1.1;
        padding-top: 2px;
    }

    /* Progress bar (table-based) */
    .bar-outer {
        width: 100%;
        border-collapse: collapse;
    }
    .bar-outer td {
        height: 10px;
        padding: 0;
    }
    .bar-fill {
        border-radius: 5px 0 0 5px;
    }
    .bar-empty {
        background-color: #e2e8f0;
        border-radius: 0 5px 5px 0;
    }
    .bar-fill-full {
        border-radius: 5px;
    }
    .bar-empty-full {
        border-radius: 5px;
    }

    /* Summary section */
    .summary-section {
        padding: 16px 50px 12px 50px;
    }
    .summary-title {
        font-size: 9pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #1a2332;
        padding-bottom: 6px;
        margin-bottom: 10px;
        border-bottom: 2px solid #e2e8f0;
    }
    .summary-text {
        font-size: 10pt;
        color: #4a5568;
        line-height: 1.5;
    }

    /* Footer */
    .footer-bar {
        padding: 14px 50px;
        background-color: #1a2332;
        color: #8899aa;
        font-size: 8pt;
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
<div class="page">

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

    <!-- Overall Health Banner -->
    <div class="health-banner" style="background-color: <?php echo $banner_bg; ?>;">
        <div class="health-banner-text">Site Health: <?php echo esc_html( $banner_label ); ?></div>
    </div>

    <!-- Health Cards -->
    <div class="content">
        <table class="cards-table">
            <!-- Row 1: Security, WordPress Core, PHP & Server -->
            <tr>
                <!-- Card 1: Site Security -->
                <td class="card-cell" style="background-color: <?php echo $sec_colors['bg']; ?>; border: 1px solid <?php echo $sec_colors['border']; ?>;">
                    <table class="card-header-table">
                        <tr>
                            <td style="width: 20px;"><span class="dot" style="background-color: <?php echo $sec_colors['dot']; ?>;"></span></td>
                            <td><span class="card-label">Site Security</span></td>
                        </tr>
                    </table>
                    <div class="card-status"><?php echo esc_html( $sec_status ); ?></div>
                    <div class="card-metric" style="color: <?php echo $sec_colors['dot']; ?>;">
                        <?php echo esc_html( $plugins_total ); ?> <span style="font-size: 9pt; color: #718096; font-weight: normal;">plugins</span>
                    </div>
                </td>

                <!-- Card 2: WordPress Core -->
                <td class="card-cell" style="background-color: <?php echo $wp_colors['bg']; ?>; border: 1px solid <?php echo $wp_colors['border']; ?>;">
                    <table class="card-header-table">
                        <tr>
                            <td style="width: 20px;"><span class="dot" style="background-color: <?php echo $wp_colors['dot']; ?>;"></span></td>
                            <td><span class="card-label">WordPress Core</span></td>
                        </tr>
                    </table>
                    <div class="card-status"><?php echo esc_html( $wp_status ); ?></div>
                    <div class="card-metric"><?php echo esc_html( $wp_version ); ?></div>
                </td>

                <!-- Card 3: PHP & Server -->
                <td class="card-cell" style="background-color: <?php echo $php_colors['bg']; ?>; border: 1px solid <?php echo $php_colors['border']; ?>;">
                    <table class="card-header-table">
                        <tr>
                            <td style="width: 20px;"><span class="dot" style="background-color: <?php echo $php_colors['dot']; ?>;"></span></td>
                            <td><span class="card-label">PHP &amp; Server</span></td>
                        </tr>
                    </table>
                    <div class="card-status"><?php echo esc_html( $php_status ); ?></div>
                    <div class="card-metric"><?php echo esc_html( $php_version ); ?></div>
                </td>
            </tr>

            <!-- Row 2: Search Visibility, Dev Hours, Overall Health -->
            <tr>
                <!-- Card 4: Search Visibility -->
                <td class="card-cell" style="background-color: <?php echo $search_colors['bg']; ?>; border: 1px solid <?php echo $search_colors['border']; ?>;">
                    <table class="card-header-table">
                        <tr>
                            <td style="width: 20px;"><span class="dot" style="background-color: <?php echo $search_colors['dot']; ?>;"></span></td>
                            <td><span class="card-label">Search Visibility</span></td>
                        </tr>
                    </table>
                    <div class="card-status"><?php echo esc_html( $search_status ); ?></div>
                    <?php if ( null !== $clicks ) : ?>
                        <div class="card-metric"><?php echo esc_html( $search_metric ); ?></div>
                    <?php else : ?>
                        <div class="card-metric" style="color: #a0aec0;">&mdash;</div>
                    <?php endif; ?>
                </td>

                <!-- Card 5: Dev Hours -->
                <td class="card-cell" style="background-color: <?php echo $dev_colors['bg']; ?>; border: 1px solid <?php echo $dev_colors['border']; ?>;">
                    <table class="card-header-table">
                        <tr>
                            <td style="width: 20px;"><span class="dot" style="background-color: <?php echo $dev_colors['dot']; ?>;"></span></td>
                            <td><span class="card-label">Dev Hours</span></td>
                        </tr>
                    </table>
                    <div class="card-status"><?php echo esc_html( $hours_used . '/' . $hours_included . ' used' ); ?></div>
                    <!-- Table-based progress bar -->
                    <table class="bar-outer" style="margin-top: 6px;">
                        <tr>
                            <?php if ( $dev_pct > 0 && $dev_pct < 100 ) : ?>
                                <td class="bar-fill" style="width: <?php echo $dev_pct; ?>%; background-color: <?php echo $dev_bar_color; ?>;"></td>
                                <td class="bar-empty"></td>
                            <?php elseif ( $dev_pct >= 100 ) : ?>
                                <td class="bar-fill-full" style="width: 100%; background-color: <?php echo $dev_bar_color; ?>;"></td>
                            <?php else : ?>
                                <td class="bar-empty-full" style="width: 100%;"></td>
                            <?php endif; ?>
                        </tr>
                    </table>
                    <div style="font-size: 8pt; color: #718096; text-align: center; padding-top: 3px;"><?php echo $dev_pct; ?>%</div>
                </td>

                <!-- Card 6: Overall Health -->
                <td class="card-cell" style="background-color: <?php echo $overall_colors['bg']; ?>; border: 1px solid <?php echo $overall_colors['border']; ?>;">
                    <table class="card-header-table">
                        <tr>
                            <td style="width: 20px;"><span class="dot" style="background-color: <?php echo $overall_colors['dot']; ?>;"></span></td>
                            <td><span class="card-label">Overall Health</span></td>
                        </tr>
                    </table>
                    <div class="card-status"><?php echo esc_html( $overall_label ); ?></div>
                    <div class="card-metric" style="color: <?php echo $overall_colors['dot']; ?>;">
                        <?php echo esc_html( $overall_label ); ?>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Executive Summary -->
    <?php if ( $exec_summary ) : ?>
    <div class="summary-section">
        <div class="summary-title">Summary</div>
        <div class="summary-text"><?php echo esc_html( $exec_summary ); ?></div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer-bar">
        <span class="footer-brand">WHAM</span> &mdash; Web Hosting &amp; Maintenance by Clear pH &nbsp;&bull;&nbsp; <?php echo esc_html( $period_label ); ?>
        <br>Questions? Contact us at support@clearph.com
    </div>

</div>
</body>
</html>
