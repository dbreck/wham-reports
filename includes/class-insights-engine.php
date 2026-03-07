<?php
namespace WHAM_Reports;

defined( 'ABSPATH' ) || exit;

/**
 * Insights Engine — Generates actionable insights, health scores,
 * and recommendations from collected report data.
 */
class Insights_Engine {

	/**
	 * Generate insights from report data.
	 *
	 * @param array $report_data Collected report data from Data_Collector.
	 * @return array Insights array with wins, watch_items, recommendations, health_scores, overall_health, executive_summary.
	 */
	public static function generate( array $report_data ): array {
		$tier = $report_data['tier'] ?? 'basic';

		$wins            = [];
		$watch_items     = [];
		$recommendations = [];
		$health_scores   = [];

		// Security analysis (all tiers).
		$maintenance = $report_data['maintenance'] ?? [];
		$security    = self::analyze_security( $maintenance );

		$health_scores['security'] = $security['score'];
		$wins            = array_merge( $wins, $security['wins'] );
		$watch_items     = array_merge( $watch_items, $security['watches'] );
		$recommendations = array_merge( $recommendations, $security['recs'] );

		// Dev hours analysis (all tiers).
		$dev_hours     = $report_data['dev_hours'] ?? [];
		$dev_analysis  = self::analyze_dev_hours( $dev_hours );

		if ( null !== $dev_analysis['score'] ) {
			$health_scores['dev_hours'] = $dev_analysis['score'];
			$wins            = array_merge( $wins, $dev_analysis['wins'] );
			$watch_items     = array_merge( $watch_items, $dev_analysis['watches'] );
			$recommendations = array_merge( $recommendations, $dev_analysis['recs'] );
		}

		// SEO analysis (Professional+ only).
		if ( 'basic' !== $tier ) {
			$search       = $report_data['search'] ?? [];
			$seo_analysis = self::analyze_seo( $search );

			if ( null !== $seo_analysis['score'] ) {
				$health_scores['seo'] = $seo_analysis['score'];
				$wins            = array_merge( $wins, $seo_analysis['wins'] );
				$watch_items     = array_merge( $watch_items, $seo_analysis['watches'] );
				$recommendations = array_merge( $recommendations, $seo_analysis['recs'] );
			}

			// Traffic analysis (Professional+ only).
			$analytics        = $report_data['analytics'] ?? [];
			$traffic_analysis = self::analyze_traffic( $analytics );

			if ( null !== $traffic_analysis['score'] ) {
				$health_scores['traffic'] = $traffic_analysis['score'];
				$wins            = array_merge( $wins, $traffic_analysis['wins'] );
				$watch_items     = array_merge( $watch_items, $traffic_analysis['watches'] );
				$recommendations = array_merge( $recommendations, $traffic_analysis['recs'] );
			}
		}

		// Cap recommendations at 4.
		$recommendations = array_slice( $recommendations, 0, 4 );

		$overall_health    = self::compute_overall_health( $health_scores );
		$executive_summary = self::build_executive_summary( $health_scores, $wins, $watch_items, $tier );

		return [
			'wins'              => $wins,
			'watch_items'       => $watch_items,
			'recommendations'   => $recommendations,
			'health_scores'     => $health_scores,
			'overall_health'    => $overall_health,
			'executive_summary' => $executive_summary,
		];
	}

	/**
	 * Analyze security/maintenance health.
	 *
	 * @param array $maintenance Maintenance data from MainWP.
	 * @return array With keys: score, wins, watches, recs.
	 */
	private static function analyze_security( array $maintenance ): array {
		$result = [
			'score'   => 'green',
			'wins'    => [],
			'watches' => [],
			'recs'    => [],
		];

		$source = $maintenance['source'] ?? '';

		if ( 'error' === $source || 'not_configured' === $source ) {
			$result['score'] = 'amber';
			return $result;
		}

		$wp_update     = ! empty( $maintenance['wp_update_available'] );
		$theme_update  = ! empty( $maintenance['theme_update_available'] );
		$plugin_updates = (int) ( $maintenance['plugins_updates_count'] ?? 0 );

		// Red: WP core update or 3+ plugin updates.
		if ( $wp_update || $plugin_updates >= 3 ) {
			$result['score'] = 'red';

			if ( $wp_update ) {
				$result['watches'][] = 'WordPress core update is available';
			}
			if ( $plugin_updates >= 3 ) {
				$result['watches'][] = sprintf( '%d plugin updates pending', $plugin_updates );
			}

			$result['recs'][] = [
				'title'     => 'Apply pending updates',
				'rationale' => $wp_update
					? 'A WordPress core update is available, which may include security patches.'
					: sprintf( '%d plugin updates are pending, increasing vulnerability risk.', $plugin_updates ),
				'impact'    => 'Reduces security exposure and ensures compatibility with latest features.',
			];

			return $result;
		}

		// Amber: 1-2 plugin updates.
		if ( $plugin_updates > 0 ) {
			$result['score']     = 'amber';
			$result['watches'][] = sprintf(
				'%d plugin update%s pending',
				$plugin_updates,
				1 === $plugin_updates ? '' : 's'
			);

			return $result;
		}

		// Theme update pending.
		if ( $theme_update ) {
			$result['score']     = 'amber';
			$result['watches'][] = 'Theme update is available';

			return $result;
		}

		// All clear.
		$result['wins'][] = 'Site security is fully current — all plugins, themes, and WordPress core are up to date';

		return $result;
	}

