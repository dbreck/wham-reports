<?php
/**
 * Chart Generator service.
 *
 * Generates chart images via QuickChart.io and caches them locally
 * for embedding in PDF reports and the client dashboard.
 *
 * @package WHAM_Reports
 */

namespace WHAM_Reports;

defined( 'ABSPATH' ) || exit;

/**
 * Chart_Generator class.
 */
class Chart_Generator {

	/**
	 * Default color palette for charts.
	 *
	 * @var string[]
	 */
	private static array $colors = [
		'#3b82f6',
		'#16a34a',
		'#d97706',
		'#dc2626',
		'#7c3aed',
		'#8899aa',
	];

	/**
	 * Generate a line chart.
	 *
	 * @param string[] $labels  X-axis labels.
	 * @param array[]  $datasets Array of dataset arrays, each with 'label', 'data', and optional 'borderColor'.
	 * @param array    $options  Optional. Width, height, and other overrides.
	 * @return string Local file path to the chart PNG, or empty string on failure.
	 */
	public static function line_chart( array $labels, array $datasets, array $options = [] ): string {
		$chart_datasets = [];
		foreach ( $datasets as $i => $ds ) {
			$color = $ds['borderColor'] ?? self::$colors[ $i % count( self::$colors ) ];
			$chart_datasets[] = [
				'label'       => $ds['label'] ?? '',
				'data'        => $ds['data'] ?? [],
				'borderColor' => $color,
				'fill'        => false,
				'borderWidth' => 2,
				'pointRadius' => 3,
				'tension'     => 0.3,
			];
		}

		$config = [
			'type' => 'line',
			'data' => [
				'labels'   => $labels,
				'datasets' => $chart_datasets,
			],
			'options' => self::base_options(),
		];

		$url = self::build_chart_url( $config, $options );
		return self::download_chart( $url );
	}

	/**
	 * Generate a bar chart.
	 *
	 * @param string[] $labels X-axis labels.
	 * @param array    $data   Flat array of numeric values.
	 * @param array    $options Optional. Width, height, colors, and other overrides.
	 * @return string Local file path to the chart PNG, or empty string on failure.
	 */
	public static function bar_chart( array $labels, array $data, array $options = [] ): string {
		$bar_colors = array_slice(
			array_merge( self::$colors, self::$colors, self::$colors ),
			0,
			count( $data )
		);

		$config = [
			'type' => 'bar',
			'data' => [
				'labels'   => $labels,
				'datasets' => [
					[
						'data'            => $data,
						'backgroundColor' => $bar_colors,
					],
				],
			],
			'options' => self::base_options(),
		];

		$url = self::build_chart_url( $config, $options );
		return self::download_chart( $url );
	}

	/**
	 * Generate a doughnut chart.
	 *
	 * @param string[] $labels Segment labels.
	 * @param array    $data   Flat array of numeric values.
	 * @param array    $options Optional. Width, height, colors, and other overrides.
	 * @return string Local file path to the chart PNG, or empty string on failure.
	 */
	public static function doughnut_chart( array $labels, array $data, array $options = [] ): string {
		$chart_colors = $options['colors'] ?? array_slice(
			array_merge( self::$colors, self::$colors, self::$colors ),
			0,
			count( $data )
		);

		$config = [
			'type'    => 'doughnut',
			'data'    => [
				'labels'   => $labels,
				'datasets' => [
					[
						'data'            => $data,
						'backgroundColor' => $chart_colors,
					],
				],
			],
			'options' => [
				'cutout'  => '60%',
				'plugins' => [
					'legend' => [
						'position' => 'bottom',
						'labels'   => [
							'font' => [
								'family' => 'Helvetica Neue, sans-serif',
							],
						],
					],
				],
			],
		];

		$url = self::build_chart_url( $config, $options );
		return self::download_chart( $url );
	}

	/**
	 * Convert a local chart file path to a WordPress URL.
	 *
	 * @param string $file_path Absolute local file path.
	 * @return string Public URL for the chart image.
	 */
	public static function get_chart_url( string $file_path ): string {
		$upload_dir = wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] );
		$base_url   = trailingslashit( $upload_dir['baseurl'] );

		if ( strpos( $file_path, $base_dir ) === 0 ) {
			return $base_url . substr( $file_path, strlen( $base_dir ) );
		}

		return '';
	}

	/**
	 * Build the QuickChart.io URL for a chart configuration.
	 *
	 * @param array $config  Chart.js configuration object.
	 * @param array $options Optional. width, height overrides.
	 * @return string The full QuickChart URL.
	 */
	private static function build_chart_url( array $config, array $options = [] ): string {
		$width  = $options['width'] ?? 600;
		$height = $options['height'] ?? 300;

		$json = wp_json_encode( $config );

		return 'https://quickchart.io/chart?c=' . rawurlencode( $json )
			. '&w=' . absint( $width )
			. '&h=' . absint( $height )
			. '&bkg=white&f=png';
	}

	/**
	 * Download a chart image and cache it locally.
	 *
	 * @param string $url QuickChart URL.
	 * @return string Local file path to the cached PNG, or empty string on failure.
	 */
	private static function download_chart( string $url ): string {
		$chart_dir = self::ensure_chart_dir();
		if ( empty( $chart_dir ) ) {
			return '';
		}

		$hash     = md5( $url );
		$filename = 'chart-' . $hash . '.png';
		$filepath = trailingslashit( $chart_dir ) . $filename;

		// Return cached file if it exists.
		if ( file_exists( $filepath ) ) {
			return $filepath;
		}

		$response = wp_remote_get( $url, [
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( '[WHAM Charts] Download failed: ' . $response->get_error_message() );
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			error_log( '[WHAM Charts] QuickChart returned HTTP ' . $code );
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			error_log( '[WHAM Charts] Empty response body from QuickChart' );
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $filepath, $body );

		return $filepath;
	}

	/**
	 * Ensure the charts upload directory exists.
	 *
	 * @return string Absolute path to the charts directory, or empty string on failure.
	 */
	private static function ensure_chart_dir(): string {
		$upload_dir = wp_upload_dir();
		$chart_dir  = trailingslashit( $upload_dir['basedir'] ) . 'wham-reports/charts';

		if ( ! is_dir( $chart_dir ) ) {
			wp_mkdir_p( $chart_dir );
		}

		return is_dir( $chart_dir ) ? $chart_dir : '';
	}

	/**
	 * Base Chart.js options shared across line and bar charts.
	 *
	 * @return array
	 */
	private static function base_options(): array {
		return [
			'plugins' => [
				'legend' => [
					'position' => 'bottom',
					'labels'   => [
						'font' => [
							'family' => 'Helvetica Neue, sans-serif',
						],
					],
				],
			],
			'scales'  => [
				'x' => [
					'grid' => [
						'color' => '#e2e8f0',
					],
					'ticks' => [
						'font' => [
							'family' => 'Helvetica Neue, sans-serif',
						],
					],
				],
				'y' => [
					'grid' => [
						'color' => '#e2e8f0',
					],
					'ticks' => [
						'font' => [
							'family' => 'Helvetica Neue, sans-serif',
						],
					],
				],
			],
		];
	}
}
