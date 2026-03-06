<?php defined( 'ABSPATH' ) || exit;

// Handle form submission.
if ( isset( $_POST['wham_client_map_save'] ) && check_admin_referer( 'wham_save_mapping' ) ) {
    $map = [];
    $ids = $_POST['monday_id'] ?? [];
    foreach ( $ids as $i => $monday_id ) {
        $monday_id = sanitize_text_field( $monday_id );
        if ( empty( $monday_id ) ) continue;
        $map[ $monday_id ] = [
            'client_name'    => sanitize_text_field( $_POST['client_name'][ $i ] ?? '' ),
            'client_url'     => esc_url_raw( $_POST['client_url'][ $i ] ?? '' ),
            'tier'           => sanitize_text_field( $_POST['tier'][ $i ] ?? 'basic' ),
            'mainwp_site_id' => sanitize_text_field( $_POST['mainwp_site_id'][ $i ] ?? '' ),
            'gsc_property'   => sanitize_text_field( $_POST['gsc_property'][ $i ] ?? '' ),
            'ga4_property'   => sanitize_text_field( $_POST['ga4_property'][ $i ] ?? '' ),
            'client_email'   => sanitize_email( $_POST['client_email'][ $i ] ?? '' ),
            'wp_user_id'     => absint( $_POST['wp_user_id'][ $i ] ?? 0 ),
        ];
    }
    update_option( 'wham_client_map', wp_json_encode( $map ) );
    echo '<div class="notice notice-success is-dismissible"><p>Client mapping saved.</p></div>';
}

