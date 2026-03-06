<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wham-admin">
    <h1>WHAM Reports — Settings</h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'wham_reports_settings' ); ?>

        <h2>API Credentials</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="wham_monday_api_token">Monday.com API Token</label></th>
                <td>
                    <input type="password" id="wham_monday_api_token" name="wham_monday_api_token"
                           value="<?php echo esc_attr( get_option( 'wham_monday_api_token' ) ); ?>"
                           class="regular-text" autocomplete="off" />
                    <p class="description">Your Monday.com personal API token. Found at monday.com → Avatar → Developers → My Access Tokens.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wham_mainwp_app_password">MainWP Application Password</label></th>
                <td>
                    <input type="password" id="wham_mainwp_app_password" name="wham_mainwp_app_password"
                           value="<?php echo esc_attr( get_option( 'wham_mainwp_app_password' ) ); ?>"
                           class="regular-text" autocomplete="off" />
                    <p class="description">WordPress Application Password for REST API access. If the plugin runs on the same server as MainWP, this can be left blank (will use direct DB access).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wham_gsc_credentials_json">Google Search Console — Service Account JSON</label></th>
                <td>
                    <textarea id="wham_gsc_credentials_json" name="wham_gsc_credentials_json"
                              rows="6" class="large-text code"><?php echo esc_textarea( get_option( 'wham_gsc_credentials_json' ) ); ?></textarea>
                    <p class="description">Paste the contents of your Google Cloud service account JSON key file. Needs Search Console read access.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wham_ga4_credentials_json">Google Analytics 4 — Service Account JSON</label></th>
                <td>
                    <textarea id="wham_ga4_credentials_json" name="wham_ga4_credentials_json"
                              rows="6" class="large-text code"><?php echo esc_textarea( get_option( 'wham_ga4_credentials_json' ) ); ?></textarea>
                    <p class="description">Same service account can be used for both GSC and GA4. Needs Analytics read access.</p>
                </td>
            </tr>
        </table>

        <h2>Email Settings</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="wham_sender_name">Sender Name</label></th>
                <td>
                    <input type="text" id="wham_sender_name" name="wham_sender_name"
                           value="<?php echo esc_attr( get_option( 'wham_sender_name', 'WHAM Reports' ) ); ?>"
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wham_sender_email">Sender Email</label></th>
                <td>
                    <input type="email" id="wham_sender_email" name="wham_sender_email"
                           value="<?php echo esc_attr( get_option( 'wham_sender_email', get_option( 'admin_email' ) ) ); ?>"
                           class="regular-text" />
                </td>
            </tr>
        </table>

        <?php submit_button( 'Save Settings' ); ?>
    </form>
</div>