	/**
	 * Analyze SEO health from GSC data.
	 *
	 * @param array $search Search data from GSC.
	 * @return array With keys: score, wins, watches, recs.
	 */
	private static function analyze_seo( array $search ): array {
		$result = [
			'score'   => null,
			'wins'    => [],
			'watches' => [],
			'recs'    => [],
		];

		$source = $search['source'] ?? '';

		if ( 'error' === $source || 'not_configured' === $source || empty( $source ) ) {
			return $result;
		}

		$result['score'] = 'green';

		$ctr = $search['ctr'] ?? null;

		// CTR analysis.
		if ( null !== $ctr ) {
			if ( $ctr >= 4.0 ) {
				$result['wins'][] = sprintf(
					'Click-through rate of %.1f%% exceeds the 3-4%% industry average',
					$ctr
				);
			} elseif ( $ctr < 2.0 ) {
				$result['watches'][] = sprintf(
					'Click-through rate is %.1f%%, below the 2%% minimum target',
					$ctr
				);
				$result['recs'][] = [
					'title'     => 'Optimize meta descriptions and title tags',
					'rationale' => sprintf( 'CTR of %.1f%% suggests search listings are not compelling enough.', $ctr ),
					'impact'    => 'Improved meta descriptions could increase click-through by 20-40%.',
				];
			}
		}

		// MoM comparison.
		$comparison = $search['comparison'] ?? [];
		if ( ! empty( $comparison ) ) {
			$clicks_change = $comparison['clicks_change'] ?? 0;

			if ( $clicks_change > 10 ) {
				$result['wins'][] = sprintf(
					'Search clicks grew %.0f%% month-over-month',
					$clicks_change
				);
			} elseif ( $clicks_change < -10 ) {
				$result['score']     = 'red';
				$result['watches'][] = sprintf(
					'Search clicks declined %.0f%% month-over-month',
					abs( $clicks_change )
				);
				$result['recs'][] = [
					'title'     => 'Audit content freshness on top-ranking pages',
					'rationale' => sprintf( 'Search clicks dropped %.0f%%, which may indicate ranking losses.', abs( $clicks_change ) ),
					'impact'    => 'Refreshing content on key pages can recover lost search visibility.',
				];
			} elseif ( $clicks_change < 0 ) {
				$result['score']     = 'amber';
				$result['watches'][] = 'Search clicks declined slightly this month';
			}
		}

		// Position analysis.
		$position = $search['position'] ?? null;
		if ( null !== $position && ! empty( $comparison ) ) {
			$prev_position = $comparison['prev_position'] ?? null;

			if ( null !== $prev_position ) {
				$position_change = $prev_position - $position; // Positive = improved.

				if ( $position < 20 && $position_change > 0 ) {
					$result['wins'][] = sprintf(
						'Average search position improved to %.1f (up %.1f spots)',
						$position,
						$position_change
					);
				} elseif ( $position_change < -3 ) {
					$result['watches'][] = sprintf(
						'Average search position dropped to %.1f (down %.1f spots)',
						$position,
						abs( $position_change )
					);
				}
			}
		}

		return $result;
	}

	/**
	 * Analyze traffic health from GA4 data.
	 *
	 * @param array $analytics Analytics data from GA4.
	 * @return array With keys: score, wins, watches, recs.
	 */
	private static function analyze_traffic( array $analytics ): array {
		$result = [
			'score'   => null,
			'wins'    => [],
			'watches' => [],
			'recs'    => [],
		];

		$source = $analytics['source'] ?? '';

		if ( 'skipped' === $source || 'error' === $source || empty( $source ) ) {
			return $result;
		}

		$result['score'] = 'green';

		$sessions          = $analytics['sessions'] ?? 0;
		$previous_sessions = $analytics['previous_sessions'] ?? 0;
		$bounce_rate       = $analytics['bounce_rate'] ?? null;

		// Sessions change.
		if ( $previous_sessions > 0 ) {
			$sessions_change = ( $sessions - $previous_sessions ) / $previous_sessions * 100;

			if ( $sessions_change > 10 ) {
				$result['wins'][] = sprintf(
					'Site traffic grew %.0f%% month-over-month (%s sessions)',
					$sessions_change,
					number_format( $sessions )
				);
			} elseif ( $sessions_change < -10 ) {
				$result['score']     = 'red';
				$result['watches'][] = sprintf(
					'Site traffic declined %.0f%% month-over-month',
					abs( $sessions_change )
				);
				$result['recs'][] = [
					'title'     => 'Investigate traffic decline',
					'rationale' => sprintf( 'Sessions dropped %.0f%% compared to last month.', abs( $sessions_change ) ),
					'impact'    => 'Identifying the source of the decline can help recover lost traffic.',
				];
			} elseif ( $sessions_change < 0 ) {
				$result['score']     = 'amber';
				$result['watches'][] = 'Site traffic declined slightly this month';
			}
		}

		// Bounce rate analysis.
		if ( null !== $bounce_rate ) {
			if ( $bounce_rate > 60 ) {
				if ( 'green' === $result['score'] ) {
					$result['score'] = 'amber';
				}
				$result['watches'][] = sprintf(
					'Bounce rate is %.0f%%, above the 55%% target',
					$bounce_rate
				);
				$result['recs'][] = [
					'title'     => 'Review top landing pages for content relevance and page speed',
					'rationale' => sprintf( 'Bounce rate of %.0f%% suggests visitors are not finding what they need.', $bounce_rate ),
					'impact'    => 'Reducing bounce rate by 10-15% can significantly increase engagement and conversions.',
				];
			} elseif ( $bounce_rate < 40 ) {
				$result['wins'][] = sprintf(
					'Excellent engagement — bounce rate of %.0f%%',
					$bounce_rate
				);
			}
		}

		return $result;
	}