$client_map = \WHAM_Reports::get_client_map();
?>
<div class="wrap wham-admin">
    <h1>Client Mapping</h1>
    <p>Map each Monday.com client to their MainWP site ID, Google Search Console property, GA4 property ID, and contact info.</p>

    <form method="post">
        <?php wp_nonce_field( 'wham_save_mapping' ); ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Monday ID</th>
                    <th>Client Name</th>
                    <th>Website URL</th>
                    <th>Tier</th>
                    <th>MainWP Site ID</th>
                    <th>GSC Property</th>
                    <th>GA4 Property ID</th>
                    <th>Client Email</th>
                    <th>WP User ID</th>
                </tr>
            </thead>
            <tbody id="wham-mapping-rows">
                <?php
                $i = 0;
                foreach ( $client_map as $monday_id => $config ) :
                ?>
                <tr>
                    <td><input type="text" name="monday_id[<?php echo $i; ?>]" value="<?php echo esc_attr( $monday_id ); ?>" class="small-text" /></td>
                    <td><input type="text" name="client_name[<?php echo $i; ?>]" value="<?php echo esc_attr( $config['client_name'] ?? '' ); ?>" /></td>
                    <td><input type="url" name="client_url[<?php echo $i; ?>]" value="<?php echo esc_attr( $config['client_url'] ?? '' ); ?>" /></td>
                    <td>
                        <select name="tier[<?php echo $i; ?>]">
                            <option value="basic" <?php selected( $config['tier'] ?? '', 'basic' ); ?>>Basic</option>
                            <option value="professional" <?php selected( $config['tier'] ?? '', 'professional' ); ?>>Professional</option>
                            <option value="premium" <?php selected( $config['tier'] ?? '', 'premium' ); ?>>Premium</option>
                        </select>
                    </td>
                    <td><input type="text" name="mainwp_site_id[<?php echo $i; ?>]" value="<?php echo esc_attr( $config['mainwp_site_id'] ?? '' ); ?>" class="small-text" /></td>
                    <td><input type="text" name="gsc_property[<?php echo $i; ?>]" value="<?php echo esc_attr( $config['gsc_property'] ?? '' ); ?>" /></td>
                    <td><input type="text" name="ga4_property[<?php echo $i; ?>]" value="<?php echo esc_attr( $config['ga4_property'] ?? '' ); ?>" class="small-text" /></td>
                    <td><input type="email" name="client_email[<?php echo $i; ?>]" value="<?php echo esc_attr( $config['client_email'] ?? '' ); ?>" /></td>
                    <td><input type="number" name="wp_user_id[<?php echo $i; ?>]" value="<?php echo esc_attr( $config['wp_user_id'] ?? '' ); ?>" class="small-text" /></td>
                </tr>
                <?php $i++; endforeach; ?>

                <!-- Empty rows for adding new clients -->
                <?php for ( $j = 0; $j < 3; $j++ ) : $idx = $i + $j; ?>
                <tr>
                    <td><input type="text" name="monday_id[<?php echo $idx; ?>]" value="" class="small-text" /></td>
                    <td><input type="text" name="client_name[<?php echo $idx; ?>]" value="" /></td>
                    <td><input type="url" name="client_url[<?php echo $idx; ?>]" value="" /></td>
                    <td>
                        <select name="tier[<?php echo $idx; ?>]">
                            <option value="basic">Basic</option>
                            <option value="professional">Professional</option>
                            <option value="premium">Premium</option>
                        </select>
                    </td>
                    <td><input type="text" name="mainwp_site_id[<?php echo $idx; ?>]" value="" class="small-text" /></td>
                    <td><input type="text" name="gsc_property[<?php echo $idx; ?>]" value="" /></td>
                    <td><input type="text" name="ga4_property[<?php echo $idx; ?>]" value="" class="small-text" /></td>
                    <td><input type="email" name="client_email[<?php echo $idx; ?>]" value="" /></td>
                    <td><input type="number" name="wp_user_id[<?php echo $idx; ?>]" value="" class="small-text" /></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <p class="submit">
            <input type="submit" name="wham_client_map_save" class="button button-primary" value="Save Client Mapping" />
        </p>
    </form>

    <hr />
    <h2>Pre-Populated Data from Monday.com</h2>
    <p>Below is the known client data from Monday.com board <?php echo esc_html( WHAM_REPORTS_MONDAY_BOARD_ID ); ?>. Use this as a reference when filling in the mapping above.</p>
    <table class="widefat striped">
        <thead>
            <tr><th>Monday ID</th><th>Client</th><th>Tier</th><th>URL</th><th>GSC Property (Known)</th></tr>
        </thead>
        <tbody>
            <tr><td>9141194308</td><td>Mira Mar Sarasota</td><td>Professional</td><td>miramarsarasota.com</td><td>sc-domain:miramarsarasota.com</td></tr>
            <tr><td>9162419003</td><td>St. Pete Dermatology</td><td>Basic</td><td>stpetederm.com</td><td>❌ Not in GSC</td></tr>
            <tr><td>9141299484</td><td>Altera Wellness</td><td>Basic</td><td>alterawellness.com</td><td>sc-domain:alterawellness.com</td></tr>
            <tr><td>9714942359</td><td>Peregrine Construction</td><td>Basic</td><td>peregrineconstructiongroup.com</td><td>sc-domain:peregrineconstructiongroup.com</td></tr>
            <tr><td>9545788260</td><td>Windstar Homes</td><td>Professional</td><td>windstarhomes.com</td><td>sc-domain:windstarhomes.com</td></tr>
            <tr><td>9955710484</td><td>Flood Guard USA</td><td>Basic</td><td>floodguardusa.com</td><td>sc-domain:floodguardusa.com</td></tr>
            <tr><td>9141367401</td><td>Your Tampa Expert</td><td>Basic</td><td>yourtampaexpert.com</td><td>⚠️ Unverified</td></tr>
            <tr><td>9141264648</td><td>Seren Hospitality</td><td>Basic</td><td>serenhospitality.com</td><td>sc-domain:serenabythesea.com</td></tr>
            <tr><td>9260077438</td><td>3rd & 3rd Apartments</td><td>Basic</td><td>3rdand3rdapartments.com</td><td>sc-domain:3rdand3rdapartments.com</td></tr>
            <tr><td>10083094386</td><td>Spatial HQ</td><td>Basic</td><td>spatial-hq.com</td><td>sc-domain:spatial-hq.com</td></tr>
            <tr><td>10979594433</td><td>Backstreets Capital</td><td>Basic</td><td>backstreetscapital.com</td><td>❌ Not in GSC</td></tr>
        </tbody>
    </table>
</div>
