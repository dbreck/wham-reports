<?php
/**
 * Plugin Name: WHAM Reports
 * Description: Automated monthly reporting for WHAM (Web Hosting And Maintenance) clients. Collects data from MainWP, Google Search Console, GA4, and Monday.com to generate PDF reports and a client dashboard.
 * Version: 3.3.0
 * Author: Clear ph Design
 * Text Domain: wham-reports
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WHAM_REPORTS_VERSION', '3.3.0' );
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

    public static function default_report_period(): string {
        return \WHAM_Reports\Report_Period::previous_completed_month()->period();
    }

    public static function get_dashboard_page_id(): int {
        return absint( get_option( 'wham_dashboard_page_id', 0 ) );
    }

    public static function get_dashboard_page_url(): string {
        $page_id = self::get_dashboard_page_id();
        if ( ! $page_id ) {
            return '';
        }

        $url = get_permalink( $page_id );
        return $url ? $url : '';
    }

    public static function get_report_dashboard_url( int $report_id ): string {
        $base_url = self::get_dashboard_page_url();
        if ( ! $base_url ) {
            return '';
        }

        return add_query_arg( 'report', $report_id, $base_url );
    }

    public static function current_user_client_id( int $user_id = 0 ): string {
        $user_id = $user_id ?: get_current_user_id();
        if ( ! $user_id ) {
            return '';
        }

        return (string) get_user_meta( $user_id, '_wham_monday_client_id', true );
    }

    public static function current_user_can_access_reports( int $user_id = 0 ): bool {
        $user_id = $user_id ?: get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }

        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }

        return self::current_user_client_id( $user_id ) !== '';
    }

    public static function user_can_access_report( int $report_id, int $user_id = 0 ): bool {
        $user_id = $user_id ?: get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }

        $post = get_post( $report_id );
        if ( ! $post || 'wham_report' !== $post->post_type ) {
            return false;
        }

        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }

        if ( 'publish' !== $post->post_status ) {
            return false;
        }

        $client_id       = self::current_user_client_id( $user_id );
        $report_client_id = (string) get_post_meta( $report_id, '_wham_client_id', true );

        return $client_id !== '' && hash_equals( $report_client_id, $client_id );
    }

    public static function get_report_pdf_path( int $report_id ): ?string {
        $path = get_post_meta( $report_id, '_wham_pdf_file', true );
        if ( is_string( $path ) && $path !== '' && file_exists( $path ) ) {
            return $path;
        }

        $attachment_id = absint( get_post_meta( $report_id, '_wham_pdf_attachment_id', true ) );
        if ( $attachment_id ) {
            $attached = get_attached_file( $attachment_id );
            if ( $attached && file_exists( $attached ) ) {
                return $attached;
            }
        }

        $legacy_url = get_post_meta( $report_id, '_wham_pdf_url', true );
        if ( is_string( $legacy_url ) && $legacy_url !== '' ) {
            $upload_dir = wp_get_upload_dir();
            $base_url   = trailingslashit( $upload_dir['baseurl'] );
            $base_dir   = trailingslashit( $upload_dir['basedir'] );

            if ( strpos( $legacy_url, $base_url ) === 0 ) {
                $legacy_path = $base_dir . ltrim( substr( $legacy_url, strlen( $base_url ) ), '/' );
                if ( file_exists( $legacy_path ) ) {
                    return $legacy_path;
                }
            }
        }

        return null;
    }

    public static function get_report_pdf_filename( int $report_id ): string {
        $filename = get_post_meta( $report_id, '_wham_pdf_filename', true );
        if ( is_string( $filename ) && $filename !== '' ) {
            return $filename;
        }

        $path = self::get_report_pdf_path( $report_id );
        if ( $path ) {
            return wp_basename( $path );
        }

        return 'wham-report-' . $report_id . '.pdf';
    }

    public static function get_report_download_url( int $report_id ): string {
        if ( ! self::get_report_pdf_path( $report_id ) ) {
            return '';
        }

        return add_query_arg(
            [
                'action'    => 'wham_download_report',
                'report_id' => $report_id,
            ],
            admin_url( 'admin-post.php' )
        );
    }

    public static function get_mail_sender_name(): string {
        $name = trim( (string) get_option( 'wham_sender_name', 'WHAM Reports' ) );
        return '' !== $name ? $name : 'WHAM Reports';
    }

    public static function get_mail_sender_email(): string {
        $sender_email = trim( (string) get_option( 'wham_sender_email', '' ) );
        if ( is_email( $sender_email ) ) {
            return $sender_email;
        }

        $admin_email = trim( (string) get_option( 'admin_email', '' ) );
        if ( is_email( $admin_email ) ) {
            return $admin_email;
        }

        return '';
    }

    /**
     * Send HTML email using the site's existing mail transport.
     *
     * Returns `sent`, `error`, and whether the call fell back to the site default sender.
     */
    public static function send_html_mail( string $to, string $subject, string $body, array $attachments = [] ): array {
        $sender_name  = self::get_mail_sender_name();
        $sender_email = self::get_mail_sender_email();

        $attachments = array_values( array_filter( array_map( 'strval', $attachments ), static function( string $path ): bool {
            return '' !== $path && file_exists( $path ) && is_readable( $path );
        } ) );

        $result = self::attempt_html_mail( $to, $subject, $body, $attachments, $sender_name, $sender_email );
        if ( $result['sent'] ) {
            return $result;
        }

        $fallback = $result;
        if ( '' !== $sender_email ) {
            self::debug_log( 'MAIL', 'Retrying email without custom sender override after failure: ' . $result['error'] );

            $fallback = self::attempt_html_mail( $to, $subject, $body, $attachments, '', '' );
            $fallback['used_sender_fallback'] = true;

            if ( $fallback['sent'] ) {
                return $fallback;
            }
        }

        $mailpit = self::get_local_mailpit_config();
        if ( ! $mailpit ) {
            return $fallback;
        }

        self::debug_log(
            'MAIL',
            sprintf(
                'Retrying email via Local Mailpit SMTP on %s:%d after transport failure: %s',
                $mailpit['host'],
                $mailpit['port'],
                $fallback['error'] ?: $result['error']
            )
        );

        $smtp_result = self::attempt_html_mail( $to, $subject, $body, $attachments, $sender_name, $sender_email, $mailpit );
        $smtp_result['used_local_mailpit'] = true;
        if ( $smtp_result['sent'] ) {
            return $smtp_result;
        }

        if ( '' !== $sender_email ) {
            $smtp_fallback = self::attempt_html_mail( $to, $subject, $body, $attachments, '', '', $mailpit );
            $smtp_fallback['used_sender_fallback'] = true;
            $smtp_fallback['used_local_mailpit']   = true;

            if ( $smtp_fallback['sent'] ) {
                return $smtp_fallback;
            }

            return $smtp_fallback;
        }

        return $smtp_result;
    }

    private static function attempt_html_mail( string $to, string $subject, string $body, array $attachments, string $sender_name, string $sender_email, ?array $smtp_config = null ): array {
        $failure_message = '';

        $content_type_filter = static function( $content_type ): string {
            return 'text/html';
        };

        $failure_handler = static function( $wp_error ) use ( &$failure_message ): void {
            if ( ! ( $wp_error instanceof \WP_Error ) ) {
                return;
            }

            $failure_message = $wp_error->get_error_message();

            $data = $wp_error->get_error_data();
            if ( is_array( $data ) && ! empty( $data['phpmailer_exception_message'] ) ) {
                $failure_message = (string) $data['phpmailer_exception_message'];
            }
        };

        add_filter( 'wp_mail_content_type', $content_type_filter );
        add_action( 'wp_mail_failed', $failure_handler );

        $from_filter      = null;
        $from_name_filter = null;
        $phpmailer_filter = null;

        if ( '' !== $sender_email ) {
            $from_filter = static function( $email ) use ( $sender_email ): string {
                return $sender_email;
            };
            add_filter( 'wp_mail_from', $from_filter );
        }

        if ( '' !== $sender_name ) {
            $from_name_filter = static function( $name ) use ( $sender_name ): string {
                return $sender_name;
            };
            add_filter( 'wp_mail_from_name', $from_name_filter );
        }

        if ( is_array( $smtp_config ) ) {
            $phpmailer_filter = static function( $phpmailer ) use ( $smtp_config ): void {
                $phpmailer->isSMTP();
                $phpmailer->Host       = (string) ( $smtp_config['host'] ?? '127.0.0.1' );
                $phpmailer->Port       = (int) ( $smtp_config['port'] ?? 25 );
                $phpmailer->SMTPAuth   = false;
                $phpmailer->SMTPSecure = '';
                $phpmailer->SMTPAutoTLS = false;
            };
            add_action( 'phpmailer_init', $phpmailer_filter );
        }

        try {
            $sent = wp_mail( $to, $subject, $body, [], $attachments );
        } finally {
            remove_action( 'wp_mail_failed', $failure_handler );
            remove_filter( 'wp_mail_content_type', $content_type_filter );

            if ( null !== $phpmailer_filter ) {
                remove_action( 'phpmailer_init', $phpmailer_filter );
            }

            if ( null !== $from_filter ) {
                remove_filter( 'wp_mail_from', $from_filter );
            }
            if ( null !== $from_name_filter ) {
                remove_filter( 'wp_mail_from_name', $from_name_filter );
            }
        }

        if ( ! $sent && '' === $failure_message ) {
            $failure_message = 'wp_mail() returned false.';
        }

        return [
            'sent'                 => (bool) $sent,
            'error'                => $failure_message,
            'used_sender_fallback' => false,
            'used_local_mailpit'   => false,
        ];
    }

    private static function get_local_mailpit_config(): ?array {
        $config_path = trailingslashit( (string) getenv( 'HOME' ) ) . 'Library/Application Support/Local/sites.json';
        if ( ! file_exists( $config_path ) || ! defined( 'ABSPATH' ) ) {
            return null;
        }

        $site_root = wp_normalize_path( dirname( dirname( rtrim( ABSPATH, '/\\' ) ) ) );
        if ( '' === $site_root ) {
            return null;
        }

        $sites = json_decode( (string) file_get_contents( $config_path ), true );
        if ( ! is_array( $sites ) ) {
            return null;
        }

        foreach ( $sites as $site ) {
            $path = wp_normalize_path( (string) ( $site['path'] ?? '' ) );
            if ( '' === $path || $path !== $site_root ) {
                continue;
            }

            $port = (int) ( $site['services']['mailpit']['ports']['SMTP'][0] ?? 0 );
            if ( $port <= 0 ) {
                return null;
            }

            return [
                'host' => '127.0.0.1',
                'port' => $port,
            ];
        }

        return null;
    }

    public static function is_debug_enabled(): bool {
        return ( defined( 'WHAM_REPORTS_DEBUG' ) && WHAM_REPORTS_DEBUG )
            || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
    }

    public static function debug_log( string $channel, string $message ): void {
        if ( ! self::is_debug_enabled() ) {
            return;
        }

        error_log( sprintf( '[WHAM %s] %s', strtoupper( $channel ), $message ) );
    }

    public static function record_run_summary( array $summary ): void {
        update_option( 'wham_last_run_summary', wp_json_encode( $summary ) );
    }

    public static function get_last_run_summary(): array {
        $summary = json_decode( get_option( 'wham_last_run_summary', '{}' ), true );
        return is_array( $summary ) ? $summary : [];
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
        add_action( 'wham_generate_reports', [ $this, 'run_report_generation' ] );

        // Reschedule cron when auto-send settings change.
        foreach ( [ 'update_option_wham_autosend_day', 'update_option_wham_autosend_hour', 'update_option_wham_autosend_enabled',
                     'add_option_wham_autosend_day', 'add_option_wham_autosend_hour', 'add_option_wham_autosend_enabled' ] as $hook ) {
            add_action( $hook, [ $this, 'reschedule_cron' ], 10, 0 );
        }

        // Manual trigger via admin.
        add_action( 'admin_post_wham_generate_reports', [ $this, 'handle_manual_generate' ] );
        add_action( 'admin_post_wham_generate_single', [ $this, 'handle_single_generate' ] );
        add_action( 'admin_post_wham_save_mapping', [ $this, 'handle_save_mapping' ] );
        add_action( 'admin_post_wham_download_report', [ $this, 'handle_download_report' ] );
        add_action( 'admin_post_nopriv_wham_download_report', [ $this, 'handle_download_report_login' ] );

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

        $this->reschedule_cron();
    }

    public function deactivate(): void {
        wp_clear_scheduled_hook( 'wham_generate_reports' );
        flush_rewrite_rules();
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

        $day  = (int) get_option( 'wham_autosend_day', 3 );
        $hour = (int) get_option( 'wham_autosend_hour', 6 );
        $next = $this->calculate_next_run( $day, $hour );

        wp_schedule_single_event( $next, 'wham_generate_reports' );
    }

    /**
     * Calculate the next cron run timestamp for a given day and hour.
     */
    private function calculate_next_run( int $day, int $hour ): int {
        $timezone   = wp_timezone();
        $now        = new \DateTimeImmutable( 'now', $timezone );
        $this_month = $now->setDate(
            (int) $now->format( 'Y' ),
            (int) $now->format( 'm' ),
            $day
        )->setTime( $hour, 0, 0 );

        if ( $this_month > $now ) {
            return $this_month->getTimestamp();
        }

        $next_month = $now->modify( 'first day of next month' )->setDate(
            (int) $now->modify( 'first day of next month' )->format( 'Y' ),
            (int) $now->modify( 'first day of next month' )->format( 'm' ),
            $day
        )->setTime( $hour, 0, 0 );

        return $next_month->getTimestamp();
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
        register_setting( 'wham_reports_settings', 'wham_sender_email', [
            'sanitize_callback' => 'sanitize_email',
        ] );
        register_setting( 'wham_reports_settings', 'wham_sender_name', [
            'sanitize_callback' => 'sanitize_text_field',
        ] );
        register_setting( 'wham_reports_settings', 'wham_dashboard_page_id', [
            'sanitize_callback' => 'absint',
        ] );

        // Tier configuration (legacy — kept for backward compat).
        register_setting( 'wham_reports_settings', 'wham_tier_config', [
            'sanitize_callback' => [ $this, 'sanitize_tier_config' ],
        ] );

        // Granular tier capabilities (v3.2.1+).
        register_setting( 'wham_reports_settings', 'wham_tier_capabilities', [
            'sanitize_callback' => [ $this, 'sanitize_tier_capabilities' ],
        ] );

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

        $admin_css     = WHAM_REPORTS_PATH . 'assets/css/admin.css';
        $style_version = file_exists( $admin_css ) ? (string) filemtime( $admin_css ) : WHAM_REPORTS_VERSION;

        wp_enqueue_style( 'wham-admin', WHAM_REPORTS_URL . 'assets/css/admin.css', [], $style_version );
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
            'permission_callback' => [ $this, 'rest_can_access_reports' ],
        ]);

        register_rest_route( 'wham/v1', '/reports/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_report' ],
            'permission_callback' => [ $this, 'rest_can_access_reports' ],
        ]);
    }

    public function rest_can_access_reports(): bool {
        return self::current_user_can_access_reports();
    }

    public function rest_get_reports( \WP_REST_Request $request ): \WP_REST_Response {
        $user      = wp_get_current_user();
        $is_admin  = current_user_can( 'manage_options' );
        $client_id = self::current_user_client_id( $user->ID );

        $args = [
            'post_type'      => 'wham_report',
            'posts_per_page' => 12,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_status'    => $is_admin ? [ 'publish', 'draft' ] : 'publish',
        ];

        if ( ! $is_admin ) {
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
                'download_url'=> self::get_report_download_url( $post->ID ),
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

        if ( ! self::user_can_access_report( $post->ID ) ) {
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
            'download_url'=> self::get_report_download_url( $post->ID ),
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

        $period = $period ? \WHAM_Reports\Report_Period::sanitize( $period ) : self::default_report_period();

        if ( $is_cron ) {
            $this->reschedule_cron();
        }

        require_once WHAM_REPORTS_PATH . 'includes/class-data-collector.php';

        $collector = new \WHAM_Reports\Data_Collector();
        $results   = [];

        if ( ! empty( $client_id ) ) {
            $results[ $client_id ] = $collector->generate_single_report( $client_id, $period );
        } else {
            $excluded   = $is_cron ? json_decode( get_option( 'wham_autosend_excludes', '[]' ), true ) ?: [] : [];
            $auto_email = $is_cron && get_option( 'wham_autosend_email', '1' );

            $results = $collector->generate_and_send( $period, $excluded, $auto_email );
        }

        self::record_run_summary( $this->build_run_summary( $results, $period, $is_cron ? 'cron' : 'runtime' ) );
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

        $period     = isset( $_POST['wham_report_period'] ) ? \WHAM_Reports\Report_Period::sanitize( sanitize_text_field( wp_unslash( $_POST['wham_report_period'] ) ) ) : self::default_report_period();
        $client_ids = isset( $_POST['wham_client_ids'] ) && is_array( $_POST['wham_client_ids'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wham_client_ids'] ) )
            : [];

        // Buffer any stray output so wp_redirect headers aren't blocked.
        ob_start();

        $generated = 0;
        $results   = [];

        if ( ! empty( $client_ids ) ) {
            require_once WHAM_REPORTS_PATH . 'includes/class-data-collector.php';
            $collector = new \WHAM_Reports\Data_Collector();

            foreach ( $client_ids as $cid ) {
                try {
                    $result = $collector->generate_single_report( $cid, $period );
                    $results[ $cid ] = $result;
                    if ( ( $result['status'] ?? '' ) === 'success' ) {
                        $generated++;
                    }
                } catch ( \Throwable $e ) {
                    $message         = "Report generation error for client {$cid}: " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
                    $results[ $cid ] = [ 'status' => 'error', 'message' => $message ];
                    $this->log_error( $message );
                }
                usleep( 500000 ); // 0.5s pause for API rate limits.
            }
        }

        ob_end_clean();
        self::record_run_summary( $this->build_run_summary( $results, $period, 'manual' ) );

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
        $period    = isset( $_GET['period'] ) ? \WHAM_Reports\Report_Period::sanitize( sanitize_text_field( wp_unslash( $_GET['period'] ) ) ) : self::default_report_period();

        if ( empty( $client_id ) ) {
            wp_die( 'Missing client ID.' );
        }

        require_once WHAM_REPORTS_PATH . 'includes/class-data-collector.php';

        $collector = new \WHAM_Reports\Data_Collector();

        ob_start();
        try {
            $result = $collector->generate_single_report( $client_id, $period );
        } catch ( \Throwable $e ) {
            $message = 'Single report generation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString();
            $this->log_error( $message );
            $result = [ 'status' => 'error', 'message' => $message ];
        }
        ob_end_clean();
        self::record_run_summary( $this->build_run_summary( [ $client_id => $result ], $period, 'manual-single' ) );

        wp_redirect( admin_url( 'admin.php?page=wham-reports&generated=single' ) );
        exit;
    }

    public function handle_save_mapping(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'wham_save_mapping' );

        $map = [];
        $ids = isset( $_POST['monday_id'] ) && is_array( $_POST['monday_id'] ) ? wp_unslash( $_POST['monday_id'] ) : [];
        $allowed_tiers = [ 'basic', 'professional', 'premium' ];

        foreach ( $ids as $i => $monday_id ) {
            $monday_id = sanitize_text_field( $monday_id );
            if ( '' === $monday_id ) {
                continue;
            }

            $tier = sanitize_text_field( wp_unslash( $_POST['tier'][ $i ] ?? 'basic' ) );
            if ( ! in_array( $tier, $allowed_tiers, true ) ) {
                $tier = 'basic';
            }

            $map[ $monday_id ] = [
                'client_name'    => sanitize_text_field( wp_unslash( $_POST['client_name'][ $i ] ?? '' ) ),
                'client_url'     => esc_url_raw( wp_unslash( $_POST['client_url'][ $i ] ?? '' ) ),
                'tier'           => $tier,
                'mainwp_site_id' => sanitize_text_field( wp_unslash( $_POST['mainwp_site_id'][ $i ] ?? '' ) ),
                'gsc_property'   => sanitize_text_field( wp_unslash( $_POST['gsc_property'][ $i ] ?? '' ) ),
                'ga4_property'   => sanitize_text_field( wp_unslash( $_POST['ga4_property'][ $i ] ?? '' ) ),
                'client_email'   => sanitize_email( wp_unslash( $_POST['client_email'][ $i ] ?? '' ) ),
            ];
        }

        update_option( 'wham_client_map', wp_json_encode( $map ) );

        $previous_users = get_users( [
            'meta_key'     => '_wham_monday_client_id',
            'meta_compare' => 'EXISTS',
            'fields'       => 'ID',
        ] );

        foreach ( $previous_users as $uid ) {
            delete_user_meta( (int) $uid, '_wham_monday_client_id' );
        }

        $user_assignments = isset( $_POST['wham_users'] ) && is_array( $_POST['wham_users'] ) ? wp_unslash( $_POST['wham_users'] ) : [];
        foreach ( $user_assignments as $monday_id => $user_ids ) {
            $monday_id = sanitize_text_field( $monday_id );
            if ( ! isset( $map[ $monday_id ] ) ) {
                continue;
            }

            foreach ( (array) $user_ids as $uid ) {
                $uid = absint( $uid );
                if ( $uid ) {
                    update_user_meta( $uid, '_wham_monday_client_id', $monday_id );
                }
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=wham-reports-mapping&updated=1' ) );
        exit;
    }

    public function handle_download_report_login(): void {
        $report_id = absint( $_REQUEST['report_id'] ?? 0 );
        $redirect  = $report_id ? self::get_report_dashboard_url( $report_id ) : '';
        if ( ! $redirect ) {
            $redirect = home_url( '/' );
        }

        wp_safe_redirect( wp_login_url( $redirect ) );
        exit;
    }

    public function handle_download_report(): void {
        if ( ! is_user_logged_in() ) {
            $this->handle_download_report_login();
        }

        $report_id = absint( $_REQUEST['report_id'] ?? 0 );
        if ( ! $report_id || ! self::user_can_access_report( $report_id ) ) {
            wp_die( 'Access denied.' );
        }

        $pdf_path = self::get_report_pdf_path( $report_id );
        if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
            wp_die( 'Report PDF not found.' );
        }

        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( self::get_report_pdf_filename( $report_id ) ) . '"' );
        header( 'Content-Length: ' . filesize( $pdf_path ) );
        header( 'X-Robots-Tag: noindex, nofollow', true );

        readfile( $pdf_path );
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
        $period       = \WHAM_Reports\Report_Period::sanitize( (string) get_post_meta( $report_id, '_wham_period', true ) );
        $period_label = \WHAM_Reports\Report_Period::from_string( $period )->label();
        $pdf_url      = '';
        $tier         = get_post_meta( $report_id, '_wham_tier', true );
        $dashboard_url = self::get_report_dashboard_url( $report_id );

        $report_data = json_decode( get_post_meta( $report_id, '_wham_report_data', true ), true );
        $chart_urls  = [];

        // Render email body.
        ob_start();
        include WHAM_REPORTS_PATH . 'templates/email/report-email.php';
        $body = ob_get_clean();

        $subject      = "[TEST] WHAM Report — {$client_name} — {$period_label}";

        // Attach PDF if available.
        $attachments = [];
        $pdf_path = self::get_report_pdf_path( $report_id );
        if ( $pdf_path && file_exists( $pdf_path ) ) {
            $attachments[] = $pdf_path;
        }

        $mail_result = self::send_html_mail( $email, $subject, $body, $attachments );

        if ( ! $mail_result['sent'] && ! empty( $attachments ) ) {
            self::debug_log( 'MAIL', 'Retrying test email without PDF attachment after failure: ' . $mail_result['error'] );
            $retry_without_attachment = self::send_html_mail( $email, $subject, $body, [] );

            if ( $retry_without_attachment['sent'] ) {
                $message = "Test email sent to {$email} without the PDF attachment.";
                if ( ! empty( $retry_without_attachment['used_sender_fallback'] ) ) {
                    $message .= ' It was also sent using the site default sender.';
                }
                $message .= ' Your local mail transport rejected the attached version.';

                wp_send_json_success( $message );
            }

            $mail_result = $retry_without_attachment;
        }

        if ( $mail_result['sent'] ) {
            $message = "Test email sent to {$email}.";
            if ( ! empty( $mail_result['used_sender_fallback'] ) ) {
                $message .= ' Sent using the site default sender instead of the plugin sender setting.';
            }

            wp_send_json_success( $message );
        } else {
            self::debug_log( 'MAIL', 'Test email failed: ' . $mail_result['error'] );
            wp_send_json_error( $mail_result['error'] ?: 'wp_mail() failed.' );
        }
    }

    /**
     * Log an error through the shared debug channel when debug logging is enabled.
     */
    public function log_error( string $message ): void {
        self::debug_log( 'ERROR', $message );
    }

    private function build_run_summary( array $results, string $period, string $context ): array {
        $summary = [
            'timestamp' => current_time( 'c' ),
            'context'   => $context,
            'period'    => $period,
            'processed' => count( $results ),
            'success'   => 0,
            'skipped'   => 0,
            'excluded'  => 0,
            'error'     => 0,
            'emailed'   => 0,
            'errors'    => [],
        ];

        foreach ( $results as $key => $result ) {
            $status = (string) ( $result['status'] ?? 'error' );
            if ( isset( $summary[ $status ] ) ) {
                $summary[ $status ]++;
            } elseif ( 'success' === $status ) {
                $summary['success']++;
            } else {
                $summary['error']++;
            }

            if ( ! empty( $result['email_sent'] ) ) {
                $summary['emailed']++;
            }

            $label = $result['client_name'] ?? (string) $key;
            if ( ! empty( $result['message'] ) && 'error' === $status ) {
                $summary['errors'][] = $label . ': ' . $result['message'];
            }

            foreach ( (array) ( $result['issues'] ?? [] ) as $issue ) {
                $summary['errors'][] = $label . ': ' . $issue;
            }
        }

        $summary['errors'] = array_values( array_unique( array_filter( $summary['errors'] ) ) );

        return $summary;
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
                $download_url = self::get_report_download_url( $post_id );
                echo $download_url
                    ? '<a href="' . esc_url( $download_url ) . '">Download</a>'
                    : '<span style="color:#94a3b8;">—</span>';
                break;

            case 'actions':
                $report_id = $post_id;
                $dashboard_url = self::get_report_dashboard_url( $report_id );
                if ( $dashboard_url ) {
                    echo '<a href="' . esc_url( $dashboard_url ) . '" target="_blank" style="margin-right:8px;">View</a>';
                } else {
                    echo '<span style="color:#94a3b8;margin-right:8px;">No dashboard</span>';
                }

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