	/**
	 * Analyze dev hours usage.
	 *
	 * @param array $dev_hours Dev hours data from Monday.com.
	 * @return array With keys: score, wins, watches, recs.
	 */
	private static function analyze_dev_hours( array $dev_hours ): array {
		$result = [
			'score'   => null,
			'wins'    => [],
			'watches' => [],
			'recs'    => [],
		];

		$source = $dev_hours['source'] ?? '';

		if ( 'error' === $source || empty( $source ) ) {
			return $result;
		}

		$hours_included = (float) ( $dev_hours['hours_included'] ?? 0 );
		$hours_used     = (float) ( $dev_hours['hours_used'] ?? 0 );

		if ( $hours_included <= 0 ) {
			$result['score'] = 'green';
			return $result;
		}

		// Exceeded hours.
		if ( $hours_used > $hours_included ) {
			$exceeded          = $hours_used - $hours_included;
			$result['score']     = 'red';
			$result['watches'][] = sprintf(
				'Development hours exceeded by %.1f hour%s',
				$exceeded,
				$exceeded == 1.0 ? '' : 's'
			);
			$result['recs'][] = [
				'title'     => 'Review project scope for next month',
				'rationale' => sprintf(
					'%.1f of %.0f included hours used — exceeding the monthly allocation.',
					$hours_used,
					$hours_included
				),
				'impact' => 'Right-sizing the plan or prioritizing tasks prevents overage charges.',
			];

			return $result;
		}

		$usage_pct = ( $hours_used / $hours_included ) * 100;

		if ( $usage_pct > 85 ) {
			$result['score']     = 'red';
			$result['watches'][] = sprintf(
				'Approaching monthly hour limit (%.0f%% used — %.1f of %.0f hours)',
				$usage_pct,
				$hours_used,
				$hours_included
			);
		} elseif ( $usage_pct >= 60 ) {
			$result['score']     = 'amber';
			$result['watches'][] = sprintf(
				'%.0f%% of monthly development hours used (%.1f of %.0f)',
				$usage_pct,
				$hours_used,
				$hours_included
			);
		} else {
			$result['score'] = 'green';
		}

		return $result;
	}

	/**
	 * Compute overall health from individual scores.
	 *
	 * @param array $health_scores Map of category => green|amber|red.
	 * @return string Worst score across all categories.
	 */
	private static function compute_overall_health( array $health_scores ): string {
		if ( in_array( 'red', $health_scores, true ) ) {
			return 'red';
		}
		if ( in_array( 'amber', $health_scores, true ) ) {
			return 'amber';
		}
		return 'green';
	}

	/**
	 * Build an executive summary from insights data.
	 *
	 * @param array  $health_scores Health score map.
	 * @param array  $wins          List of win strings.
	 * @param array  $watches       List of watch item strings.
	 * @param string $tier          Client tier.
	 * @return string 2-3 sentence summary.
	 */
	private static function build_executive_summary( array $health_scores, array $wins, array $watches, string $tier ): string {
		$overall  = self::compute_overall_health( $health_scores );
		$sentences = [];

		// Overall health statement.
		if ( 'green' === $overall ) {
			$sentences[] = 'Your site is healthy and secure this month.';
		} elseif ( 'amber' === $overall ) {
			$sentences[] = 'Your site is mostly healthy this month with a few items to monitor.';
		} else {
			$sentences[] = 'Your site needs attention this month on a few important items.';
		}

		// Biggest win.
		if ( ! empty( $wins ) ) {
			$sentences[] = ucfirst( $wins[0] ) . '.';
		}

		// For basic tier, keep it short.
		if ( 'basic' === $tier ) {
			return implode( ' ', $sentences );
		}

		// Biggest concern.
		if ( ! empty( $watches ) ) {
			$sentences[] = 'One area to monitor: ' . lcfirst( $watches[0] ) . '.';
		}

		return implode( ' ', $sentences );
	}
}
