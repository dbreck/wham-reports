<?php defined( 'ABSPATH' ) || exit;

// ── Handle form submission ───────────────────────────────────────────────────
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
        ];
    }
    update_option( 'wham_client_map', wp_json_encode( $map ) );

    // ── Sync user ↔ client access via user meta ──────────────────────────
    $prev_assigned = get_users([
        'meta_key'   => '_wham_monday_client_id',
        'meta_compare' => 'EXISTS',
        'fields'     => 'ID',
    ]);
    foreach ( $prev_assigned as $uid ) {
        delete_user_meta( (int) $uid, '_wham_monday_client_id' );
    }

    $user_assignments = $_POST['wham_users'] ?? [];
    foreach ( $user_assignments as $monday_id => $user_ids ) {
        $monday_id = sanitize_text_field( $monday_id );
        if ( empty( $monday_id ) || ! isset( $map[ $monday_id ] ) ) continue;
        foreach ( (array) $user_ids as $uid ) {
            $uid = absint( $uid );
            if ( $uid ) {
                update_user_meta( $uid, '_wham_monday_client_id', $monday_id );
            }
        }
    }

    echo '<div class="notice notice-success is-dismissible"><p>Client mapping and user access saved.</p></div>';
}

// ── Load data ────────────────────────────────────────────────────────────────
$client_map = \WHAM_Reports::get_client_map();

$all_users = get_users([
    'orderby' => 'display_name',
    'order'   => 'ASC',
    'fields'  => [ 'ID', 'display_name', 'user_email', 'user_login' ],
]);
$picker_users = array_filter( $all_users, function( $u ) {
    return ! user_can( $u->ID, 'manage_options' );
});

$user_access_map = [];
foreach ( $picker_users as $u ) {
    $mid = get_user_meta( $u->ID, '_wham_monday_client_id', true );
    if ( $mid ) {
        $user_access_map[ $mid ][] = (int) $u->ID;
    }
}

