<?php
/**
 * Plugin Name: WHAM Reports
 * Description: Automated monthly reporting for WHAM (Web Hosting And Maintenance) clients. Collects data from MainWP, Google Search Console, GA4, and Monday.com to generate PDF reports and a client dashboard.
 * Version: 1.0.0
 * Author: Clear ph Design
 * Text Domain: wham-reports
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WHAM_REPORTS_VERSION', '1.0.0' );
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

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
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

        // Dashboard shortcode.
        add_shortcode( 'wham_dashboard', [ $this, 'render_dashboard_shortcode' ] );

        // REST API for dashboard data.
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // Cron.
        add_action( 'wham_generate_reports', [ $this, 'run_report_generation' ] );

        // Manual trigger via admin.
        add_action( 'admin_post_wham_generate_reports', [ $this, 'handle_manual_generate' ] );
        add_action( 'admin_post_wham_generate_single', [ $this, 'handle_single_generate' ] );
    }

    /* ------------------------------------------------------------------
     * Activation / Deactivation
     * ----------------------------------------------------------------*/

    public function activate(): void {
        $this->register_post_type();
        $this->register_client_role();
        flush_rewrite_rules();

        // Schedule monthly cron on the 1st at 6:00 AM.
        if ( ! wp_next_scheduled( 'wham_generate_reports' ) ) {
            $next = strtotime( 'first day of next month 06:00:00' );
            wp_schedule_event( $next, 'monthly', 'wham_generate_reports' );
        }
    }

    public function deactivate(): void {
        wp_clear_scheduled_hook( 'wham_generate_reports' );
        flush_rewrite_rules();
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

        // Client mapping (JSON map of monday_id → mainwp_site_id, gsc_property, ga4_property_id).
        register_setting( 'wham_reports_settings', 'wham_client_map' );
    }

    /* ------------------------------------------------------------------
     * Admin Assets
     * ----------------------------------------------------------------*/

    public function admin_assets( string $hook ): void {
        if ( strpos( $hook, 'wham-reports' ) === false ) {
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

    /* ------------------------------------------------------------------
     * Dashboard Shortcode: [wham_dashboard]
     * ----------------------------------------------------------------*/

    public function render_dashboard_shortcode( $atts ): string {
        if ( ! is_user_logged_in() ) {
            return '<p>Please <a href="' . wp_login_url( get_permalink() ) . '">log in</a> to view your reports.</p>';
        }

        $user       = wp_get_current_user();
        $client_id  = get_user_meta( $user->ID, '_wham_monday_client_id', true );

        if ( empty( $client_id ) && ! current_user_can( 'manage_options' ) ) {
            return '<p>Your account is not linked to a WHAM client. Please contact support.</p>';
        }

        ob_start();
        include WHAM_REPORTS_PATH . 'templates/dashboard/client-dashboard.php';
        return ob_get_clean();
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

    public function run_report_generation(): void {
        require_once WHAM_REPORTS_PATH . 'includes/class-data-collector.php';

        $collector = new \WHAM_Reports\Data_Collector();
        $collector->generate_all_reports();
    }

    public function handle_manual_generate(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'wham_generate_reports' );

        $this->run_report_generation();

        wp_redirect( admin_url( 'admin.php?page=wham-reports&generated=1' ) );
        exit;
    }

    public function handle_single_generate(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'wham_generate_single' );

        $client_id = sanitize_text_field( $_GET['client_id'] ?? '' );
        if ( empty( $client_id ) ) {
            wp_die( 'Missing client ID.' );
        }

        require_once WHAM_REPORTS_PATH . 'includes/class-data-collector.php';

        $collector = new \WHAM_Reports\Data_Collector();
        $collector->generate_single_report( $client_id );

        wp_redirect( admin_url( 'admin.php?page=wham-reports&generated=single' ) );
        exit;
    }

    /* ------------------------------------------------------------------
     * Helper: Get client map from settings.
     * ----------------------------------------------------------------*/

    public static function get_client_map(): array {
        $json = get_option( 'wham_client_map', '{}' );
        return json_decode( $json, true ) ?: [];
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
