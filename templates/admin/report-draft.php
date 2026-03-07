<?php defined( 'ABSPATH' ) || exit;

$report_id = isset( $_GET['report_id'] ) ? absint( $_GET['report_id'] ) : 0;

if ( $report_id ) :
	// ── Mode B: Single Draft Review ──────────────────────────────────
	$post = get_post( $report_id );
	if ( ! $post || 'wham_report' !== $post->post_type ) {
		echo '<div class="wrap"><div class="notice notice-error"><p>Report not found.</p></div></div>';
		return;
	}

	$report_data = json_decode( get_post_meta( $report_id, '_wham_report_data', true ), true );
	$insights    = $report_data['insights'] ?? [];
	$health      = $insights['health_scores'] ?? [];

	$exec_summary    = $insights['executive_summary'] ?? '';
	$wins            = $insights['wins'] ?? [];
	$watch_items     = $insights['watch_items'] ?? [];
	$recommendations = $insights['recommendations'] ?? [];

	$health_colors = [
		'good'    => '#16a34a',
		'warning' => '#ca8a04',
		'poor'    => '#dc2626',
	];
?>
<style>
	.wham-health-badges { display: flex; gap: 12px; flex-wrap: wrap; margin: 16px 0 24px; }
	.wham-health-badge {
		padding: 8px 16px; border-radius: 6px; color: #fff; font-weight: 600; font-size: 13px;
	}
	.wham-field-group { margin-bottom: 12px; padding: 12px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; }
	.wham-field-group .wham-remove { float: right; cursor: pointer; color: #b32d2e; border: none; background: none; font-size: 18px; line-height: 1; }
	.wham-rec-fields { display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 8px; }
	.wham-rec-fields input, .wham-rec-fields textarea { width: 100%; }
	.wham-rec-fields textarea { min-height: 60px; }
	.wham-add-btn { margin-top: 8px; }
</style>

<div class="wrap wham-admin">
	<h1><?php echo esc_html( $post->post_title ); ?></h1>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wham-reports-drafts' ) ); ?>">&larr; Back to Draft List</a></p>

	<?php if ( ! empty( $health ) ) : ?>
		<h3>Health Scores</h3>
		<div class="wham-health-badges">
			<?php foreach ( $health as $key => $score ) :
				$level = 'good';
				if ( is_numeric( $score ) ) {
					if ( $score < 50 ) { $level = 'poor'; }
					elseif ( $score < 75 ) { $level = 'warning'; }
				}
			?>
				<span class="wham-health-badge" style="background:<?php echo esc_attr( $health_colors[ $level ] ); ?>;">
					<?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?>: <?php echo esc_html( $score ); ?>
				</span>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wham_approve_report" />
		<input type="hidden" name="report_id" value="<?php echo esc_attr( $report_id ); ?>" />
		<?php wp_nonce_field( 'wham_approve_report' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row"><label for="executive_summary">Executive Summary</label></th>
				<td>
					<textarea id="executive_summary" name="executive_summary" rows="4" class="large-text"><?php echo esc_textarea( $exec_summary ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row">Wins</th>
				<td>
					<div id="wins-list">
						<?php foreach ( $wins as $win ) : ?>
							<div class="wham-field-group">
								<button type="button" class="wham-remove" title="Remove">&times;</button>
								<input type="text" name="wins[]" value="<?php echo esc_attr( $win ); ?>" class="large-text" />
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button wham-add-btn" id="add-win">Add Win</button>
				</td>
			</tr>
			<tr>
				<th scope="row">Watch Items</th>
				<td>
					<div id="watch-list">
						<?php foreach ( $watch_items as $item ) : ?>
							<div class="wham-field-group">
								<button type="button" class="wham-remove" title="Remove">&times;</button>
								<input type="text" name="watch_items[]" value="<?php echo esc_attr( $item ); ?>" class="large-text" />
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button wham-add-btn" id="add-watch">Add Watch Item</button>
				</td>
			</tr>
			<tr>
				<th scope="row">Recommendations</th>
				<td>
					<div id="rec-list">
						<?php foreach ( $recommendations as $rec ) : ?>
							<div class="wham-field-group">
								<button type="button" class="wham-remove" title="Remove">&times;</button>
								<div class="wham-rec-fields">
									<input type="text" name="rec_title[]" value="<?php echo esc_attr( $rec['title'] ?? '' ); ?>" placeholder="Title" />
									<textarea name="rec_rationale[]" placeholder="Rationale"><?php echo esc_textarea( $rec['rationale'] ?? '' ); ?></textarea>
									<input type="text" name="rec_impact[]" value="<?php echo esc_attr( $rec['impact'] ?? '' ); ?>" placeholder="Impact" />
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button wham-add-btn" id="add-rec">Add Recommendation</button>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" name="send_email" value="1" class="button button-primary">Approve &amp; Send Email</button>
			<button type="submit" name="send_email" value="0" class="button">Approve (Dashboard Only)</button>
		</p>
	</form>
</div>

<script>
(function() {
	function addRemoveHandler(btn) {
		btn.addEventListener('click', function() { this.closest('.wham-field-group').remove(); });
	}
	document.querySelectorAll('.wham-remove').forEach(addRemoveHandler);

	function addRow(containerId, html) {
		var container = document.getElementById(containerId);
		var div = document.createElement('div');
		div.className = 'wham-field-group';
		div.innerHTML = '<button type="button" class="wham-remove" title="Remove">&times;</button>' + html;
		addRemoveHandler(div.querySelector('.wham-remove'));
		container.appendChild(div);
	}

	document.getElementById('add-win').addEventListener('click', function() {
		addRow('wins-list', '<input type="text" name="wins[]" value="" class="large-text" />');
	});
	document.getElementById('add-watch').addEventListener('click', function() {
		addRow('watch-list', '<input type="text" name="watch_items[]" value="" class="large-text" />');
	});
	document.getElementById('add-rec').addEventListener('click', function() {
		addRow('rec-list',
			'<div class="wham-rec-fields">' +
			'<input type="text" name="rec_title[]" value="" placeholder="Title" />' +
			'<textarea name="rec_rationale[]" placeholder="Rationale"></textarea>' +
			'<input type="text" name="rec_impact[]" value="" placeholder="Impact" />' +
			'</div>'
		);
	});
})();
</script>

<?php else :
	// ── Mode A: Draft List ───────────────────────────────────────────
?>
<div class="wrap wham-admin">
	<h1>Review Report Drafts</h1>

	<?php if ( isset( $_GET['approved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Report approved and published.</p></div>
	<?php endif; ?>

	<?php
	$drafts = get_posts([
		'post_type'      => 'wham_report',
		'post_status'    => 'draft',
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'DESC',
	]);

	if ( empty( $drafts ) ) :
	?>
		<p>No reports pending review.</p>
	<?php else : ?>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th>Client</th>
					<th>Period</th>
					<th>Tier</th>
					<th>Generated</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $drafts as $draft ) :
					$client_name = get_post_meta( $draft->ID, '_wham_client_name', true );
					$period      = get_post_meta( $draft->ID, '_wham_period', true );
					$tier        = get_post_meta( $draft->ID, '_wham_tier', true );
				?>
					<tr>
						<td><?php echo esc_html( $client_name ); ?></td>
						<td><?php echo esc_html( $period ); ?></td>
						<td><?php echo esc_html( ucfirst( $tier ) ); ?></td>
						<td><?php echo esc_html( get_the_date( 'M j, Y g:i A', $draft ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wham-reports-drafts&report_id=' . $draft->ID ) ); ?>" class="button button-small">Review</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
<?php endif; ?>
