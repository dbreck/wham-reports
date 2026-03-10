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
	$tier        = $report_data['tier'] ?? 'basic';

	// PDF URL.
	$pdf_url = get_post_meta( $report_id, '_wham_pdf_url', true );
	if ( ! $pdf_url ) {
		$pdf_url = get_post_meta( $report_id, '_wham_pdf_url_swiss', true );
	}
?>

<div class="wrap wham-admin">
	<h1><?php echo esc_html( $post->post_title ); ?></h1>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wham-reports-drafts' ) ); ?>">&larr; Back to Draft List</a></p>

	<?php if ( $pdf_url ) : ?>
		<p><a href="<?php echo esc_url( $pdf_url ); ?>" class="button" target="_blank">Preview PDF</a></p>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wham_approve_report" />
		<input type="hidden" name="report_id" value="<?php echo esc_attr( $report_id ); ?>" />
		<?php wp_nonce_field( 'wham_approve_report' ); ?>

		<p class="submit">
			<button type="submit" name="send_email" value="1" class="button button-primary">Approve &amp; Send Email</button>
			<button type="submit" name="send_email" value="0" class="button">Approve (Dashboard Only)</button>
		</p>
	</form>
</div>

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
