<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wham-admin">
    <h1>WHAM Reports</h1>

    <?php if ( isset( $_GET['generated'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php if ( $_GET['generated'] === 'single' ) : ?>
                    Report generated successfully for the selected client.
                <?php else : ?>
                    All reports generated successfully.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="wham-admin-grid">
        <!-- Quick Stats -->
        <div class="wham-card">
            <h2>Quick Stats</h2>
            <?php
            $total_reports = wp_count_posts( 'wham_report' )->publish ?? 0;
            $client_map    = \WHAM_Reports::get_client_map();
            $total_clients = count( $client_map );
            ?>
            <div class="wham-stats">
                <div class="wham-stat">
                    <span class="wham-stat-number"><?php echo (int) $total_clients; ?></span>
                    <span class="wham-stat-label">Active Clients</span>
                </div>
                <div class="wham-stat">
                    <span class="wham-stat-number"><?php echo (int) $total_reports; ?></span>
                    <span class="wham-stat-label">Reports Generated</span>
                </div>
            </div>
        </div>

        <!-- Generate Reports -->
        <div class="wham-card">
            <h2>Generate Reports</h2>
            <p>Run the report generation pipeline for all active clients. This will collect data from all sources, generate PDFs, and optionally send emails.</p>
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                <?php wp_nonce_field( 'wham_generate_reports' ); ?>
                <input type="hidden" name="action" value="wham_generate_reports" />
                <button type="submit" class="button button-primary button-hero">
                    Generate All Reports Now
                </button>
            </form>
            <?php
            $next = wp_next_scheduled( 'wham_generate_reports' );
            if ( $next ) :
            ?>
                <p class="description" style="margin-top: 12px;">
                    Next scheduled run: <strong><?php echo date( 'F j, Y \a\t g:i A', $next ); ?></strong>
                </p>
            <?php endif; ?>
        </div>

        <!-- Recent Reports -->
        <div class="wham-card wham-card-wide">
            <h2>Recent Reports</h2>
            <?php
            $recent = new WP_Query([
                'post_type'      => 'wham_report',
                'posts_per_page' => 10,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);

            if ( $recent->have_posts() ) :
            ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Period</th>
                        <th>Tier</th>
                        <th>PDF</th>
                        <th>Generated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ( $recent->have_posts() ) : $recent->the_post(); ?>
                    <tr>
                        <td><?php echo esc_html( get_post_meta( get_the_ID(), '_wham_client_name', true ) ); ?></td>
                        <td><?php echo esc_html( get_post_meta( get_the_ID(), '_wham_period', true ) ); ?></td>
                        <td><?php echo esc_html( \WHAM_Reports::get_tier_label( get_post_meta( get_the_ID(), '_wham_tier', true ) ) ); ?></td>
                        <td>
                            <?php $pdf = get_post_meta( get_the_ID(), '_wham_pdf_url', true ); ?>
                            <?php if ( $pdf ) : ?>
                                <a href="<?php echo esc_url( $pdf ); ?>" target="_blank">Download</a>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo get_the_date(); ?></td>
                    </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                </tbody>
            </table>
            <?php else : ?>
                <p>No reports generated yet. Use the button above to generate your first batch.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
