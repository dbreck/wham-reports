<?php
/**
 * WHAM Report — Professional/Premium Tier PDF Template
 * Categories: C (Updates & Maintenance), F (SEO & Traffic), G (Dev Hours)
 *
 * Variables available: $report (array with all report data)
 */
defined( 'ABSPATH' ) || exit;

$client       = $report['client'] ?? [];
$maintenance  = $report['maintenance'] ?? [];
$search       = $report['search'] ?? [];
$analytics    = $report['analytics'] ?? [];
$dev_hours    = $report['dev_hours'] ?? [];
$period_label = $report['period_label'] ?? date( 'F Y' );
$tier         = $report['tier'] ?? 'professional';

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

// GSC data.
$gsc_clicks      = $search['clicks'] ?? 0;
$gsc_impressions = $search['impressions'] ?? 0;
$gsc_ctr         = $search['ctr'] ?? 0;
$gsc_position    = $search['position'] ?? 0;
$gsc_top_queries = $search['top_queries'] ?? [];
$gsc_top_pages   = $search['top_pages'] ?? [];
$gsc_mom         = $search['month_over_month'] ?? [];
$gsc_error       = $search['error'] ?? '';

// GA4 data.
$ga4_sessions  = $analytics['sessions'] ?? 0;
$ga4_users     = $analytics['users'] ?? 0;
$ga4_new_users = $analytics['new_users'] ?? 0;
$ga4_bounce    = $analytics['bounce_rate'] ?? 0;
$ga4_duration  = $analytics['avg_session_duration'] ?? 0;
$ga4_pageviews = $analytics['pageviews'] ?? 0;
$ga4_sources   = $analytics['traffic_sources'] ?? [];
$ga4_pages     = $analytics['top_landing_pages'] ?? [];
$ga4_error     = $analytics['error'] ?? '';

// Dev hours.
$hours_included  = $dev_hours['hours_included'] ?? 0;
$hours_used      = $dev_hours['hours_used'] ?? 0;
$hours_remaining = $dev_hours['hours_remaining'] ?? 0;
$work_summary    = $dev_hours['work_summary'] ?? '';
$dev_error       = $dev_hours['error'] ?? '';

// Helper: format large numbers.
function wham_format_number( $n ) {
    if ( $n >= 1000000 ) return round( $n / 1000000, 1 ) . 'M';
    if ( $n >= 1000 )    return round( $n / 1000, 1 ) . 'K';
    return number_format( $n );
}

// Helper: format duration in seconds to readable.
function wham_format_duration( $seconds ) {
    $m = floor( $seconds / 60 );
    $s = $seconds % 60;
    return $m . 'm ' . $s . 's';
}

