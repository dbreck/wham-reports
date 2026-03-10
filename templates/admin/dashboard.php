<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wham-admin wham-dashboard">

    <?php if ( isset( $_GET['generated'] ) ) : ?>
        <?php $gen = (int) $_GET['generated']; ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                if ( $gen === 1 ) {
                    echo '1 report generated successfully.';
                } elseif ( $gen > 1 ) {
                    echo esc_html( $gen ) . ' reports generated successfully.';
                } else {
                    echo 'Report generation complete. Reports may have been skipped if they already exist for this period.';
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php
    $total_reports = wp_count_posts( 'wham_report' )->publish ?? 0;
    $client_map    = \WHAM_Reports::get_client_map();
    $total_clients = count( $client_map );
    $next_cron     = wp_next_scheduled( 'wham_generate_reports' );

    // Sort clients alphabetically.
    uasort( $client_map, function( $a, $b ) {
        return strcasecmp( $a['client_name'] ?? '', $b['client_name'] ?? '' );
    });

    $tier_colors = [
        'basic'        => '#64748b',
        'professional' => '#2563eb',
        'premium'      => '#7c3aed',
    ];
    ?>

    <!-- Dashboard Header -->
    <div class="wham-dash-header">
        <div class="wham-dash-header-left">
            <h1>WHAM Reports</h1>
            <p class="wham-dash-subtitle">Web Hosting And Maintenance</p>
        </div>
        <div class="wham-dash-header-stats">
            <div class="wham-dash-stat">
                <span class="wham-dash-stat-num"><?php echo (int) $total_clients; ?></span>
                <span class="wham-dash-stat-label">Clients</span>
            </div>
            <div class="wham-dash-stat">
                <span class="wham-dash-stat-num"><?php echo (int) $total_reports; ?></span>
                <span class="wham-dash-stat-label">Reports</span>
            </div>
            <?php if ( $next_cron ) : ?>
            <div class="wham-dash-stat wham-dash-stat-schedule">
                <span class="wham-dash-stat-num wham-dash-stat-date"><?php echo esc_html( date( 'M j', $next_cron ) ); ?></span>
                <span class="wham-dash-stat-label">Next Run</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="wham-dash-grid">

        <!-- Generate Reports -->
        <div class="wham-dash-card wham-dash-card-generate">
            <div class="wham-dash-card-header">
                <h2>Generate Reports</h2>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'wham_generate_reports' ); ?>
                <input type="hidden" name="action" value="wham_generate_reports" />

                <div class="wham-dash-field">
                    <label for="wham_report_period" class="wham-dash-label">Report Period</label>
                    <select name="wham_report_period" id="wham_report_period" class="wham-dash-select">
                        <?php
                        $now = new \DateTime( 'first day of this month' );
                        for ( $i = 0; $i < 12; $i++ ) {
                            $value = $now->format( 'Y-m' );
                            $label = $now->format( 'F Y' );
                            printf(
                                '<option value="%s"%s>%s</option>',
                                esc_attr( $value ),
                                0 === $i ? ' selected' : '',
                                esc_html( $label )
                            );
                            $now->modify( '-1 month' );
                        }
                        ?>
                    </select>
                </div>

                <div class="wham-dash-field">
                    <div class="wham-dash-label-row">
                        <label class="wham-dash-label">Clients</label>
                        <label class="wham-dash-select-all">
                            <input type="checkbox" id="wham-select-all" checked />
                            All
                        </label>
                    </div>
                    <div class="wham-dash-client-list">
                        <?php foreach ( $client_map as $mid => $cfg ) :
                            $tier  = $cfg['tier'] ?? 'basic';
                            $color = $tier_colors[ $tier ] ?? '#64748b';
                        ?>
                            <label class="wham-dash-client-row">
                                <input type="checkbox" name="wham_client_ids[]" value="<?php echo esc_attr( $mid ); ?>" checked class="wham-client-cb" />
                                <span class="wham-dash-client-name"><?php echo esc_html( $cfg['client_name'] ?? $mid ); ?></span>
                                <span class="wham-dash-tier-badge" style="background:<?php echo esc_attr( $color ); ?>;"><?php echo esc_html( ucfirst( $tier ) ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="button button-primary button-hero wham-dash-generate-btn">
                    Generate Reports
                </button>
            </form>
        </div>

        <!-- Test Email -->
        <div class="wham-dash-card wham-dash-card-email">
            <div class="wham-dash-card-header">
                <h2>Test Email</h2>
            </div>
            <p class="wham-dash-card-desc">Send a test report email to verify formatting and delivery.</p>

            <div class="wham-dash-field">
                <label for="wham_test_report" class="wham-dash-label">Report</label>
                <select id="wham_test_report" class="wham-dash-select">
                    <option value="">-- Select a report --</option>
                    <?php
                    $test_reports = new WP_Query([
                        'post_type'      => 'wham_report',
                        'posts_per_page' => 20,
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                        'post_status'    => 'any',
                    ]);
                    while ( $test_reports->have_posts() ) : $test_reports->the_post();
                        $rname   = get_post_meta( get_the_ID(), '_wham_client_name', true );
                        $rperiod = get_post_meta( get_the_ID(), '_wham_period', true );
                        $rtier   = get_post_meta( get_the_ID(), '_wham_tier', true );
                        printf(
                            '<option value="%d">%s — %s (%s)</option>',
                            get_the_ID(),
                            esc_html( $rname ),
                            esc_html( $rperiod ),
                            esc_html( $rtier ?: 'basic' )
                        );
                    endwhile;
                    wp_reset_postdata();
                    ?>
                </select>
            </div>

            <div class="wham-dash-field">
                <label for="wham_test_email" class="wham-dash-label">Send to</label>
                <input type="email" id="wham_test_email" class="wham-dash-input" placeholder="you@example.com" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
            </div>

            <div class="wham-dash-email-action">
                <button type="button" id="wham-send-test-email" class="button button-primary" disabled>Send Test Email</button>
                <span id="wham-test-email-status"></span>
            </div>
        </div>

        <!-- Recent Reports -->
        <div class="wham-dash-card wham-dash-card-wide">
            <div class="wham-dash-card-header">
                <h2>Recent Reports</h2>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wham_report' ) ); ?>" class="wham-dash-view-all">View All &rarr;</a>
            </div>
            <?php
            $recent = new WP_Query([
                'post_type'      => 'wham_report',
                'posts_per_page' => 10,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);

            if ( $recent->have_posts() ) :
            ?>
            <table class="wham-dash-table">
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
                    <?php while ( $recent->have_posts() ) : $recent->the_post();
                        $r_tier  = get_post_meta( get_the_ID(), '_wham_tier', true );
                        $r_color = $tier_colors[ $r_tier ] ?? '#64748b';
                    ?>
                    <tr>
                        <td class="wham-dash-table-client"><?php echo esc_html( get_post_meta( get_the_ID(), '_wham_client_name', true ) ); ?></td>
                        <td><?php echo esc_html( get_post_meta( get_the_ID(), '_wham_period', true ) ); ?></td>
                        <td><span class="wham-dash-tier-badge" style="background:<?php echo esc_attr( $r_color ); ?>;"><?php echo esc_html( ucfirst( $r_tier ?: 'basic' ) ); ?></span></td>
                        <td>
                            <?php $pdf = get_post_meta( get_the_ID(), '_wham_pdf_url', true ); ?>
                            <?php if ( $pdf ) : ?>
                                <a href="<?php echo esc_url( $pdf ); ?>" target="_blank" class="wham-dash-pdf-link">Download</a>
                            <?php else : ?>
                                <span class="wham-dash-muted">--</span>
                            <?php endif; ?>
                        </td>
                        <td class="wham-dash-muted"><?php echo get_the_date( 'M j, Y' ); ?></td>
                    </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                </tbody>
            </table>
            <?php else : ?>
                <p class="wham-dash-empty">No reports generated yet. Use the form above to generate your first batch.</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
(function(){
    /* Select All toggle */
    var all = document.getElementById('wham-select-all');
    var cbs = document.querySelectorAll('.wham-client-cb');
    all.addEventListener('change', function(){
        cbs.forEach(function(cb){ cb.checked = all.checked; });
    });
    cbs.forEach(function(cb){
        cb.addEventListener('change', function(){
            all.checked = Array.from(cbs).every(function(c){ return c.checked; });
        });
    });

    /* Test Email */
    var btn    = document.getElementById('wham-send-test-email');
    var sel    = document.getElementById('wham_test_report');
    var inp    = document.getElementById('wham_test_email');
    var status = document.getElementById('wham-test-email-status');

    function toggleBtn() {
        btn.disabled = !sel.value || !inp.value;
    }
    sel.addEventListener('change', toggleBtn);
    inp.addEventListener('input', toggleBtn);
    toggleBtn();

    btn.addEventListener('click', function(){
        btn.disabled = true;
        status.textContent = 'Sending...';
        status.className = 'wham-dash-status-pending';

        var fd = new FormData();
        fd.append('action', 'wham_test_email');
        fd.append('nonce', '<?php echo wp_create_nonce( 'wham_test_email' ); ?>');
        fd.append('report_id', sel.value);
        fd.append('email', inp.value);

        fetch(ajaxurl, { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(r){
                if (r.success) {
                    status.textContent = r.data;
                    status.className = 'wham-dash-status-success';
                } else {
                    status.textContent = r.data || 'Error sending email.';
                    status.className = 'wham-dash-status-error';
                }
                toggleBtn();
            })
            .catch(function(){
                status.textContent = 'Request failed.';
                status.className = 'wham-dash-status-error';
                toggleBtn();
            });
    });
})();
</script>
