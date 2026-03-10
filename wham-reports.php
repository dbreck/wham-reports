<?php
/**
 * Plugin Name: WHAM Reports
 * Description: Automated monthly reporting for WHAM (Web Hosting And Maintenance) clients. Collects data from MainWP, Google Search Console, GA4, and Monday.com to generate PDF reports and a client dashboard.
 * Version: 3.2.2
 * Author: Clear ph Design
 * Text Domain: wham-reports
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WHAM_REPORTS_VERSION', '3.2.2' );
define( 'WHAM_REPORTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WHAM_REPORTS_URL', plugin_dir_url( __FILE__ ) );
define( 'WHAM_REPORTS_MONDAY_BOARD_ID', '9141194124' );

/**
 * Autoload plugin classes.
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'WHAM_Reports\\';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }
    $relative = substr( $class, strlen( $prefix ) );
    $file     = WHAM_REPORTS_PATH . 'includes/class-' . strtolower( str_replace( [ '\\', '_' ], [ '/', '-' ], $relative ) ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
});

/**
 * Main plugin class.
 */
final class WHAM_Reports {

    private static ?self $instance = null;

    /**
     * Default tier capability matrix. Keys are capability names;
     * values are arrays of [basic, professional, premium] booleans.
     */
    private const TIER_CAPABILITY_DEFAULTS = [
        'maintenance'        => [ 'basic' => true,  'professional' => true,  'premium' => true ],
        'maintenance_detail' => [ 'basic' => false, 'professional' => true,  'premium' => true ],
        'gsc_aggregate'      => [ 'basic' => true,  'professional' => true,  'premium' => true ],
        'gsc_comparison'     => [ 'basic' => true,  'professional' => true,  'premium' => true ],
        'gsc_top_queries'    => [ 'basic' => false, 'professional' => true,  'premium' => true ],
        'gsc_top_pages'      => [ 'basic' => false, 'professional' => true,  'premium' => true ],
        'gsc_trend'          => [ 'basic' => false, 'professional' => true,  'premium' => true ],
        'ga4_core'           => [ 'basic' => false, 'professional' => true,  'premium' => true ],
        'ga4_comparison'     => [ 'basic' => false, 'professional' => true,  'premium' => true ],
        'ga4_sources'        => [ 'basic' => false, 'professional' => true,  'premium' => true ],
        'ga4_landing_pages'  => [ 'basic' => false, 'professional' => true,  'premium' => true ],
        'ga4_trend'          => [ 'basic' => false, 'professional' => true,  'premium' => true ],
    ];

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check if a tier has a specific capability.
     *
     * @param string $tier       Client tier (basic, professional, premium).
     * @param string $capability Capability key (e.g., 'ga4_core', 'gsc_top_queries').
     * @return bool
     */
    public static function tier_has( string $tier, string $capability ): bool {
        static $config = null;

        if ( null === $config ) {
            $stored = json_decode( get_option( 'wham_tier_capabilities', '' ), true );
            $config = is_array( $stored ) ? $stored : [];
        }

        // Check stored config first, then fall back to defaults.
        if ( isset( $config[ $capability ][ $tier ] ) ) {
            return (bool) $config[ $capability ][ $tier ];
        }

        return self::TIER_CAPABILITY_DEFAULTS[ $capability ][ $tier ] ?? false;
    }

    private function __construct() {
        $this->register_hooks();
    }