// MoM change helper.
function wham_mom_badge( $current, $previous, $suffix = '' ) {
    if ( ! $previous ) return '';
    $change = round( ( ( $current - $previous ) / $previous ) * 100, 1 );
    $arrow  = $change >= 0 ? '↑' : '↓';
    $color  = $change >= 0 ? '#16a34a' : '#dc2626';
    return '<span style="font-size:8pt;color:' . $color . ';font-weight:600;">' . $arrow . ' ' . abs( $change ) . '%' . $suffix . '</span>';
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
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        font-size: 10pt;
        line-height: 1.5;
        color: #1a1a1a;
        background: #fff;
    }

    .page { padding: 36px 44px; max-width: 800px; margin: 0 auto; }

    /* Header */
    .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 3px solid #1a1a1a;
        padding-bottom: 16px;
        margin-bottom: 24px;
    }
    .header-brand h1 {
        font-size: 28pt;
        font-weight: 800;
        letter-spacing: -0.03em;
        color: #1a1a1a;
        line-height: 1;
    }
    .header-brand p {
        font-size: 8pt;
        color: #666;
        margin-top: 3px;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }
    .header-meta { text-align: right; font-size: 8pt; color: #666; }
    .header-meta .client-name {
        font-size: 13pt;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 2px;
    }
    .tier-badge {
        display: inline-block;
        background: #1a1a1a;
        color: #fff;
        font-size: 7pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        padding: 2px 8px;
        border-radius: 3px;
        margin-top: 4px;
    }

    /* Sections */
    .section {
        margin-bottom: 22px;
        page-break-inside: avoid;
    }
    .section-title {
        font-size: 11pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #1a1a1a;
        border-bottom: 1px solid #ddd;
        padding-bottom: 5px;
        margin-bottom: 12px;
    }

    /* Metric cards */
    .metrics-row {
        display: flex;
        gap: 10px;
        margin-bottom: 12px;
    }
    .metric-card {
        flex: 1;
        background: #f8f8f8;
        border: 1px solid #e5e5e5;
        border-radius: 5px;
        padding: 10px 12px;
        text-align: center;
    }
    .metric-value {
        font-size: 20pt;
        font-weight: 800;
        line-height: 1.1;
        color: #1a1a1a;
    }
    .metric-value.green { color: #16a34a; }
    .metric-value.amber { color: #d97706; }
    .metric-value.red { color: #dc2626; }
    .metric-label {
        font-size: 7pt;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #888;
        margin-top: 3px;
    }
    .metric-change { font-size: 8pt; margin-top: 2px; }

    /* Tables */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9pt;
    }
    .data-table th {
        text-align: left;
        font-weight: 600;
        padding: 6px 10px;
        background: #f4f4f4;
        border-bottom: 1px solid #ddd;
        font-size: 8pt;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #555;
    }
    .data-table td {
        padding: 6px 10px;
        border-bottom: 1px solid #eee;
    }
    .data-table tr:last-child td { border-bottom: none; }

    .status-badge {
        display: inline-block;
        padding: 1px 7px;
        border-radius: 3px;
        font-size: 8pt;
        font-weight: 600;
    }
    .badge-green { background: #dcfce7; color: #166534; }
    .badge-amber { background: #fef3c7; color: #92400e; }
    .badge-red { background: #fee2e2; color: #991b1b; }

    /* Hours bar */
    .hours-bar-container {
        background: #eee;
        border-radius: 4px;
        height: 16px;
        margin: 8px 0;
        overflow: hidden;
    }
    .hours-bar-fill {
        height: 100%;
        border-radius: 4px;
    }

    /* Footer */
    .footer {
        margin-top: 30px;
        padding-top: 12px;
        border-top: 1px solid #ddd;
        font-size: 7pt;
        color: #999;
        text-align: center;
    }

    .notice {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 4px;
        padding: 8px 12px;
        font-size: 8pt;
        color: #92400e;
        margin-bottom: 10px;
    }

    .work-summary {
        background: #f8f8f8;
        border-left: 3px solid #1a1a1a;
        padding: 8px 12px;
        font-size: 9pt;
        margin-top: 10px;
    }

    .two-col { display: flex; gap: 16px; }
    .two-col > div { flex: 1; }
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
            <div class="tier-badge"><?php echo esc_html( ucfirst( $tier ) ); ?> Plan</div>
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
                <div class="metric-value <?php echo $wp_up_to_date ? 'green' : 'amber'; ?>"><?php echo esc_html( $wp_version ); ?></div>
                <div class="metric-label">WordPress</div>
            </div>
            <div class="metric-card">
                <div class="metric-value <?php echo $plugins_updated === $plugins_total ? 'green' : 'amber'; ?>"><?php echo $plugins_updated; ?>/<?php echo $plugins_total; ?></div>
                <div class="metric-label">Plugins Updated</div>
            </div>
            <div class="metric-card">
                <div class="metric-value <?php echo $theme_current ? 'green' : 'amber'; ?>"><?php echo esc_html( $theme_version ); ?></div>
                <div class="metric-label"><?php echo esc_html( $theme_name ); ?></div>
            </div>
            <div class="metric-card">
                <div class="metric-value green"><?php echo esc_html( $php_version ); ?></div>
                <div class="metric-label">PHP</div>
            </div>
        </div>

        <?php endif; ?>
    </div>

    <!-- Category F: Search Console -->
    <div class="section">
        <div class="section-title">Search Performance (Google)</div>

        <?php if ( $gsc_error ) : ?>
            <div class="notice"><?php echo esc_html( $gsc_error ); ?></div>
        <?php else : ?>

        <?php
            $prev_clicks = $gsc_mom['previous_clicks'] ?? 0;
            $prev_impressions = $gsc_mom['previous_impressions'] ?? 0;
        ?>

        <div class="metrics-row">
            <div class="metric-card">
                <div class="metric-value"><?php echo wham_format_number( $gsc_clicks ); ?></div>
                <div class="metric-label">Clicks</div>
                <?php if ( $prev_clicks ) : ?>
                    <div class="metric-change"><?php echo wham_mom_badge( $gsc_clicks, $prev_clicks ); ?> vs prior period</div>
                <?php endif; ?>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo wham_format_number( $gsc_impressions ); ?></div>
                <div class="metric-label">Impressions</div>
                <?php if ( $prev_impressions ) : ?>
                    <div class="metric-change"><?php echo wham_mom_badge( $gsc_impressions, $prev_impressions ); ?> vs prior period</div>
                <?php endif; ?>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo esc_html( $gsc_ctr ); ?>%</div>
                <div class="metric-label">Avg CTR</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo esc_html( $gsc_position ); ?></div>
                <div class="metric-label">Avg Position</div>
            </div>
        </div>

        <?php if ( ! empty( $gsc_top_queries ) || ! empty( $gsc_top_pages ) ) : ?>
        <div class="two-col">
            <?php if ( ! empty( $gsc_top_queries ) ) : ?>
            <div>
                <table class="data-table">
                    <thead><tr><th>Top Search Queries</th><th style="text-align:right">Clicks</th></tr></thead>
                    <tbody>
                    <?php foreach ( array_slice( $gsc_top_queries, 0, 8 ) as $q ) : ?>
                        <tr>
                            <td><?php echo esc_html( $q['query'] ?? '' ); ?></td>
                            <td style="text-align:right"><?php echo esc_html( $q['clicks'] ?? 0 ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $gsc_top_pages ) ) : ?>
            <div>
                <table class="data-table">
                    <thead><tr><th>Top Pages</th><th style="text-align:right">Clicks</th></tr></thead>
                    <tbody>
                    <?php foreach ( array_slice( $gsc_top_pages, 0, 5 ) as $p ) : ?>
                        <tr>
                            <td style="word-break:break-all;max-width:200px;"><?php echo esc_html( $p['page'] ?? '' ); ?></td>
                            <td style="text-align:right"><?php echo esc_html( $p['clicks'] ?? 0 ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- Category F: Analytics (GA4) -->
    <?php if ( $analytics['source'] ?? '' !== 'skipped' ) : ?>
    <div class="section">
        <div class="section-title">Website Analytics</div>

        <?php if ( $ga4_error ) : ?>
            <div class="notice"><?php echo esc_html( $ga4_error ); ?></div>
        <?php else : ?>

        <div class="metrics-row">
            <div class="metric-card">
                <div class="metric-value"><?php echo wham_format_number( $ga4_sessions ); ?></div>
                <div class="metric-label">Sessions</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo wham_format_number( $ga4_users ); ?></div>
                <div class="metric-label">Users</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo wham_format_number( $ga4_pageviews ); ?></div>
                <div class="metric-label">Pageviews</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo esc_html( $ga4_bounce ); ?>%</div>
                <div class="metric-label">Bounce Rate</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo wham_format_duration( $ga4_duration ); ?></div>
                <div class="metric-label">Avg Duration</div>
            </div>
        </div>

        <?php if ( ! empty( $ga4_sources ) || ! empty( $ga4_pages ) ) : ?>
        <div class="two-col">
            <?php if ( ! empty( $ga4_sources ) ) : ?>
            <div>
                <table class="data-table">
                    <thead><tr><th>Traffic Sources</th><th style="text-align:right">Sessions</th></tr></thead>
                    <tbody>
                    <?php foreach ( array_slice( $ga4_sources, 0, 5 ) as $src ) : ?>
                        <tr>
                            <td><?php echo esc_html( $src['sessionDefaultChannelGroup'] ?? '' ); ?></td>
                            <td style="text-align:right"><?php echo esc_html( $src['metric_0'] ?? 0 ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $ga4_pages ) ) : ?>
            <div>
                <table class="data-table">
                    <thead><tr><th>Top Landing Pages</th><th style="text-align:right">Sessions</th></tr></thead>
                    <tbody>
                    <?php foreach ( array_slice( $ga4_pages, 0, 5 ) as $pg ) : ?>
                        <tr>
                            <td style="word-break:break-all;max-width:200px;"><?php echo esc_html( $pg['landingPagePlusQueryString'] ?? '' ); ?></td>
                            <td style="text-align:right"><?php echo esc_html( $pg['metric_0'] ?? 0 ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
    <?php endif; ?>

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
                <div class="metric-label">Included</div>
            </div>
            <div class="metric-card">
                <div class="metric-value <?php echo $hours_remaining > 0 ? 'green' : 'red'; ?>"><?php echo esc_html( $hours_remaining ); ?></div>
                <div class="metric-label">Remaining</div>
            </div>
        </div>

        <div class="hours-bar-container">
            <div class="hours-bar-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $bar_color; ?>;"></div>
        </div>
        <p style="font-size:8pt;color:#666;text-align:center;"><?php echo $pct; ?>% of included hours used</p>

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
