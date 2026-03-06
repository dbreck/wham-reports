<?php
/**
 * Client Dashboard — Single Report Detail View
 *
 * Variables: $report_post (WP_Post), $report_data (array), $pdf_url (string), $is_admin (bool)
 */
defined( 'ABSPATH' ) || exit;

$client       = $report_data['client'] ?? [];
$maintenance  = $report_data['maintenance'] ?? [];
$search       = $report_data['search'] ?? [];
$analytics    = $report_data['analytics'] ?? [];
$dev_hours    = $report_data['dev_hours'] ?? [];
$period_label = $report_data['period_label'] ?? '';
$tier         = $report_data['tier'] ?? 'basic';

$back_url = remove_query_arg( 'report' );
?>
<div class="wham-dashboard">

    <div class="wham-dash-header">
        <div>
            <a href="<?php echo esc_url( $back_url ); ?>" class="wham-back-link">&larr; All Reports</a>
            <h1><?php echo esc_html( $period_label ); ?> Report</h1>
            <p class="wham-dash-subtitle"><?php echo esc_html( $client['name'] ?? '' ); ?> &middot; <?php echo esc_html( ucfirst( $tier ) ); ?> Plan</p>
        </div>
        <?php if ( $pdf_url ) : ?>
            <a href="<?php echo esc_url( $pdf_url ); ?>" class="wham-btn" target="_blank">Download PDF</a>
        <?php endif; ?>
    </div>

    <!-- Maintenance Section -->
    <div class="wham-dash-section">
        <h2>Updates &amp; Maintenance</h2>
        <?php if ( ! empty( $maintenance['error'] ) ) : ?>
            <p class="wham-dash-muted"><?php echo esc_html( $maintenance['error'] ); ?></p>
        <?php else : ?>
        <div class="wham-metric-grid">
            <div class="wham-metric">
                <span class="wham-metric-val"><?php echo esc_html( $maintenance['wp_version'] ?? 'N/A' ); ?></span>
                <span class="wham-metric-lbl">WordPress</span>
            </div>
            <div class="wham-metric">
                <span class="wham-metric-val"><?php echo esc_html( ( $maintenance['plugins_updated'] ?? 0 ) . '/' . ( $maintenance['plugins_total'] ?? 0 ) ); ?></span>
                <span class="wham-metric-lbl">Plugins Updated</span>
            </div>
            <div class="wham-metric">
                <span class="wham-metric-val"><?php echo esc_html( $maintenance['php_version'] ?? 'N/A' ); ?></span>
                <span class="wham-metric-lbl">PHP Version</span>
            </div>
            <div class="wham-metric">
                <span class="wham-metric-val"><?php echo esc_html( $maintenance['theme_name'] ?? 'N/A' ); ?> <?php echo esc_html( $maintenance['theme_version'] ?? '' ); ?></span>
                <span class="wham-metric-lbl">Theme</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Search Console Section (Professional+) -->
    <?php if ( ( $search['source'] ?? '' ) !== 'skipped' && ( $search['source'] ?? '' ) !== 'not_configured' ) : ?>
    <div class="wham-dash-section">
        <h2>Search Performance</h2>
        <?php if ( ! empty( $search['error'] ) ) : ?>
            <p class="wham-dash-muted"><?php echo esc_html( $search['error'] ); ?></p>
        <?php else : ?>
        <div class="wham-metric-grid">
            <div class="wham-metric">
                <span class="wham-metric-val"><?php echo esc_html( number_format( $search['clicks'] ?? 0 ) ); ?></span>
                <span class="wham-metric-lbl">Clicks</span>
            </div>
            <div class="wham-metric">
                <span class="wham-metric-val"><?php echo esc_html( number_format( $search['impressions'] ?? 0 ) ); ?></span>
                <span class="wham-metric-lbl">Impressions</span>
            </div>
            <div class="wham-metric">
                <span class="wham-metric-val"><?php echo esc_html( $search['ctr'] ?? 0 ); ?>%</span>
                <span class="wham-metric-lbl">CTR</span>
            </div>
            <div class="wham-metric">
                <span class="wham-metric-val"><?php echo esc_html( $search['position'] ?? 0 ); ?></span>
                <span class="wham-metric-lbl">Avg Position</span>
            </div>
        </div>

        <?php if ( ! empty( $search['top_queries'] ) ) : ?>
        <h3>Top Search Queries</h3>
        <table class="wham-dash-table">
            <thead><tr><th>Query</th><th>Clicks</th><th>Impressions</th><th>CTR</th></tr></thead>
            <tbody>
            <?php foreach ( array_slice( $search['top_queries'], 0, 10 ) as $q ) : ?>
                <tr>
                    <td><?php echo esc_html( $q['query'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $q['clicks'] ?? 0 ); ?></td>
                    <td><?php echo esc_html( $q['impressions'] ?? 0 ); ?></td>
                    <td><?php echo esc_html( $q['ctr'] ?? '' ); ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- GA4 Analytics Section (Professional+) -->
    <?php if ( ( $analytics['source'] ?? '' ) !== 'skipped' && ( $analytics['source'] ?? '' ) !== 'error' ) : ?>
    <div class="wham-dash-section">
        <h2>Website Analytics</h2>
        <div class="wham-metric-grid">
            <div class="wham-metric">
                <span class="wham-metric-val"><?php echo esc_html( number_format( $analytics['sessions'] ?? 0 ) ); ?></span>
                <span class="wham-metric-lbl">Sessions</span>
            </div>
            <div class="wham-metric">
                <span class="wham-metric-val"><?php echo esc_html( number_format( $analytics['users'] ?? 0 ) ); ?></span>
                <span class="wham-metric-lbl">Users</span>
            </div>
            <div class="wham-metric">
                <span class="wham-metric-val"><?php echo esc_html( number_format( $analytics['pageviews'] ?? 0 ) ); ?></span>
                <span class="wham-metric-lbl">Pageviews</span>
            </div>
            <div class="wham-metric">
                <span class="wham-metric-val"><?php echo esc_html( $analytics['bounce_rate'] ?? 0 ); ?>%</span>
                <span class="wham-metric-lbl">Bounce Rate</span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Dev Hours Section -->
    <div class="wham-dash-section">
        <h2>Development Hours</h2>
        <?php if ( ! empty( $dev_hours['error'] ) ) : ?>
            <p class="wham-dash-muted"><?php echo esc_html( $dev_hours['error'] ); ?></p>
        <?php else : ?>
        <?php
            $hrs_incl = $dev_hours['hours_included'] ?? 0;
            $hrs_used = $dev_hours['hours_used'] ?? 0;
            $hrs_rem  = $dev_hours['hours_remaining'] ?? 0;
            $pct      = $hrs_incl > 0 ? min( 100, round( ( $hrs_used / $hrs_incl ) * 100 ) ) : 0;
        ?>
        <div class="wham-metric-grid">
            <div class="wham-metric">
                <span class="wham-metric-val"><?php echo esc_html( $hrs_used ); ?></span>
                <span class="wham-metric-lbl">Hours Used</span>
            </div>
            <div class="wham-metric">
                <span class="wham-metric-val"><?php echo esc_html( $hrs_incl ); ?></span>
                <span class="wham-metric-lbl">Hours Included</span>
            </div>
            <div class="wham-metric">
                <span class="wham-metric-val"><?php echo esc_html( $hrs_rem ); ?></span>
                <span class="wham-metric-lbl">Remaining</span>
            </div>
        </div>
        <div class="wham-hours-bar">
            <div class="wham-hours-fill" style="width:<?php echo $pct; ?>%;"></div>
        </div>
        <?php if ( $dev_hours['work_summary'] ?? '' ) : ?>
            <p class="wham-work-summary"><strong>Work:</strong> <?php echo esc_html( $dev_hours['work_summary'] ); ?></p>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</div>
