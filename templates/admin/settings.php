<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wham-admin">
    <h1>WHAM Reports — Settings</h1>

    <div class="notice notice-info" style="padding: 12px 16px;">
        <strong>Client Dashboard Shortcode:</strong>
        <code style="font-size: 14px; padding: 2px 8px; background: #f0f0f1; border-radius: 3px;">[wham_dashboard]</code>
        <span class="description" style="margin-left: 8px;">Add this shortcode to any page to display the client report dashboard. Logged-in users with the <em>WHAM Client</em> role will see only their reports.</span>
    </div>

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

        <h2>Plugin Updates</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="wham_github_token">GitHub Personal Access Token</label></th>
                <td>
                    <input type="password" id="wham_github_token" name="wham_github_token"
                           value="<?php echo esc_attr( get_option( 'wham_github_token' ) ); ?>"
                           class="regular-text" autocomplete="off" />
                    <p class="description">Required for automatic update checks from the private GitHub repo (<code>dbreck/wham-reports</code>). Generate a fine-grained token with <strong>Contents: Read</strong> access at <a href="https://github.com/settings/personal-access-tokens" target="_blank">github.com/settings/personal-access-tokens</a>.</p>
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

        <h2>Tier Configuration</h2>
        <p class="description">Configure which report sections are included for each tier. Checked items will appear in client reports.</p>

        <?php
        $tier_defaults = [
            'basic' => [
                'maintenance' => true,
                'search'      => true,
                'analytics'   => false,
                'dev_hours'   => true,
            ],
            'professional' => [
                'maintenance'       => true,
                'maintenance_detail' => true,
                'search'            => true,
                'search_detail'     => true,
                'analytics'         => true,
                'dev_hours'         => true,
            ],
            'premium' => [
                'maintenance'       => true,
                'maintenance_detail' => true,
                'search'            => true,
                'search_detail'     => true,
                'analytics'         => true,
                'dev_hours'         => true,
            ],
        ];
        $tier_config = json_decode( get_option( 'wham_tier_config', '' ), true ) ?: $tier_defaults;

        $sections = [
            'maintenance'        => [ 'label' => 'Updates & Maintenance', 'description' => 'WordPress core, plugin, and theme update summary' ],
            'maintenance_detail' => [ 'label' => 'Maintenance Details', 'description' => 'Individual plugin update log with version numbers' ],
            'search'             => [ 'label' => 'Search Console (Aggregate)', 'description' => 'Clicks, impressions, CTR, and average position' ],
            'search_detail'      => [ 'label' => 'Search Console (Detailed)', 'description' => 'Top queries and pages with period comparison' ],
            'analytics'          => [ 'label' => 'Google Analytics', 'description' => 'Sessions, users, bounce rate, traffic sources, top pages' ],
            'dev_hours'          => [ 'label' => 'Dev Hours', 'description' => 'Monday.com time tracking summary' ],
        ];

        $tiers = [ 'basic' => 'Basic', 'professional' => 'Professional', 'premium' => 'Premium' ];
        ?>

        <table class="widefat fixed" style="max-width: 800px;">
            <thead>
                <tr>
                    <th style="width: 40%;">Report Section</th>
                    <?php foreach ( $tiers as $tier_key => $tier_label ) : ?>
                        <th style="text-align: center;"><?php echo esc_html( $tier_label ); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $sections as $section_key => $section ) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( $section['label'] ); ?></strong><br>
                            <span class="description"><?php echo esc_html( $section['description'] ); ?></span>
                        </td>
                        <?php foreach ( $tiers as $tier_key => $tier_label ) : ?>
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="checkbox"
                                       name="wham_tier_config[<?php echo esc_attr( $tier_key ); ?>][<?php echo esc_attr( $section_key ); ?>]"
                                       value="1"
                                       <?php checked( ! empty( $tier_config[ $tier_key ][ $section_key ] ) ); ?> />
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>PDF Settings</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="wham_pdf_template">PDF Template</label></th>
                <td>
                    <?php $pdf_template = get_option( 'wham_pdf_template', 'auto' ); ?>
                    <select id="wham_pdf_template" name="wham_pdf_template">
                        <option value="auto" <?php selected( $pdf_template, 'auto' ); ?>>Auto (Basic template for Basic tier, Professional for others)</option>
                        <option value="basic" <?php selected( $pdf_template, 'basic' ); ?>>Always use Basic template</option>
                        <option value="professional" <?php selected( $pdf_template, 'professional' ); ?>>Always use Professional template</option>
                    </select>
                    <p class="description">Choose which PDF template to use for generated reports.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wham_company_name">Company Name (in reports)</label></th>
                <td>
                    <input type="text" id="wham_company_name" name="wham_company_name"
                           value="<?php echo esc_attr( get_option( 'wham_company_name', 'WHAM' ) ); ?>"
                           class="regular-text" />
                    <p class="description">Displayed in the report header and footer.</p>
                </td>
            </tr>
        </table>

        <?php submit_button( 'Save Settings' ); ?>
    </form>
</div>