// JSON-encode users for JS row creation.
$picker_users_json = wp_json_encode( array_values( array_map( function( $u ) {
    return [
        'id'    => (int) $u->ID,
        'name'  => $u->display_name,
        'email' => $u->user_email,
        'login' => $u->user_login,
    ];
}, $picker_users ) ) );
?>
<div class="wrap wham-admin wham-mapping-page">
    <div class="wham-mapping-header">
        <div>
            <h1>Client Mapping</h1>
            <p class="wham-subtitle">Map Monday.com clients to data sources and manage dashboard access.</p>
        </div>
        <div class="wham-mapping-header-actions">
            <span class="wham-client-count"><?php echo count( $client_map ); ?> client<?php echo count( $client_map ) !== 1 ? 's' : ''; ?> mapped</span>
        </div>
    </div>

    <form method="post" id="wham-mapping-form">
        <?php wp_nonce_field( 'wham_save_mapping' ); ?>

        <div class="wham-mapping-card">
            <div class="wham-mapping-card-header">
                <h2>Client Data Sources</h2>
                <div class="wham-mapping-card-actions">
                    <span class="wham-format-hint">GSC: <code>sc-domain:example.com</code> or <code>https://example.com/</code></span>
                    <button type="button" class="button" id="wham-add-row">+ Add Client</button>
                </div>
            </div>

            <div class="wham-mapping-table-wrap">
                <table class="wham-mapping-table" id="wham-mapping-table">
                    <thead>
                        <tr>
                            <th class="wham-col-id">Monday ID</th>
                            <th class="wham-col-name">Client Name</th>
                            <th class="wham-col-url">Website URL</th>
                            <th class="wham-col-tier">Tier</th>
                            <th class="wham-col-mainwp">MainWP</th>
                            <th class="wham-col-gsc">GSC Property</th>
                            <th class="wham-col-ga4">GA4 ID</th>
                            <th class="wham-col-email">Client Email</th>
                            <th class="wham-col-users">Users</th>
                            <th class="wham-col-actions"></th>
                        </tr>
                    </thead>
                    <tbody id="wham-mapping-rows">
                        <?php
                        $i = 0;
                        foreach ( $client_map as $monday_id => $config ) :
                            $assigned_ids = $user_access_map[ $monday_id ] ?? [];
                        ?>
                        <tr class="wham-mapping-row" data-row="<?php echo $i; ?>">
                            <td><input type="text" name="monday_id[<?php echo $i; ?>]" value="<?php echo esc_attr( $monday_id ); ?>" placeholder="ID" /></td>
                            <td><input type="text" name="client_name[<?php echo $i; ?>]" value="<?php echo esc_attr( $config['client_name'] ?? '' ); ?>" placeholder="Name" /></td>
                            <td><input type="url" name="client_url[<?php echo $i; ?>]" value="<?php echo esc_attr( $config['client_url'] ?? '' ); ?>" placeholder="https://..." /></td>
                            <td>
                                <select name="tier[<?php echo $i; ?>]">
                                    <option value="basic" <?php selected( $config['tier'] ?? '', 'basic' ); ?>>Basic</option>
                                    <option value="professional" <?php selected( $config['tier'] ?? '', 'professional' ); ?>>Pro</option>
                                    <option value="premium" <?php selected( $config['tier'] ?? '', 'premium' ); ?>>Premium</option>
                                </select>
                            </td>
                            <td><input type="text" name="mainwp_site_id[<?php echo $i; ?>]" value="<?php echo esc_attr( $config['mainwp_site_id'] ?? '' ); ?>" placeholder="—" /></td>
                            <td><input type="text" name="gsc_property[<?php echo $i; ?>]" value="<?php echo esc_attr( $config['gsc_property'] ?? '' ); ?>" placeholder="sc-domain:..." /></td>
                            <td><input type="text" name="ga4_property[<?php echo $i; ?>]" value="<?php echo esc_attr( $config['ga4_property'] ?? '' ); ?>" placeholder="—" /></td>
                            <td><input type="email" name="client_email[<?php echo $i; ?>]" value="<?php echo esc_attr( $config['client_email'] ?? '' ); ?>" placeholder="email" /></td>
                            <td class="wham-users-cell">
                                <button type="button" class="wham-users-toggle" data-mid="<?php echo esc_attr( $monday_id ); ?>">
                                    <span class="wham-users-count"><?php echo count( $assigned_ids ); ?></span>
                                    <span class="dashicons dashicons-admin-users"></span>
                                </button>
                                <div class="wham-users-panel" id="wham-users-<?php echo esc_attr( $monday_id ); ?>" style="display:none;">
                                    <div class="wham-users-search">
                                        <input type="text" placeholder="Filter users..." class="wham-user-filter" />
                                    </div>
                                    <div class="wham-users-list">
                                        <?php foreach ( $picker_users as $u ) : ?>
                                        <label class="wham-user-option" data-search="<?php echo esc_attr( strtolower( $u->display_name . ' ' . $u->user_email . ' ' . $u->user_login ) ); ?>">
                                            <input type="checkbox"
                                                name="wham_users[<?php echo esc_attr( $monday_id ); ?>][]"
                                                value="<?php echo (int) $u->ID; ?>"
                                                <?php checked( in_array( (int) $u->ID, $assigned_ids, true ) ); ?>
                                            />
                                            <span class="wham-user-name"><?php echo esc_html( $u->display_name ); ?></span>
                                            <span class="wham-user-email"><?php echo esc_html( $u->user_email ); ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                        <?php if ( empty( $picker_users ) ) : ?>
                                            <p class="wham-users-empty">No non-admin users found. <a href="<?php echo admin_url( 'user-new.php' ); ?>">Create one</a>.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="wham-row-actions">
                                <button type="button" class="wham-remove-row" title="Remove client">&times;</button>
                            </td>
                        </tr>
                        <?php $i++; endforeach; ?>
                    </tbody>
                </table>

                <?php if ( empty( $client_map ) ) : ?>
                <div class="wham-empty-state" id="wham-empty-state">
                    <span class="dashicons dashicons-groups"></span>
                    <p>No clients mapped yet. Add one manually or use the reference table below.</p>
                    <button type="button" class="button" id="wham-add-first-row">+ Add Your First Client</button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="wham-mapping-save-bar">
            <input type="submit" name="wham_client_map_save" class="button button-primary button-hero" value="Save Client Mapping" />
            <span class="wham-save-hint">Saves all mapping data and user access assignments.</span>
        </div>
    </form>

    <?php
    // Look up GA4 properties.
    $ga4_source     = new \WHAM_Reports\GA4_Source();
    $ga4_properties = $ga4_source->list_properties();

    $ga4_by_domain = [];
    foreach ( $ga4_properties as $prop_id => $prop_info ) {
        $prop_url = $prop_info['url'] ?? '';
        if ( $prop_url ) {
            $domain = strtolower( trim( parse_url( $prop_url, PHP_URL_HOST ) ?: '', '.' ) );
            $domain = preg_replace( '/^www\./', '', $domain );
            if ( $domain ) {
                $ga4_by_domain[ $domain ] = $prop_id;
            }
        }
    }

    // Look up MainWP child sites.
    $mainwp_source = new \WHAM_Reports\MainWP_Source();
    $mainwp_sites  = $mainwp_source->list_sites();

    $mainwp_by_domain = [];
    foreach ( $mainwp_sites as $site_id => $site_info ) {
        $domain = $site_info['domain'] ?? '';
        if ( $domain ) {
            $mainwp_by_domain[ $domain ] = $site_id;
        }
    }

    $reference = [
        '9141194308'  => [ 'Mira Mar Sarasota',    'Professional', 'miramarsarasota.com',           'sc-domain:miramarsarasota.com' ],
        '9162419003'  => [ 'St. Pete Dermatology',  'Basic',        'stpetederm.com',                '' ],
        '9141299484'  => [ 'Altera Wellness',       'Basic',        'alterawellness.com',            'sc-domain:alterawellness.com' ],
        '9714942359'  => [ 'Peregrine Construction', 'Basic',       'peregrineconstructiongroup.com', 'https://peregrineconstructiongroup.com/' ],
        '9545788260'  => [ 'Windstar Homes',        'Professional', 'windstarhomes.com',             'https://windstarhomes.com/' ],
        '9955710484'  => [ 'Flood Guard USA',       'Basic',        'floodguardusa.com',             'sc-domain:floodguardusa.com' ],
        '9141367401'  => [ 'Your Tampa Expert',     'Basic',        'yourtampaexpert.com',           '' ],
        '9141264648'  => [ 'Seren Hospitality',     'Basic',        'serenhospitality.com',          'https://serenabythesea.com/' ],
        '9260077438'  => [ '3rd & 3rd Apartments',  'Basic',        '3rdand3rdapartments.com',       'https://3rdand3rdapartments.com/' ],
        '10083094386' => [ 'Spatial HQ',            'Basic',        'spatial-hq.com',                'sc-domain:spatial-hq.com' ],
        '10979594433' => [ 'Backstreets Capital',   'Basic',        'backstreetscapital.com',        '' ],
    ];
    ?>

    <div class="wham-mapping-card wham-ref-card">
        <div class="wham-mapping-card-header">
            <h2>Monday.com Reference Data</h2>
            <span class="wham-ref-hint">Click <strong>+</strong> to add a client to the mapping above. Auto-matched MainWP and GA4 IDs are filled in.</span>
        </div>

        <table class="wham-ref-table" id="wham-reference-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Monday ID</th>
                    <th>Client</th>
                    <th>Tier</th>
                    <th>URL</th>
                    <th>MainWP</th>
                    <th>GSC Property</th>
                    <th>GA4 ID</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $reference as $mid => $ref ) :
                    $domain      = strtolower( preg_replace( '/^www\./', '', $ref[2] ) );
                    $ga4_id      = $ga4_by_domain[ $domain ] ?? '';
                    $mainwp_id   = $mainwp_by_domain[ $domain ] ?? '';
                    $gsc_label   = $ref[3] ?: '—';
                    $in_map      = isset( $client_map[ $mid ] );
                ?>
                <tr<?php echo $in_map ? ' class="wham-ref-mapped"' : ''; ?>>
                    <td>
                        <button type="button" class="wham-add-ref"
                            data-mid="<?php echo esc_attr( $mid ); ?>"
                            data-name="<?php echo esc_attr( $ref[0] ); ?>"
                            data-tier="<?php echo esc_attr( strtolower( $ref[1] ) ); ?>"
                            data-url="<?php echo esc_attr( $ref[2] ); ?>"
                            data-mainwp="<?php echo esc_attr( $mainwp_id ); ?>"
                            data-gsc="<?php echo esc_attr( $ref[3] ); ?>"
                            data-ga4="<?php echo esc_attr( $ga4_id ); ?>"
                            title="<?php echo $in_map ? 'Already mapped' : 'Add to mapping'; ?>"
                        ><?php echo $in_map ? '&#10003;' : '+'; ?></button>
                    </td>
                    <td class="wham-ref-id"><?php echo esc_html( $mid ); ?></td>
                    <td class="wham-ref-name"><?php echo esc_html( $ref[0] ); ?></td>
                    <td><span class="wham-tier-badge wham-tier-<?php echo esc_attr( strtolower( $ref[1] ) ); ?>"><?php echo esc_html( $ref[1] ); ?></span></td>
                    <td class="wham-ref-url"><?php echo esc_html( $ref[2] ); ?></td>
                    <td><?php echo $mainwp_id ? esc_html( $mainwp_id ) : '<span class="wham-na">—</span>'; ?></td>
                    <td class="wham-ref-gsc"><?php echo esc_html( $gsc_label ); ?></td>
                    <td><?php echo $ga4_id ? esc_html( $ga4_id ) : '<span class="wham-na">—</span>'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( empty( $ga4_properties ) ) : ?>
            <p class="wham-ref-note">
                GA4 property lookup returned no results. Ensure the Analytics Admin API is enabled and properties are shared with <code><?php
                    $creds = json_decode( get_option( 'wham_ga4_credentials_json' ) ?: get_option( 'wham_gsc_credentials_json', '' ), true );
                    echo esc_html( $creds['client_email'] ?? 'your service account' );
                ?></code>.
            </p>
        <?php endif; ?>
    </div>
