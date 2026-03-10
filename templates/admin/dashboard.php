<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wham-admin">
    <h1>WHAM Reports</h1>

    <?php if ( isset( $_GET['generated'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                $gen = sanitize_text_field( $_GET['generated'] );
                if ( $gen === 'single' ) {
                    echo 'Report generated successfully for the selected client.';
                } elseif ( is_numeric( $gen ) && (int) $gen > 1 ) {
                    echo esc_html( (int) $gen ) . ' reports generated successfully.';
                } else {
                    echo 'Reports generated successfully.';
                }
                ?>
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
            <p>Run the report generation pipeline. Select a period and optionally a specific client.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'wham_generate_reports' ); ?>
                <input type="hidden" name="action" value="wham_generate_reports" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wham_report_period">Report Period</label></th>
                        <td>
                            <select name="wham_report_period" id="wham_report_period">
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
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Clients</label></th>
                        <td>
                            <?php
                            $client_map = \WHAM_Reports::get_client_map();
                            // Sort alphabetically by client name.
                            uasort( $client_map, function( $a, $b ) {
                                return strcasecmp( $a['client_name'] ?? '', $b['client_name'] ?? '' );
                            });
                            $tier_colors = [
                                'basic'        => '#64748b',
                                'professional' => '#2563eb',
                                'premium'      => '#7c3aed',
                            ];
                            ?>
                            <label style="display:block;margin-bottom:8px;font-weight:600;">
                                <input type="checkbox" id="wham-select-all" checked />
                                Select All
                            </label>
                            <div style="max-height:200px;overflow-y:auto;border:1px solid #ddd;padding:8px 12px;border-radius:4px;background:#fafafa;">
                                <?php foreach ( $client_map as $mid => $cfg ) :
                                    $tier = $cfg['tier'] ?? 'basic';
                                    $color = $tier_colors[ $tier ] ?? '#64748b';
                                ?>
                                    <label style="display:block;padding:3px 0;cursor:pointer;">
                                        <input type="checkbox" name="wham_client_ids[]" value="<?php echo esc_attr( $mid ); ?>" checked class="wham-client-cb" />
                                        <?php echo esc_html( $cfg['client_name'] ?? $mid ); ?>
                                        <span style="display:inline-block;padding:1px 6px;border-radius:3px;font-size:11px;font-weight:600;color:#fff;background:<?php echo esc_attr( $color ); ?>;margin-left:4px;"><?php echo esc_html( ucfirst( $tier ) ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <script>
                            (function(){
                                var all = document.getElementById('wham-select-all');
                                var cbs = document.querySelectorAll('.wham-client-cb');
                                all.addEventListener('change', function(){
                                    cbs.forEach(function(cb){ cb.checked = all.checked; });
                                });
                                // Uncheck "select all" if any individual is unchecked.
                                cbs.forEach(function(cb){
                                    cb.addEventListener('change', function(){
                                        all.checked = Array.from(cbs).every(function(c){ return c.checked; });
                                    });
                                });
                            })();
                            </script>
                        </td>
                    </tr>
                </table>

                <button type="submit" class="button button-primary button-hero">
                    Generate Reports
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

        <!-- Test Email -->
        <div class="wham-card">
            <h2>Test Email</h2>
            <p>Send a test report email to any address. Picks up the full inline data from the selected report.</p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="wham_test_report">Report</label></th>
                    <td>
                        <select id="wham_test_report" style="width:100%;">
                            <option value="">— Select a report —</option>
                            <?php
                            $test_reports = new WP_Query([
                                'post_type'      => 'wham_report',
                                'posts_per_page'  => 20,
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
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wham_test_email">Send to</label></th>
                    <td>
                        <input type="email" id="wham_test_email" class="regular-text" placeholder="you@example.com" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
                    </td>
                </tr>
            </table>
            <button type="button" id="wham-send-test-email" class="button button-primary" disabled>Send Test Email</button>
            <span id="wham-test-email-status" style="margin-left:12px;"></span>
        </div>

        <script>
        (function(){
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
                status.style.color = '#666';

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
                            status.style.color = '#059669';
                        } else {
                            status.textContent = r.data || 'Error sending email.';
                            status.style.color = '#dc2626';
                        }
                        toggleBtn();
                    })
                    .catch(function(){
                        status.textContent = 'Request failed.';
                        status.style.color = '#dc2626';
                        toggleBtn();
                    });
            });
        })();
        </script>

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
