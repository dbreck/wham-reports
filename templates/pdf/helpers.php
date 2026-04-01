<?php
/**
 * Shared helper functions for all PDF report templates.
 *
 * @package WHAM_Reports
 */

defined( 'ABSPATH' ) || exit;

/**
 * Format large numbers with K/M suffixes.
 *
 * @param int|float $n The number to format.
 * @return string Formatted number string.
 */
if ( ! function_exists( 'wham_format_number' ) ) {
	function wham_format_number( $n ) {
		if ( $n >= 1000000 ) return round( $n / 1000000, 1 ) . 'M';
		if ( $n >= 1000 )    return round( $n / 1000, 1 ) . 'K';
		return number_format( $n );
	}
}

/**
 * Format duration in seconds to readable "Xm Ys" string.
 *
 * @param int|float $seconds Duration in seconds.
 * @return string Formatted duration.
 */
if ( ! function_exists( 'wham_format_duration' ) ) {
	function wham_format_duration( $seconds ) {
		$m = floor( $seconds / 60 );
		$s = (int) $seconds % 60;
		return $m . 'm ' . $s . 's';
	}
}

/**
 * Month-over-month change badge with color.
 *
 * Returns an HTML span with +/- prefix (DomPDF-safe, no Unicode arrows).
 * Green (#059669) for positive change, red (#dc2626) for negative.
 *
 * @param int|float $current  Current period value.
 * @param int|float $previous Previous period value.
 * @param string    $suffix   Optional suffix after the percentage.
 * @return string HTML span or empty string if no previous data.
 */
if ( ! function_exists( 'wham_mom_badge' ) ) {
	function wham_mom_badge( $current, $previous, $suffix = '' ) {
		if ( ! $previous ) return '';
		$change = round( ( ( $current - $previous ) / $previous ) * 100, 1 );
		$prefix = $change >= 0 ? '+' : '-';
		$color  = $change >= 0 ? '#059669' : '#dc2626';
		return '<span style="font-size:8pt;color:' . $color . ';font-weight:bold;">' . $prefix . abs( $change ) . '%' . $suffix . '</span>';
	}
}

/**
 * Embed a chart image as base64 for reliable DomPDF rendering.
 *
 * DomPDF cannot load file:// URLs reliably, so this encodes the PNG
 * directly into the HTML as a data URI.
 *
 * @param string $path      Absolute filesystem path to the chart PNG.
 * @param string $max_width CSS max-width for the image (default '520px').
 * @return string HTML div with embedded img, or empty string if file missing.
 */
if ( ! function_exists( 'wham_chart_img' ) ) {
	function wham_chart_img( $path, $max_width = '520px' ) {
		if ( empty( $path ) || ! file_exists( $path ) ) {
			return ''; // Silently skip missing charts.
		}
		$data = base64_encode( file_get_contents( $path ) );
		return '<div class="chart-wrap"><img src="data:image/png;base64,' . $data . '" style="width:100%;max-width:' . $max_width . ';"></div>';
	}
}

/**
 * Encode a local asset as a data URI for reliable PDF rendering.
 *
 * @param string $path Absolute filesystem path.
 * @return string Data URI or empty string if missing/unsupported.
 */
if ( ! function_exists( 'wham_pdf_asset_data_uri' ) ) {
	function wham_pdf_asset_data_uri( $path ) {
		if ( empty( $path ) || ! file_exists( $path ) ) {
			return '';
		}

		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$map = [
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif'  => 'image/gif',
			'svg'  => 'image/svg+xml',
			'webp' => 'image/webp',
		];

		if ( empty( $map[ $ext ] ) ) {
			return '';
		}

		$data = file_get_contents( $path );
		if ( false === $data ) {
			return '';
		}

		return 'data:' . $map[ $ext ] . ';base64,' . base64_encode( $data );
	}
}

/**
 * Render the WHAM logo from uploads as an embedded image for PDFs.
 *
 * @param string $style Inline CSS for the img tag.
 * @param string $class CSS class for the img tag.
 * @return string HTML img tag or empty string if the logo is unavailable.
 */
if ( ! function_exists( 'wham_pdf_logo_img' ) ) {
	function wham_pdf_logo_img( $style = '', $class = '' ) {
		$upload_dir = wp_get_upload_dir();
		$candidates = [
			trailingslashit( $upload_dir['basedir'] ) . '2025/07/wham-logo-dark.svg',
		];

		foreach ( $candidates as $path ) {
			$data_uri = wham_pdf_asset_data_uri( $path );
			if ( $data_uri ) {
				$class_attr = $class ? ' class="' . esc_attr( $class ) . '"' : '';
				$style_attr = $style ? ' style="' . esc_attr( $style ) . '"' : '';
				return '<img src="' . $data_uri . '" alt="WHAM"' . $class_attr . $style_attr . '>';
			}
		}

		return '';
	}
}