</div>

<style>
    /* ── Page layout ─────────────────────────────────────────────────── */
    .wham-mapping-page { max-width: 100%; }

    .wham-mapping-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 24px;
    }
    .wham-mapping-header h1 { margin-bottom: 2px; }
    .wham-subtitle { color: #646970; margin: 0; font-size: 14px; }
    .wham-client-count {
        background: #f0f0f1;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 13px;
        color: #50575e;
        font-weight: 500;
    }

    /* ── Cards ────────────────────────────────────────────────────── */
    .wham-mapping-card {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 6px;
        margin-bottom: 24px;
        overflow: hidden;
    }
    .wham-mapping-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #e2e4e7;
        background: #f9fafb;
    }
    .wham-mapping-card-header h2 {
        margin: 0;
        font-size: 15px;
        font-weight: 600;
        color: #1d2327;
    }
    .wham-mapping-card-actions {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .wham-format-hint {
        font-size: 12px;
        color: #888;
    }
    .wham-format-hint code {
        font-size: 11px;
        background: #eef;
        padding: 1px 4px;
        border-radius: 3px;
    }

    /* ── Mapping table ────────────────────────────────────────────── */
    .wham-mapping-table-wrap { overflow-x: auto; }

    .wham-mapping-table {
        width: 100%;
        border-collapse: collapse;
        border-spacing: 0;
    }
    .wham-mapping-table thead th {
        padding: 10px 8px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #646970;
        border-bottom: 2px solid #e2e4e7;
        text-align: left;
        white-space: nowrap;
    }
    .wham-mapping-table tbody td {
        padding: 6px 4px;
        vertical-align: middle;
        border-bottom: 1px solid #f0f0f1;
    }
    .wham-mapping-row:hover { background: #f9fafb; }

    .wham-mapping-table input[type="text"],
    .wham-mapping-table input[type="url"],
    .wham-mapping-table input[type="email"] {
        width: 100%;
        padding: 6px 8px;
        font-size: 13px;
        border: 1px solid transparent;
        border-radius: 4px;
        background: transparent;
        transition: border-color 0.15s, background 0.15s;
    }
    .wham-mapping-table input:focus,
    .wham-mapping-table input:hover {
        border-color: #c3c4c7;
        background: #fff;
        outline: none;
    }
    .wham-mapping-table input:focus {
        border-color: #2271b1;
        box-shadow: 0 0 0 1px #2271b1;
    }
    .wham-mapping-table select {
        padding: 5px 6px;
        font-size: 13px;
        border: 1px solid transparent;
        border-radius: 4px;
        background: transparent;
        cursor: pointer;
    }
    .wham-mapping-table select:hover,
    .wham-mapping-table select:focus {
        border-color: #c3c4c7;
        background: #fff;
    }

    /* Column widths */
    .wham-col-id { width: 110px; }
    .wham-col-name { width: 160px; }
    .wham-col-url { width: 180px; }
    .wham-col-tier { width: 90px; }
    .wham-col-mainwp { width: 70px; }
    .wham-col-gsc { width: 200px; }
    .wham-col-ga4 { width: 100px; }
    .wham-col-email { width: 180px; }
    .wham-col-users { width: 64px; text-align: center; }
    .wham-col-actions { width: 36px; }

    /* Row actions */
    .wham-row-actions { text-align: center; }
    .wham-remove-row {
        width: 24px;
        height: 24px;
        border: none;
        background: none;
        color: #ccc;
        font-size: 18px;
        cursor: pointer;
        border-radius: 4px;
        line-height: 1;
        transition: color 0.15s, background 0.15s;
    }
    .wham-remove-row:hover {
        color: #d63638;
        background: #fcecec;
    }

    /* Empty state */
    .wham-empty-state {
        padding: 48px 20px;
        text-align: center;
        color: #888;
    }
    .wham-empty-state .dashicons {
        font-size: 36px;
        width: 36px;
        height: 36px;
        color: #c3c4c7;
        margin-bottom: 12px;
    }
    .wham-empty-state p { margin: 0 0 16px; font-size: 14px; }

    /* Row flash animation */
    .wham-row-flash { animation: wham-flash 0.8s ease; }
    @keyframes wham-flash {
        0%, 100% { background: transparent; }
        30% { background: #dcfce7; }
    }

    /* New row highlight */
    .wham-row-new { animation: wham-new-row 0.4s ease; }
    @keyframes wham-new-row {
        from { background: #eff6ff; }
        to { background: transparent; }
    }

    /* ── Save bar ─────────────────────────────────────────────────── */
    .wham-mapping-save-bar {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 32px;
    }
    .wham-save-hint { color: #888; font-size: 13px; }

    /* ── User picker ──────────────────────────────────────────────── */
    .wham-users-cell { position: relative; text-align: center; }
    .wham-users-toggle {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 4px 8px;
        border: 1px solid transparent;
        border-radius: 4px;
        background: none;
        cursor: pointer;
        font-size: 13px;
        color: #50575e;
        transition: all 0.15s;
    }
    .wham-users-toggle:hover {
        border-color: #c3c4c7;
        background: #f0f0f1;
    }
    .wham-users-toggle .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
        line-height: 16px;
        color: #888;
    }
    .wham-users-count {
        font-weight: 600;
        min-width: 14px;
        text-align: center;
    }
    .wham-users-panel {
        position: absolute;
        right: 0;
        top: 100%;
        z-index: 100;
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 6px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        width: 280px;
        max-height: 300px;
        display: flex;
        flex-direction: column;
    }
    .wham-users-search { padding: 8px; border-bottom: 1px solid #eee; }
    .wham-users-search input {
        width: 100%;
        padding: 6px 8px;
        font-size: 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .wham-users-list { overflow-y: auto; padding: 4px 0; flex: 1; }
    .wham-user-option {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        cursor: pointer;
        font-size: 12px;
        line-height: 1.3;
        transition: background 0.1s;
    }
    .wham-user-option:hover { background: #f0f6fc; }
    .wham-user-option input[type="checkbox"] { margin: 0; flex-shrink: 0; }
    .wham-user-name { font-weight: 600; white-space: nowrap; }
    .wham-user-email { color: #888; font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .wham-users-empty { padding: 12px; color: #888; font-size: 12px; margin: 0; }
    .wham-user-option.wham-hidden { display: none; }

    /* New row user placeholder */
    .wham-users-placeholder {
        font-size: 11px;
        color: #aaa;
        font-style: italic;
    }

    /* ── Reference table ──────────────────────────────────────────── */
    .wham-ref-card { margin-top: 8px; }
    .wham-ref-hint { font-size: 12px; color: #888; }
    .wham-ref-table {
        width: 100%;
        border-collapse: collapse;
    }
    .wham-ref-table thead th {
        padding: 10px 10px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #646970;
        border-bottom: 2px solid #e2e4e7;
        text-align: left;
    }
    .wham-ref-table tbody td {
        padding: 8px 10px;
        font-size: 13px;
        border-bottom: 1px solid #f0f0f1;
    }
    .wham-ref-table tbody tr:hover { background: #f9fafb; }

    .wham-ref-mapped { opacity: 0.45; }
    .wham-ref-mapped .wham-add-ref { color: #16a34a; }

    .wham-add-ref {
        width: 28px;
        height: 28px;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        background: #fff;
        cursor: pointer;
        font-size: 16px;
        font-weight: 700;
        line-height: 1;
        color: #2271b1;
        transition: all 0.15s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .wham-add-ref:hover {
        background: #2271b1;
        color: #fff;
        border-color: #2271b1;
    }

    .wham-ref-id { font-family: monospace; font-size: 12px; color: #888; }
    .wham-ref-name { font-weight: 500; }
    .wham-ref-url { color: #646970; }
    .wham-ref-gsc { font-family: monospace; font-size: 12px; }
    .wham-na { color: #ccc; }

    /* Tier badges */
    .wham-tier-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .wham-tier-basic { background: #f0f0f1; color: #646970; }
    .wham-tier-professional { background: #dbeafe; color: #1e40af; }
    .wham-tier-premium { background: #fef3c7; color: #92400e; }

    .wham-ref-note {
        padding: 12px 20px;
        margin: 0;
        font-size: 12px;
        color: #888;
        border-top: 1px solid #f0f0f1;
        font-style: italic;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var mappingBody = document.getElementById('wham-mapping-rows');
    var emptyState  = document.getElementById('wham-empty-state');
    var pickerUsers = <?php echo $picker_users_json; ?>;

    function getNextIndex() {
        var rows = mappingBody.querySelectorAll('tr');
        var max = -1;
        rows.forEach(function(row) {
            var idx = parseInt(row.getAttribute('data-row'), 10);
            if (!isNaN(idx) && idx > max) max = idx;
        });
        return max + 1;
    }

    function hideEmptyState() {
        if (emptyState) emptyState.style.display = 'none';
    }

    function showEmptyStateIfNeeded() {
        if (emptyState && mappingBody.querySelectorAll('tr').length === 0) {
            emptyState.style.display = '';
        }
    }

    function buildUserPanel(idx, mondayId) {
        if (!pickerUsers.length) {
            return '<span class="wham-users-placeholder">Save first</span>';
        }

        var mid = mondayId || '__new_' + idx;
        var html = '<button type="button" class="wham-users-toggle" data-mid="' + mid + '">' +
            '<span class="wham-users-count">0</span>' +
            '<span class="dashicons dashicons-admin-users"></span></button>' +
            '<div class="wham-users-panel" id="wham-users-' + mid + '" style="display:none;">' +
            '<div class="wham-users-search"><input type="text" placeholder="Filter users..." class="wham-user-filter" /></div>' +
            '<div class="wham-users-list">';

        pickerUsers.forEach(function(u) {
            html += '<label class="wham-user-option" data-search="' +
                (u.name + ' ' + u.email + ' ' + u.login).toLowerCase() + '">' +
                '<input type="checkbox" name="wham_users[' + mid + '][]" value="' + u.id + '" />' +
                '<span class="wham-user-name">' + escHtml(u.name) + '</span>' +
                '<span class="wham-user-email">' + escHtml(u.email) + '</span></label>';
        });

        html += '</div></div>';
        return html;
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function createRow(prefill) {
        var idx = getNextIndex();
        var data = prefill || {};
        var row = document.createElement('tr');
        row.className = 'wham-mapping-row wham-row-new';
        row.setAttribute('data-row', idx);

        row.innerHTML =
            '<td><input type="text" name="monday_id[' + idx + ']" value="' + (data.mid || '') + '" placeholder="ID" /></td>' +
            '<td><input type="text" name="client_name[' + idx + ']" value="' + (data.name || '') + '" placeholder="Name" /></td>' +
            '<td><input type="url" name="client_url[' + idx + ']" value="' + (data.url || '') + '" placeholder="https://..." /></td>' +
            '<td><select name="tier[' + idx + ']">' +
                '<option value="basic"' + (data.tier === 'basic' ? ' selected' : '') + '>Basic</option>' +
                '<option value="professional"' + (data.tier === 'professional' ? ' selected' : '') + '>Pro</option>' +
                '<option value="premium"' + (data.tier === 'premium' ? ' selected' : '') + '>Premium</option>' +
            '</select></td>' +
            '<td><input type="text" name="mainwp_site_id[' + idx + ']" value="' + (data.mainwp || '') + '" placeholder="—" /></td>' +
            '<td><input type="text" name="gsc_property[' + idx + ']" value="' + (data.gsc || '') + '" placeholder="sc-domain:..." /></td>' +
            '<td><input type="text" name="ga4_property[' + idx + ']" value="' + (data.ga4 || '') + '" placeholder="—" /></td>' +
            '<td><input type="email" name="client_email[' + idx + ']" value="' + (data.email || '') + '" placeholder="email" /></td>' +
            '<td class="wham-users-cell">' + buildUserPanel(idx, data.mid) + '</td>' +
            '<td class="wham-row-actions"><button type="button" class="wham-remove-row" title="Remove client">&times;</button></td>';

        mappingBody.appendChild(row);
        hideEmptyState();
        bindRowEvents(row);
        return row;
    }

    function bindRowEvents(row) {
        // Remove button.
        var removeBtn = row.querySelector('.wham-remove-row');
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                if (row.querySelector('input[name^="monday_id"]').value.trim() !== '') {
                    if (!confirm('Remove this client from the mapping?')) return;
                }
                row.remove();
                showEmptyStateIfNeeded();
                updateClientCount();
            });
        }

        // User picker toggle.
        var toggleBtn = row.querySelector('.wham-users-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                var panel = this.nextElementSibling;
                var isOpen = panel.style.display !== 'none';
                closeAllPanels();
                if (!isOpen) {
                    panel.style.display = 'flex';
                    var fi = panel.querySelector('.wham-user-filter');
                    if (fi) fi.focus();
                }
            });
        }

        // User filter.
        var filterInput = row.querySelector('.wham-user-filter');
        if (filterInput) {
            filterInput.addEventListener('input', function() {
                var term = this.value.toLowerCase();
                this.closest('.wham-users-panel').querySelectorAll('.wham-user-option').forEach(function(opt) {
                    opt.classList.toggle('wham-hidden', term && opt.dataset.search.indexOf(term) === -1);
                });
            });
        }

        // Checkbox count update.
        row.querySelectorAll('.wham-users-panel input[type="checkbox"]').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var panel = this.closest('.wham-users-panel');
                var countEl = panel.previousElementSibling.querySelector('.wham-users-count');
                if (countEl) {
                    countEl.textContent = panel.querySelectorAll('input[type="checkbox"]:checked').length;
                }
            });
        });

        // Update user panel names when monday_id changes (for new rows).
        var midInput = row.querySelector('input[name^="monday_id"]');
        if (midInput) {
            midInput.addEventListener('change', function() {
                var newMid = this.value.trim();
                if (!newMid) return;
                var panel = row.querySelector('.wham-users-panel');
                var toggle = row.querySelector('.wham-users-toggle');
                if (panel && toggle) {
                    toggle.setAttribute('data-mid', newMid);
                    panel.id = 'wham-users-' + newMid;
                    panel.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                        cb.name = 'wham_users[' + newMid + '][]';
                    });
                }
            });
        }
    }

    function closeAllPanels() {
        document.querySelectorAll('.wham-users-panel').forEach(function(p) {
            p.style.display = 'none';
        });
    }

    function updateClientCount() {
        var count = mappingBody.querySelectorAll('tr').length;
        var badge = document.querySelector('.wham-client-count');
        if (badge) {
            badge.textContent = count + ' client' + (count !== 1 ? 's' : '') + ' mapped';
        }
    }

    // ── Bind events on existing rows ────────────────────────────────
    mappingBody.querySelectorAll('.wham-mapping-row').forEach(bindRowEvents);

    // ── Add Client button ───────────────────────────────────────────
    document.getElementById('wham-add-row').addEventListener('click', function() {
        var row = createRow();
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        row.querySelector('input[name^="monday_id"]').focus();
    });

    // First-row button in empty state.
    var firstBtn = document.getElementById('wham-add-first-row');
    if (firstBtn) {
        firstBtn.addEventListener('click', function() {
            var row = createRow();
            row.querySelector('input[name^="monday_id"]').focus();
        });
    }

    // ── Close panels on outside click ───────────────────────────────
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.wham-users-cell')) closeAllPanels();
    });

    // ── Reference table + buttons ───────────────────────────────────
    document.querySelectorAll('.wham-add-ref').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var data = this.dataset;

            // Check for duplicate.
            var existing = mappingBody.querySelectorAll('input[name^="monday_id"]');
            for (var k = 0; k < existing.length; k++) {
                if (existing[k].value === data.mid) {
                    var existingRow = existing[k].closest('tr');
                    existingRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    existingRow.classList.remove('wham-row-flash');
                    void existingRow.offsetWidth;
                    existingRow.classList.add('wham-row-flash');
                    return;
                }
            }

            var row = createRow({
                mid: data.mid,
                name: data.name,
                tier: data.tier,
                url: data.url ? ('https://' + data.url.replace(/^https?:\/\//, '')) : '',
                mainwp: data.mainwp || '',
                gsc: data.gsc,
                ga4: data.ga4 || ''
            });

            // Mark reference row.
            this.innerHTML = '&#10003;';
            this.closest('tr').classList.add('wham-ref-mapped');

            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.classList.remove('wham-row-new');
            row.classList.add('wham-row-flash');
            updateClientCount();
        });
    });
});
</script>
