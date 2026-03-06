<?php
/**
 * Client Dashboard — Report List View
 *
 * Variables: $reports (array of WP_Post), $client_id, $is_admin
 */
defined( 'ABSPATH' ) || exit;

$user = wp_get_current_user();

// Get client name from first report or user meta.
$client_name = '';
if ( ! empty( $reports ) ) {
    $client_name = get_post_meta( $reports[0]->ID, '_wham_client_name', true );
}
if ( ! $client_name ) {
    $client_name = $user->display_name;
}
?>
<div class="wham-dashboard">

    <div class="wham-dash-header">
        <div>
            <h1>Your Reports</h1>
            <p class="wham-dash-subtitle">Welcome back, <?php echo esc_html( $client_name ); ?></p>
        </div>
        <div class="wham-dash-brand">
            <strong>WHAM</strong>
            <span>Web Hosting &amp; Maintenance</span>
        </div>
    </div>

    <?php if ( empty( $reports ) ) : ?>
        <div class="wham-dash-empty">
            <p>No reports available yet. Your first report will appear here once generated.</p>
        </div>
    <?php else : ?>

    <div class="wham-report-grid">
        <?php foreach ( $reports as $rpt ) :
            $period      = get_post_meta( $rpt->ID, '_wham_period', true );
            $period_label = $period ? date( 'F Y', strtotime( $period . '-01' ) ) : '';
            $tier        = get_post_meta( $rpt->ID, '_wham_tier', true );
            $pdf_url     = get_post_meta( $rpt->ID, '_wham_pdf_url', true );
            $rpt_client  = get_post_meta( $rpt->ID, '_wham_client_name', true );
            $detail_url  = add_query_arg( 'report', $rpt->ID, get_permalink() );
        ?>
        <div class="wham-report-card">
            <div class="wham-report-card-header">
                <span class="wham-report-period"><?php echo esc_html( $period_label ); ?></span>
                <span class="wham-report-tier"><?php echo esc_html( ucfirst( $tier ) ); ?></span>
            </div>
            <?php if ( $is_admin && $rpt_client ) : ?>
                <div class="wham-report-client"><?php echo esc_html( $rpt_client ); ?></div>
            <?php endif; ?>
            <div class="wham-report-card-actions">
                <a href="<?php echo esc_url( $detail_url ); ?>" class="wham-btn wham-btn-sm">View Report</a>
                <?php if ( $pdf_url ) : ?>
                    <a href="<?php echo esc_url( $pdf_url ); ?>" class="wham-btn wham-btn-sm wham-btn-outline" target="_blank">Download PDF</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

</div>
