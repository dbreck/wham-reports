<?php
/**
 * WHAM Report — Basic Tier PDF Template
 * Categories: C (Updates & Maintenance), G (Dev Hours)
 *
 * Variables available: $report (array with all report data)
 */
defined( 'ABSPATH' ) || exit;

$client       = $report['client'] ?? [];
$maintenance  = $report['maintenance'] ?? [];
$dev_hours    = $report['dev_hours'] ?? [];
$period_label = $report['period_label'] ?? date( 'F Y' );

// Maintenance data.
$wp_version     = $maintenance['wp_version'] ?? 'N/A';
$wp_up_to_date  = empty( $maintenance['wp_update_available'] );
$plugins_total  = $maintenance['plugins_total'] ?? 0;
$plugins_updated = $maintenance['plugins_updated'] ?? 0;
$theme_name     = $maintenance['theme_name'] ?? 'N/A';
$theme_version  = $maintenance['theme_version'] ?? '';
$theme_current  = empty( $maintenance['theme_update_available'] );
$php_version    = $maintenance['php_version'] ?? 'N/A';
$last_sync      = $maintenance['last_sync'] ?? '';
$maint_error    = $maintenance['error'] ?? '';

// Dev hours.
$hours_included  = $dev_hours['hours_included'] ?? 0;
$hours_used      = $dev_hours['hours_used'] ?? 0;
$hours_remaining = $dev_hours['hours_remaining'] ?? 0;
$work_summary    = $dev_hours['work_summary'] ?? '';
$dev_error       = $dev_hours['error'] ?? '';
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
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        font-size: 11pt;
        line-height: 1.5;
        color: #1a1a1a;
        background: #fff;
    }

    .page { padding: 40px 50px; max-width: 800px; margin: 0 auto; }

    /* Header */
    .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 3px solid #1a1a1a;
        padding-bottom: 20px;
        margin-bottom: 30px;
    }
    .header-brand h1 {
        font-size: 28pt;
        font-weight: 800;
        letter-spacing: -0.03em;
        color: #1a1a1a;
        line-height: 1;
    }
    .header-brand p {
        font-size: 9pt;
        color: #666;
        margin-top: 4px;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }
    .header-meta { text-align: right; font-size: 9pt; color: #666; }
    .header-meta .client-name {
        font-size: 14pt;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 2px;
    }

    /* Sections */
    .section {
        margin-bottom: 28px;
        page-break-inside: avoid;
    }
    .section-title {
        font-size: 13pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #1a1a1a;
        border-bottom: 1px solid #ddd;
        padding-bottom: 6px;
        margin-bottom: 14px;
    }

    /* Metric cards */
    .metrics-row {
        display: flex;
        gap: 16px;
        margin-bottom: 16px;
    }
    .metric-card {
        flex: 1;
        background: #f8f8f8;
        border: 1px solid #e5e5e5;
        border-radius: 6px;
        padding: 14px 16px;
        text-align: center;
    }
    .metric-value {
        font-size: 24pt;
        font-weight: 800;
        line-height: 1.1;
        color: #1a1a1a;
    }
    .metric-value.green { color: #16a34a; }
    .metric-value.amber { color: #d97706; }
    .metric-value.red { color: #dc2626; }
    .metric-label {
        font-size: 8pt;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #888;
        margin-top: 4px;
    }

    /* Status table */
    .status-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10pt;
    }
    .status-table th {
        text-align: left;
        font-weight: 600;
        padding: 8px 12px;
        background: #f4f4f4;
        border-bottom: 1px solid #ddd;
        font-size: 9pt;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #555;
    }
    .status-table td {
        padding: 8px 12px;
        border-bottom: 1px solid #eee;
    }
    .status-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 9pt;
        font-weight: 600;
    }
    .badge-green { background: #dcfce7; color: #166534; }
    .badge-amber { background: #fef3c7; color: #92400e; }
    .badge-red { background: #fee2e2; color: #991b1b; }

    /* Hours bar */
    .hours-bar-container {
        background: #eee;
        border-radius: 4px;
        height: 20px;
        margin: 10px 0;
        overflow: hidden;
    }
    .hours-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.3s;
    }

    /* Footer */
    .footer {
        margin-top: 40px;
        padding-top: 16px;
        border-top: 1px solid #ddd;
        font-size: 8pt;
        color: #999;
        text-align: center;
    }

    /* Notice */
    .notice {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 4px;
        padding: 10px 14px;
        font-size: 9pt;
        color: #92400e;
        margin-bottom: 12px;
    }

    /* Work summary */
    .work-summary {
        background: #f8f8f8;
        border-left: 3px solid #1a1a1a;
        padding: 10px 14px;
        font-size: 10pt;
        margin-top: 12px;
    }
</style>
</head>
<body>
<div class="page">

    <!-- Header -->
    <div class="header">
        <div class="header-brand">
            <h1>WHAM</h1>
            <p>Web Hosting &amp; Maintenance</p>
        </div>
        <div class="header-meta">
            <div class="client-name"><?php echo esc_html( $client['name'] ?? 'Client' ); ?></div>
            <div><?php echo esc_html( $client['url'] ?? '' ); ?></div>
            <div><?php echo esc_html( $period_label ); ?> Report</div>
            <div>Basic Plan</div>
        </div>
    </div>

    <!-- Category C: Updates & Maintenance -->
    <div class="section">
        <div class="section-title">Updates &amp; Maintenance</div>

        <?php if ( $maint_error ) : ?>
            <div class="notice"><?php echo esc_html( $maint_error ); ?></div>
        <?php else : ?>

        <div class="metrics-row">
            <div class="metric-card">
                <div class="metric-value <?php echo $wp_up_to_date ? 'green' : 'amber'; ?>">
                    <?php echo esc_html( $wp_version ); ?>
                </div>
                <div class="metric-label">WordPress Version</div>
            </div>
            <div class="metric-card">
                <div class="metric-value <?php echo $plugins_updated === $plugins_total ? 'green' : 'amber'; ?>">
                    <?php echo esc_html( $plugins_updated ); ?>/<?php echo esc_html( $plugins_total ); ?>
                </div>
                <div class="metric-label">Plugins Updated</div>
            </div>
            <div class="metric-card">
                <div class="metric-value green">
                    <?php echo esc_html( $php_version ); ?>
                </div>
                <div class="metric-label">PHP Version</div>
            </div>
        </div>

        <table class="status-table">
            <tr>
                <th>Component</th>
                <th>Version</th>
                <th>Status</th>
            </tr>
            <tr>
                <td>WordPress Core</td>
                <td><?php echo esc_html( $wp_version ); ?></td>
                <td><span class="status-badge <?php echo $wp_up_to_date ? 'badge-green' : 'badge-amber'; ?>"><?php echo $wp_up_to_date ? 'Up to Date' : 'Update Available'; ?></span></td>
            </tr>
            <tr>
                <td>Theme: <?php echo esc_html( $theme_name ); ?></td>
                <td><?php echo esc_html( $theme_version ); ?></td>
                <td><span class="status-badge <?php echo $theme_current ? 'badge-green' : 'badge-amber'; ?>"><?php echo $theme_current ? 'Up to Date' : 'Update Available'; ?></span></td>
            </tr>
            <tr>
                <td>PHP</td>
                <td><?php echo esc_html( $php_version ); ?></td>
                <td><span class="status-badge badge-green">Active</span></td>
            </tr>
            <tr>
                <td>Plugins</td>
                <td><?php echo esc_html( $plugins_total ); ?> installed</td>
                <td><span class="status-badge <?php echo $plugins_updated === $plugins_total ? 'badge-green' : 'badge-amber'; ?>">
                    <?php echo $plugins_updated === $plugins_total ? 'All Updated' : ( $plugins_total - $plugins_updated ) . ' Need Updates'; ?>
                </span></td>
            </tr>
        </table>

        <?php if ( $last_sync ) : ?>
            <p style="font-size: 8pt; color: #999; margin-top: 8px;">Last synced: <?php echo esc_html( date( 'M j, Y g:i A', strtotime( $last_sync ) ) ); ?></p>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- Category G: Development Hours -->
    <div class="section">
        <div class="section-title">Development Hours</div>

        <?php if ( $dev_error ) : ?>
            <div class="notice"><?php echo esc_html( $dev_error ); ?></div>
        <?php else : ?>

        <?php
            $pct = $hours_included > 0 ? min( 100, round( ( $hours_used / $hours_included ) * 100 ) ) : 0;
            $bar_color = $pct > 90 ? '#dc2626' : ( $pct > 70 ? '#d97706' : '#16a34a' );
        ?>

        <div class="metrics-row">
            <div class="metric-card">
                <div class="metric-value"><?php echo esc_html( $hours_used ); ?></div>
                <div class="metric-label">Hours Used</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo esc_html( $hours_included ); ?></div>
                <div class="metric-label">Hours Included</div>
            </div>
            <div class="metric-card">
                <div class="metric-value <?php echo $hours_remaining > 0 ? 'green' : 'red'; ?>">
                    <?php echo esc_html( $hours_remaining ); ?>
                </div>
                <div class="metric-label">Hours Remaining</div>
            </div>
        </div>

        <div class="hours-bar-container">
            <div class="hours-bar-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $bar_color; ?>;"></div>
        </div>
        <p style="font-size: 9pt; color: #666; text-align: center;"><?php echo $pct; ?>% of included hours used</p>

        <?php if ( $work_summary ) : ?>
            <div class="work-summary">
                <strong>Work Performed:</strong> <?php echo esc_html( $work_summary ); ?>
            </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>WHAM — Web Hosting &amp; Maintenance by Clear Phosphor &nbsp;|&nbsp; <?php echo esc_html( $period_label ); ?></p>
        <p>Questions? Contact us at support@clearph.com</p>
    </div>

</div>
</body>
</html>