    private function register_hooks(): void {
        // Core setup.
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'init', [ $this, 'register_client_role' ] );
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );

        // GitHub updater.
        new \WHAM_Reports\GitHub_Updater();

        // Plugin row meta — "Check for updates" AJAX link.
        add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'updater_inline_script' ] );

        // Dashboard shortcode.
        add_shortcode( 'wham_dashboard', [ $this, 'render_dashboard_shortcode' ] );

        // REST API for dashboard data.
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // Cron.
        add_filter( 'cron_schedules', [ $this, 'add_monthly_schedule' ] );
        add_action( 'wham_generate_reports', [ $this, 'run_report_generation' ] );

        // Reschedule cron when auto-send settings change.
        foreach ( [ 'update_option_wham_autosend_day', 'update_option_wham_autosend_hour', 'update_option_wham_autosend_enabled',
                     'add_option_wham_autosend_day', 'add_option_wham_autosend_hour', 'add_option_wham_autosend_enabled' ] as $hook ) {
            add_action( $hook, [ $this, 'reschedule_cron' ], 10, 0 );
        }

        // Manual trigger via admin.
        add_action( 'admin_post_wham_generate_reports', [ $this, 'handle_manual_generate' ] );
        add_action( 'admin_post_wham_generate_single', [ $this, 'handle_single_generate' ] );
        add_action( 'admin_post_wham_approve_report', [ $this, 'handle_approve_report' ] );

        // Test email AJAX.
        add_action( 'wp_ajax_wham_test_email', [ $this, 'ajax_test_email' ] );

        // Custom columns on All Reports list.
        add_filter( 'manage_wham_report_posts_columns', [ $this, 'report_columns' ] );
        add_action( 'manage_wham_report_posts_custom_column', [ $this, 'report_column_content' ], 10, 2 );
        add_filter( 'manage_edit-wham_report_sortable_columns', [ $this, 'report_sortable_columns' ] );
        add_action( 'pre_get_posts', [ $this, 'report_column_orderby' ] );
    }

    /* ------------------------------------------------------------------
     * Activation / Deactivation
     * ----------------------------------------------------------------*/

    public function activate(): void {
        $this->register_post_type();
        $this->register_client_role();
        flush_rewrite_rules();

        // Schedule monthly cron if auto-send is enabled.
        if ( ! wp_next_scheduled( 'wham_generate_reports' ) && get_option( 'wham_autosend_enabled', '0' ) ) {
            $day  = (int) get_option( 'wham_autosend_day', 1 );
            $hour = (int) get_option( 'wham_autosend_hour', 6 );
            $next = $this->calculate_next_run( $day, $hour );
            wp_schedule_event( $next, 'monthly', 'wham_generate_reports' );
        }
    }

    public function deactivate(): void {
        wp_clear_scheduled_hook( 'wham_generate_reports' );
        flush_rewrite_rules();
    }

    /**
     * Register the monthly cron schedule (WordPress doesn't include one).
     */
    public function add_monthly_schedule( array $schedules ): array {
        $schedules['monthly'] = [
            'interval' => 30 * DAY_IN_SECONDS,
            'display'  => 'Once Monthly',
        ];
        return $schedules;
    }

    /**
     * Reschedule the cron event based on current auto-send settings.
     * Uses a static flag to avoid running multiple times per request.
     */
    public function reschedule_cron(): void {
        static $done = false;
        if ( $done ) {
            return;
        }
        $done = true;

        wp_clear_scheduled_hook( 'wham_generate_reports' );

        $enabled = get_option( 'wham_autosend_enabled', '0' );
        if ( ! $enabled ) {
            return;
        }

        $day  = (int) get_option( 'wham_autosend_day', 1 );
        $hour = (int) get_option( 'wham_autosend_hour', 6 );
        $next = $this->calculate_next_run( $day, $hour );

        wp_schedule_event( $next, 'monthly', 'wham_generate_reports' );
    }

    /**
     * Calculate the next cron run timestamp for a given day and hour.
     */
    private function calculate_next_run( int $day, int $hour ): int {
        $now       = current_time( 'timestamp' );
        $this_month = mktime( $hour, 0, 0, (int) date( 'n', $now ), $day, (int) date( 'Y', $now ) );

        if ( $this_month > $now ) {
            return $this_month;
        }

        // Already passed this month — schedule for next month.
        return mktime( $hour, 0, 0, (int) date( 'n', $now ) + 1, $day, (int) date( 'Y', $now ) );
    }

    /* ------------------------------------------------------------------
     * Custom Post Type: wham_report
     * ----------------------------------------------------------------*/

    public function register_post_type(): void {
        register_post_type( 'wham_report', [
            'labels' => [
                'name'          => 'WHAM Reports',
                'singular_name' => 'WHAM Report',
                'menu_name'     => 'Reports',
                'all_items'     => 'All Reports',
                'add_new_item'  => 'Add New Report',
                'edit_item'     => 'Edit Report',
                'view_item'     => 'View Report',
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false, // We show under our own menu.
            'show_in_rest' => true,
            'supports'     => [ 'title', 'custom-fields' ],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    /* ------------------------------------------------------------------
     * Client Role
     * ----------------------------------------------------------------*/

    public function register_client_role(): void {
        if ( ! get_role( 'wham_client' ) ) {
            add_role( 'wham_client', 'WHAM Client', [
                'read' => true,
            ]);
        }
    }

    /* ------------------------------------------------------------------
     * Admin Menu
     * ----------------------------------------------------------------*/

    public function admin_menu(): void {
        add_menu_page(
            'WHAM Reports',
            'WHAM Reports',
            'manage_options',
            'wham-reports',
            [ $this, 'render_admin_page' ],
            'dashicons-chart-area',
            30
        );

        add_submenu_page(
            'wham-reports',
            'All Reports',
            'All Reports',
            'manage_options',
            'edit.php?post_type=wham_report'
        );

        add_submenu_page(
            'wham-reports',
            'Settings',
            'Settings',
            'manage_options',
            'wham-reports-settings',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'wham-reports',
            'Client Mapping',
            'Client Mapping',
            'manage_options',
            'wham-reports-mapping',
            [ $this, 'render_mapping_page' ]
        );

        add_submenu_page(
            'wham-reports',
            'Schedule',
            'Schedule',
            'manage_options',
            'wham-reports-schedule',
            [ $this, 'render_schedule_page' ]
        );

        add_submenu_page(
            'wham-reports',
            'Review Drafts',
            'Review Drafts',
            'manage_options',
            'wham-reports-drafts',
            [ $this, 'render_drafts_page' ]
        );
    }

    /* ------------------------------------------------------------------
     * Settings Registration
     * ----------------------------------------------------------------*/

    public function register_settings(): void {
        // API Keys group.
        register_setting( 'wham_reports_settings', 'wham_monday_api_token' );
        register_setting( 'wham_reports_settings', 'wham_gsc_credentials_json' );
        register_setting( 'wham_reports_settings', 'wham_ga4_credentials_json' );
        register_setting( 'wham_reports_settings', 'wham_mainwp_app_password' );
        register_setting( 'wham_reports_settings', 'wham_sender_email' );
        register_setting( 'wham_reports_settings', 'wham_sender_name' );

        // Tier configuration (legacy — kept for backward compat).
        register_setting( 'wham_reports_settings', 'wham_tier_config', [
            'sanitize_callback' => [ $this, 'sanitize_tier_config' ],
        ] );

        // Granular tier capabilities (v3.2.1+).
        register_setting( 'wham_reports_settings', 'wham_tier_capabilities', [
            'sanitize_callback' => [ $this, 'sanitize_tier_capabilities' ],
        ] );

        // PDF settings.
        register_setting( 'wham_reports_settings', 'wham_pdf_template' );
        register_setting( 'wham_reports_settings', 'wham_company_name' );
        register_setting( 'wham_reports_settings', 'wham_require_review' );

        // GitHub updater token.
        register_setting( 'wham_reports_settings', 'wham_github_token' );

        // Auto-send schedule (own settings group — saved from Schedule page).
        register_setting( 'wham_schedule_settings', 'wham_autosend_enabled', [
            'sanitize_callback' => function ( $val ) { return $val ? '1' : '0'; },
        ] );
        register_setting( 'wham_schedule_settings', 'wham_autosend_day', [
            'sanitize_callback' => function ( $val ) { return max( 1, min( 28, absint( $val ) ) ); },
        ] );
        register_setting( 'wham_schedule_settings', 'wham_autosend_hour', [
            'sanitize_callback' => function ( $val ) { return max( 0, min( 23, absint( $val ) ) ); },
        ] );
        register_setting( 'wham_schedule_settings', 'wham_autosend_email', [
            'sanitize_callback' => function ( $val ) { return $val ? '1' : '0'; },
        ] );
        register_setting( 'wham_schedule_settings', 'wham_autosend_excludes', [
            'sanitize_callback' => [ $this, 'sanitize_autosend_excludes' ],
        ] );
    }

    /* ------------------------------------------------------------------
     * Admin Assets
     * ----------------------------------------------------------------*/

    /**
     * Add "Check for updates" link to the plugin row on the Plugins page.
     */
    public function plugin_row_meta( array $meta, string $plugin_file ): array {
        if ( plugin_basename( __FILE__ ) === $plugin_file ) {
            $meta[] = '<a href="#" id="wham-check-updates" onclick="whamCheckUpdates(event)">Check for updates</a>';
        }
        return $meta;
    }

    /**
     * Enqueue inline JS for the AJAX "Check for updates" link on plugins page.
     */
    public function updater_inline_script( string $hook ): void {
        if ( 'plugins.php' !== $hook ) {
            return;
        }

        $nonce = wp_create_nonce( 'wham_check_updates' );
        $ajax_url = admin_url( 'admin-ajax.php' );
        $plugin_file = 'wham-reports/wham-reports.php';

        wp_add_inline_script( 'jquery-core', "
            function whamCheckUpdates(e) {
                e.preventDefault();
                var link = document.getElementById('wham-check-updates');
                var origText = link.textContent;
                link.textContent = 'Checking…';
                link.style.pointerEvents = 'none';

                fetch('{$ajax_url}?action=wham_check_updates&nonce={$nonce}')
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success && res.data.has_update) {
                            link.textContent = origText;
                            link.style.pointerEvents = '';
                            whamShowUpdateRow(res.data);
                        } else if (res.success) {
                            link.textContent = 'Up to date (v' + res.data.current_version + ')';
                            setTimeout(function() {
                                link.textContent = origText;
                                link.style.pointerEvents = '';
                            }, 4000);
                        } else {
                            link.textContent = 'Error: ' + (res.data || 'Unknown');
                            link.style.pointerEvents = '';
                        }
                    })
                    .catch(function() {
                        link.textContent = 'Network error';
                        link.style.pointerEvents = '';
                    });
            }

            function whamShowUpdateRow(data) {
                var pluginRow = document.querySelector('tr[data-plugin=\"{$plugin_file}\"]');
                if (!pluginRow) return;

                // Remove any existing update row.
                var existing = document.getElementById('wham-update-row');
                if (existing) existing.remove();

                var colspan = pluginRow.querySelectorAll('td, th').length;
                var tr = document.createElement('tr');
                tr.id = 'wham-update-row';
                tr.className = 'plugin-update-tr active';
                tr.innerHTML = '<td colspan=\"' + colspan + '\" class=\"plugin-update colspanchange\">' +
                    '<div class=\"update-message notice inline notice-warning notice-alt\"><p>' +
                    'There is a new version of <strong>WHAM Reports</strong> available. ' +
                    '<a href=\"' + data.details_url + '\" target=\"_blank\">View v' + data.latest_version + ' details</a> or ' +
                    '<a href=\"' + data.update_url + '\" class=\"update-link\">update now</a>.' +
                    '</p></div></td>';

                pluginRow.classList.add('update');
                pluginRow.after(tr);
            }
        " );
    }

    public function admin_assets( string $hook ): void {
        // Load on WHAM admin pages and on the All Reports list (edit.php for wham_report).
        $is_wham_page   = strpos( $hook, 'wham-reports' ) !== false;
        $is_report_list = 'edit.php' === $hook && ( $_GET['post_type'] ?? '' ) === 'wham_report';

        if ( ! $is_wham_page && ! $is_report_list ) {
            return;
        }
        wp_enqueue_style( 'wham-admin', WHAM_REPORTS_URL . 'assets/css/admin.css', [], WHAM_REPORTS_VERSION );
    }

    /* ------------------------------------------------------------------
     * Admin Pages (render methods)
     * ----------------------------------------------------------------*/

    public function render_admin_page(): void {
        require_once WHAM_REPORTS_PATH . 'templates/admin/dashboard.php';
    }

    public function render_settings_page(): void {
        require_once WHAM_REPORTS_PATH . 'templates/admin/settings.php';
    }

    public function render_mapping_page(): void {
        require_once WHAM_REPORTS_PATH . 'templates/admin/mapping.php';
    }

    public function render_schedule_page(): void {
        require_once WHAM_REPORTS_PATH . 'templates/admin/schedule.php';
    }

    public function render_drafts_page(): void {
        require_once WHAM_REPORTS_PATH . 'templates/admin/report-draft.php';
    }

    /* ------------------------------------------------------------------
     * Dashboard Shortcode: [wham_dashboard]
     * ----------------------------------------------------------------*/

    public function render_dashboard_shortcode( $atts ): string {
        require_once WHAM_REPORTS_PATH . 'includes/class-report-renderer.php';

        $renderer = new \WHAM_Reports\Report_Renderer();
        return $renderer->render_dashboard();
    }

    /* ------------------------------------------------------------------
     * REST API Routes
     * ----------------------------------------------------------------*/

    public function register_rest_routes(): void {
        register_rest_route( 'wham/v1', '/reports', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_reports' ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);

        register_rest_route( 'wham/v1', '/reports/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_report' ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);
    }

    public function rest_get_reports( \WP_REST_Request $request ): \WP_REST_Response {
        $user      = wp_get_current_user();
        $client_id = get_user_meta( $user->ID, '_wham_monday_client_id', true );

        $args = [
            'post_type'      => 'wham_report',
            'posts_per_page' => 12,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        // Non-admin users only see their own reports.
        if ( ! current_user_can( 'manage_options' ) && $client_id ) {
            $args['meta_query'] = [
                [ 'key' => '_wham_client_id', 'value' => $client_id ],
            ];
        }

        $query   = new \WP_Query( $args );
        $reports = [];

        foreach ( $query->posts as $post ) {
            $reports[] = [
                'id'          => $post->ID,
                'title'       => $post->post_title,
                'period'      => get_post_meta( $post->ID, '_wham_period', true ),
                'client_name' => get_post_meta( $post->ID, '_wham_client_name', true ),
                'tier'        => get_post_meta( $post->ID, '_wham_tier', true ),
                'pdf_url'     => get_post_meta( $post->ID, '_wham_pdf_url', true ),
                'created'     => $post->post_date,
            ];
        }

        return new \WP_REST_Response( $reports, 200 );
    }

    public function rest_get_report( \WP_REST_Request $request ): \WP_REST_Response {
        $post = get_post( (int) $request['id'] );

        if ( ! $post || $post->post_type !== 'wham_report' ) {
            return new \WP_REST_Response( [ 'error' => 'Report not found.' ], 404 );
        }

        // Check access.
        $user      = wp_get_current_user();
        $client_id = get_user_meta( $user->ID, '_wham_monday_client_id', true );
        $report_client = get_post_meta( $post->ID, '_wham_client_id', true );

        if ( ! current_user_can( 'manage_options' ) && $client_id !== $report_client ) {
            return new \WP_REST_Response( [ 'error' => 'Access denied.' ], 403 );
        }

        $data = json_decode( get_post_meta( $post->ID, '_wham_report_data', true ), true );

        return new \WP_REST_Response( [
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'period'      => get_post_meta( $post->ID, '_wham_period', true ),
            'client_name' => get_post_meta( $post->ID, '_wham_client_name', true ),
            'client_url'  => get_post_meta( $post->ID, '_wham_client_url', true ),
            'tier'        => get_post_meta( $post->ID, '_wham_tier', true ),
            'pdf_url'     => get_post_meta( $post->ID, '_wham_pdf_url', true ),
            'data'        => $data,
            'created'     => $post->post_date,
        ], 200 );
    }

    /* ------------------------------------------------------------------
     * Report Generation
     * ----------------------------------------------------------------*/

    public function run_report_generation( string $period = '', string $client_id = '' ): void {
        // When called from cron (no arguments), respect auto-send settings.
        $is_cron = empty( $period ) && empty( $client_id ) && defined( 'DOING_CRON' ) && DOING_CRON;

        if ( $is_cron && ! get_option( 'wham_autosend_enabled', '0' ) ) {
            return;
        }

        require_once WHAM_REPORTS_PATH . 'includes/class-data-collector.php';

        $collector = new \WHAM_Reports\Data_Collector();

        if ( ! empty( $client_id ) ) {
            $collector->generate_single_report( $client_id, $period );
        } else {
            $excluded  = $is_cron ? json_decode( get_option( 'wham_autosend_excludes', '[]' ), true ) ?: [] : [];
            $auto_email = $is_cron && get_option( 'wham_autosend_email', '1' );

            $collector->generate_and_send( $period, $excluded, $auto_email );
        }
    }

    public function handle_manual_generate(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'wham_generate_reports' );

        // Register shutdown handler to catch fatal errors that try/catch can't.
        $log_fn = [ $this, 'log_error' ];
        register_shutdown_function( function () use ( $log_fn ) {
            $error = error_get_last();
            if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) ) {
                call_user_func( $log_fn, sprintf(
                    "FATAL: %s in %s:%d",
                    $error['message'],
                    $error['file'],
                    $error['line']
                ) );
            }
        } );

        // Extend execution time — report generation involves many API calls.
        @set_time_limit( 600 );

        $period     = isset( $_POST['wham_report_period'] ) ? sanitize_text_field( wp_unslash( $_POST['wham_report_period'] ) ) : '';
        $client_ids = isset( $_POST['wham_client_ids'] ) && is_array( $_POST['wham_client_ids'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wham_client_ids'] ) )
            : [];

        // Buffer any stray output so wp_redirect headers aren't blocked.
        ob_start();

        $generated = 0;

        if ( ! empty( $client_ids ) ) {
            require_once WHAM_REPORTS_PATH . 'includes/class-data-collector.php';
            $collector = new \WHAM_Reports\Data_Collector();

            foreach ( $client_ids as $cid ) {
                try {
                    $result = $collector->generate_single_report( $cid, $period );
                    if ( ( $result['status'] ?? '' ) === 'success' ) {
                        $generated++;
                    }
                } catch ( \Throwable $e ) {
                    $this->log_error( "Report generation error for client {$cid}: " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
                }
                usleep( 500000 ); // 0.5s pause for API rate limits.
            }
        }

        ob_end_clean();

        $redirect = admin_url( 'admin.php?page=wham-reports&generated=' . $generated );
        wp_redirect( $redirect );
        exit;
    }

    public function handle_single_generate(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'wham_generate_single' );

        $client_id = isset( $_GET['client_id'] ) ? sanitize_text_field( wp_unslash( $_GET['client_id'] ) ) : '';
        $period    = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '';

        if ( empty( $client_id ) ) {
            wp_die( 'Missing client ID.' );
        }

        require_once WHAM_REPORTS_PATH . 'includes/class-data-collector.php';

        $collector = new \WHAM_Reports\Data_Collector();

        ob_start();
        try {
            $collector->generate_single_report( $client_id, $period );
        } catch ( \Throwable $e ) {
            $this->log_error( 'Single report generation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() );
        }
        ob_end_clean();

        wp_redirect( admin_url( 'admin.php?page=wham-reports&generated=single' ) );
        exit;
    }

    public function handle_approve_report(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'wham_approve_report' );

        $post_id    = absint( $_POST['report_id'] ?? 0 );
        $send_email = ! empty( $_POST['send_email'] ) && $_POST['send_email'] === '1';

        if ( ! $post_id ) {
            wp_die( 'Missing report ID.' );
        }

        // Build overrides from form.
        $overrides = [
            'executive_summary' => sanitize_textarea_field( wp_unslash( $_POST['executive_summary'] ?? '' ) ),
            'wins'              => array_values( array_filter( array_map( 'sanitize_text_field', wp_unslash( $_POST['wins'] ?? [] ) ) ) ),
            'watch_items'       => array_values( array_filter( array_map( 'sanitize_text_field', wp_unslash( $_POST['watch_items'] ?? [] ) ) ) ),
            'recommendations'   => [],
        ];

        foreach ( wp_unslash( $_POST['rec_title'] ?? [] ) as $i => $title ) {
            $title = sanitize_text_field( $title );
            if ( empty( $title ) ) {
                continue;
            }
            $overrides['recommendations'][] = [
                'title'     => $title,
                'rationale' => sanitize_textarea_field( $_POST['rec_rationale'][ $i ] ?? '' ),
                'impact'    => sanitize_text_field( $_POST['rec_impact'][ $i ] ?? '' ),
            ];
        }

        update_post_meta( $post_id, '_wham_report_overrides', wp_json_encode( $overrides ) );

        require_once WHAM_REPORTS_PATH . 'includes/class-data-collector.php';
        $collector = new \WHAM_Reports\Data_Collector();
        $collector->finalize_report( $post_id, $send_email );

        wp_redirect( admin_url( 'admin.php?page=wham-reports-drafts&approved=1' ) );
        exit;
    }

    /**
     * AJAX handler: send a test email for a specific report to a custom address.
     */
    public function ajax_test_email(): void {
        check_ajax_referer( 'wham_test_email', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $report_id = absint( $_POST['report_id'] ?? 0 );
        $email     = sanitize_email( $_POST['email'] ?? '' );

        if ( ! $report_id || ! $email ) {
            wp_send_json_error( 'Please select a report and enter a valid email address.' );
        }

        $post = get_post( $report_id );
        if ( ! $post || 'wham_report' !== $post->post_type ) {
            wp_send_json_error( 'Invalid report.' );
        }

        // Build template variables the same way send_report_email() does.
        $client_name  = get_post_meta( $report_id, '_wham_client_name', true );
        $period       = get_post_meta( $report_id, '_wham_period', true );
        $period_label = date( 'F Y', strtotime( $period . '-01' ) );
        $pdf_url      = get_post_meta( $report_id, '_wham_pdf_url', true );
        $tier         = get_post_meta( $report_id, '_wham_tier', true );

        $report_data = json_decode( get_post_meta( $report_id, '_wham_report_data', true ), true );

        // Convert chart file paths to public URLs.
        $chart_urls = [];
        if ( ! empty( $report_data['charts'] ) ) {
            require_once WHAM_REPORTS_PATH . 'includes/class-chart-generator.php';
            foreach ( $report_data['charts'] as $key => $path ) {
                if ( ! empty( $path ) && file_exists( $path ) ) {
                    $chart_urls[ $key ] = \WHAM_Reports\Chart_Generator::get_chart_url( $path );
                }
            }
        }

        // Render email body.
        ob_start();
        include WHAM_REPORTS_PATH . 'templates/email/report-email.php';
        $body = ob_get_clean();

        $sender_name  = get_option( 'wham_sender_name', 'WHAM Reports' );
        $sender_email = get_option( 'wham_sender_email', get_option( 'admin_email' ) );
        $subject      = "[TEST] WHAM Report — {$client_name} — {$period_label}";

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$sender_name} <{$sender_email}>",
        ];

        // Attach PDF if available.
        $attachments = [];
        if ( $pdf_url ) {
            $upload_dir = wp_get_upload_dir();
            $pdf_path   = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $pdf_url );
            if ( file_exists( $pdf_path ) ) {
                $attachments[] = $pdf_path;
            }
        }

        $sent = wp_mail( $email, $subject, $body, $headers, $attachments );

        if ( $sent ) {
            wp_send_json_success( "Test email sent to {$email}." );
        } else {
            wp_send_json_error( 'wp_mail() failed. Check your mail configuration.' );
        }
    }

    /**
     * Log an error to a plugin-local file (works even with WP_DEBUG off).
     */
    public function log_error( string $message ): void {
        $log_file = WHAM_REPORTS_PATH . 'error.log';
        $entry    = '[' . date( 'Y-m-d H:i:s' ) . '] ' . $message . "\n";
        file_put_contents( $log_file, $entry, FILE_APPEND | LOCK_EX );
    }

    /* ------------------------------------------------------------------
     * Helper: Get client map from settings.
     * ----------------------------------------------------------------*/

    public static function get_client_map(): array {
        $json = get_option( 'wham_client_map', '{}' );
        return json_decode( $json, true ) ?: [];
    }

    /**
     * Sanitize tier config — converts checkbox array to JSON for storage.
     */
    public function sanitize_tier_config( $input ): string {
        if ( ! is_array( $input ) ) {
            return '{}';
        }

        $valid_tiers    = [ 'basic', 'professional', 'premium' ];
        $valid_sections = [ 'maintenance', 'maintenance_detail', 'search', 'search_detail', 'analytics', 'dev_hours' ];
        $sanitized      = [];

        foreach ( $valid_tiers as $tier ) {
            $sanitized[ $tier ] = [];
            foreach ( $valid_sections as $section ) {
                $sanitized[ $tier ][ $section ] = ! empty( $input[ $tier ][ $section ] );
            }
        }

        return wp_json_encode( $sanitized );
    }

    /**
     * Sanitize auto-send excludes.
     *
     * The form sends wham_autosend_include[] with checked (included) clients.
     * We invert this to store the excluded IDs.
     */
    public function sanitize_autosend_excludes( $input ): string {
        // This is called with the raw form value of wham_autosend_excludes,
        // but the actual data comes from wham_autosend_include checkboxes.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by settings API
        if ( ! isset( $_POST['wham_autosend_has_excludes'] ) ) {
            // No exclusion UI was rendered (no clients mapped), keep current.
            return get_option( 'wham_autosend_excludes', '[]' );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $included   = isset( $_POST['wham_autosend_include'] ) && is_array( $_POST['wham_autosend_include'] )
            ? array_keys( $_POST['wham_autosend_include'] )
            : [];
        $client_map = json_decode( get_option( 'wham_client_map', '{}' ), true ) ?: [];
        $excluded   = [];

        foreach ( array_keys( $client_map ) as $mid ) {
            if ( ! in_array( $mid, $included, false ) ) {
                $excluded[] = sanitize_text_field( $mid );
            }
        }

        return wp_json_encode( array_values( $excluded ) );
    }

    /* ------------------------------------------------------------------
     * Report List Columns (edit.php?post_type=wham_report)
     * ----------------------------------------------------------------*/

    public function report_columns( array $columns ): array {
        $new = [];
        $new['cb']        = $columns['cb'];
        $new['title']     = 'Report';
        $new['client']    = 'Client';
        $new['period']    = 'Period';
        $new['tier']      = 'Tier';
        $new['pdf']       = 'PDF';
        $new['actions']   = 'Actions';
        $new['date']      = 'Generated';
        return $new;
    }

    public function report_column_content( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'client':
                echo esc_html( get_post_meta( $post_id, '_wham_client_name', true ) ?: '—' );
                break;

            case 'period':
                $period = get_post_meta( $post_id, '_wham_period', true );
                echo $period ? esc_html( date( 'F Y', strtotime( $period . '-01' ) ) ) : '—';
                break;

            case 'tier':
                $tier = get_post_meta( $post_id, '_wham_tier', true );
                if ( $tier ) {
                    $colors = [
                        'basic'        => '#64748b',
                        'professional' => '#2563eb',
                        'premium'      => '#7c3aed',
                    ];
                    $bg = $colors[ $tier ] ?? '#64748b';
                    printf(
                        '<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;color:#fff;background:%s;">%s</span>',
                        esc_attr( $bg ),
                        esc_html( ucfirst( $tier ) )
                    );
                } else {
                    echo '—';
                }
                break;

            case 'pdf':
                $pdf_url = get_post_meta( $post_id, '_wham_pdf_url', true );
                if ( ! $pdf_url ) {
                    $pdf_url = get_post_meta( $post_id, '_wham_pdf_url_editorial', true );
                }
                if ( ! $pdf_url ) {
                    $pdf_url = get_post_meta( $post_id, '_wham_pdf_url_swiss', true );
                }
                echo $pdf_url
                    ? '<a href="' . esc_url( $pdf_url ) . '" target="_blank">Download</a>'
                    : '<span style="color:#94a3b8;">—</span>';
                break;

            case 'actions':
                $report_id = $post_id;
                $dashboard_url = home_url( '/client-dashboard/?report=' . $report_id );
                echo '<a href="' . esc_url( $dashboard_url ) . '" target="_blank" style="margin-right:8px;">View</a>';

                $test_email_url = admin_url( 'admin.php?page=wham-reports' );
                echo '<a href="' . esc_url( $test_email_url ) . '">Email</a>';
                break;
        }
    }

    public function report_sortable_columns( array $columns ): array {
        $columns['client'] = 'client';
        $columns['period'] = 'period';
        $columns['tier']   = 'tier';
        return $columns;
    }

    public function report_column_orderby( \WP_Query $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'wham_report' ) {
            return;
        }

        $orderby = $query->get( 'orderby' );
        $meta_map = [
            'client' => '_wham_client_name',
            'period' => '_wham_period',
            'tier'   => '_wham_tier',
        ];

        if ( isset( $meta_map[ $orderby ] ) ) {
            $query->set( 'meta_key', $meta_map[ $orderby ] );
            $query->set( 'orderby', 'meta_value' );
        }
    }

    /**
     * Sanitize the granular tier capabilities matrix.
     */
    public function sanitize_tier_capabilities( $input ): string {
        if ( ! is_array( $input ) ) {
            return '{}';
        }

        $valid_tiers        = [ 'basic', 'professional', 'premium' ];
        $valid_capabilities = array_keys( self::TIER_CAPABILITY_DEFAULTS );
        $sanitized          = [];

        foreach ( $valid_capabilities as $cap ) {
            $sanitized[ $cap ] = [];
            foreach ( $valid_tiers as $tier ) {
                $sanitized[ $cap ][ $tier ] = ! empty( $input[ $cap ][ $tier ] );
            }
        }

        return wp_json_encode( $sanitized );
    }

    /**
     * Get tier configuration (which sections are enabled per tier).
     */
    public static function get_tier_config( string $tier = '' ): array {
        $defaults = [
            'basic' => [
                'maintenance' => true, 'maintenance_detail' => false,
                'search' => true, 'search_detail' => false,
                'analytics' => false, 'dev_hours' => true,
            ],
            'professional' => [
                'maintenance' => true, 'maintenance_detail' => true,
                'search' => true, 'search_detail' => true,
                'analytics' => true, 'dev_hours' => true,
            ],
            'premium' => [
                'maintenance' => true, 'maintenance_detail' => true,
                'search' => true, 'search_detail' => true,
                'analytics' => true, 'dev_hours' => true,
            ],
        ];

        $config = json_decode( get_option( 'wham_tier_config', '' ), true ) ?: $defaults;

        if ( $tier ) {
            return $config[ $tier ] ?? $defaults['basic'];
        }

        return $config;
    }

    public static function get_tier_label( string $tier ): string {
        $labels = [
            'basic'        => 'Basic',
            'professional' => 'Professional',
            'premium'      => 'Premium',
        ];
        return $labels[ $tier ] ?? ucfirst( $tier );
    }
}

// Boot the plugin.
WHAM_Reports::instance();

/**
 * Global convenience function for tier capability checks.
 *
 * @param string $tier       Client tier (basic, professional, premium).
 * @param string $capability Capability key (e.g., 'ga4_core', 'gsc_top_queries').
 * @return bool
 */
function wham_tier_has( string $tier, string $capability ): bool {
    return WHAM_Reports::tier_has( $tier, $capability );
}
