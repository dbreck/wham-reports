<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wham-admin">
    <h1>WHAM Reports — Schedule</h1>

    <p>Configure automatic report generation and delivery. When enabled, reports will be generated and optionally emailed on a recurring monthly schedule.</p>

    <form method="post" action="options.php">
        <?php settings_fields( 'wham_schedule_settings' ); ?>

        <?php
        $autosend_enabled  = get_option( 'wham_autosend_enabled', '0' );
        $autosend_day      = (int) get_option( 'wham_autosend_day', 1 );
        $autosend_hour     = (int) get_option( 'wham_autosend_hour', 6 );
        $autosend_email    = get_option( 'wham_autosend_email', '1' );
        $autosend_excludes = json_decode( get_option( 'wham_autosend_excludes', '[]' ), true ) ?: [];
        $client_map        = json_decode( get_option( 'wham_client_map', '{}' ), true ) ?: [];
        $next_scheduled    = wp_next_scheduled( 'wham_generate_reports' );
        ?>

        <table class="form-table">
            <tr>
                <th scope="row">Enable Auto-Send</th>
                <td>
                    <label>
                        <input type="checkbox" name="wham_autosend_enabled" value="1" <?php checked( $autosend_enabled, '1' ); ?> />
                        Automatically generate reports on a monthly schedule
                    </label>
                    <?php if ( $autosend_enabled && $next_scheduled ) : ?>
                        <p class="description">Next scheduled run: <strong><?php echo esc_html( date_i18n( 'F j, Y \a\t g:i A', $next_scheduled ) ); ?></strong></p>
                    <?php elseif ( $autosend_enabled && ! $next_scheduled ) : ?>
                        <p class="description" style="color: #d63638;">Cron event not scheduled. Save settings to reschedule.</p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wham_autosend_day">Day of Month</label></th>
                <td>
                    <select id="wham_autosend_day" name="wham_autosend_day">
                        <?php for ( $d = 1; $d <= 28; $d++ ) : ?>
                            <option value="<?php echo esc_attr( $d ); ?>" <?php selected( $autosend_day, $d ); ?>><?php echo esc_html( $d ); ?></option>
                        <?php endfor; ?>
                    </select>
                    <p class="description">Reports will generate on this day each month. Limited to 1-28 to avoid month-length issues.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wham_autosend_hour">Time</label></th>
                <td>
                    <select id="wham_autosend_hour" name="wham_autosend_hour">
                        <?php for ( $h = 0; $h <= 23; $h++ ) : ?>
                            <option value="<?php echo esc_attr( $h ); ?>" <?php selected( $autosend_hour, $h ); ?>><?php echo esc_html( date( 'g:00 A', mktime( $h, 0 ) ) ); ?></option>
                        <?php endfor; ?>
                    </select>
                    <p class="description">Hour of day to run (server timezone: <?php echo esc_html( wp_timezone_string() ); ?>).</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Auto-Email Reports</th>
                <td>
                    <label>
                        <input type="checkbox" name="wham_autosend_email" value="1" <?php checked( $autosend_email, '1' ); ?> />
                        Automatically email reports to clients after generation
                    </label>
                    <p class="description">If unchecked, reports will be generated but not emailed. You can send them manually from the dashboard.</p>
                </td>
            </tr>
        </table>

        <?php if ( ! empty( $client_map ) ) : ?>
            <h2>Client Exclusions</h2>
            <p class="description">Uncheck clients to exclude them from automatic report generation.</p>
            <table class="widefat fixed" style="max-width: 600px;">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">Include</th>
                        <th>Client</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $client_map as $mid => $cfg ) : ?>
                        <tr>
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="checkbox"
                                       name="wham_autosend_include[<?php echo esc_attr( $mid ); ?>]"
                                       value="1"
                                       <?php checked( ! in_array( $mid, $autosend_excludes, true ) ); ?> />
                            </td>
                            <td><?php echo esc_html( $cfg['client_name'] ?? $mid ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <input type="hidden" name="wham_autosend_has_excludes" value="1" />
        <?php endif; ?>

        <?php if ( $autosend_enabled ) : ?>
            <div class="notice notice-info inline" style="margin-top: 16px; padding: 12px 16px;">
                <?php
                $ordinal    = date( 'jS', mktime( 0, 0, 0, 1, $autosend_day ) );
                $time_str   = date( 'g:00 A', mktime( $autosend_hour, 0 ) );
                $email_note = $autosend_email ? ' and emailed to clients' : '';
                $exclude_count = count( $autosend_excludes );
                $exclude_note  = $exclude_count > 0 ? sprintf( ' (%d client%s excluded)', $exclude_count, $exclude_count > 1 ? 's' : '' ) : '';
                ?>
                Reports will be generated on the <strong><?php echo esc_html( $ordinal ); ?></strong> of each month at <strong><?php echo esc_html( $time_str ); ?></strong><?php echo esc_html( $email_note . $exclude_note ); ?>.
            </div>
        <?php endif; ?>

        <?php submit_button( 'Save Schedule' ); ?>
    </form>
</div>
