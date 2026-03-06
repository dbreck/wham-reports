<?php
/**
 * WHAM Report Email Template
 *
 * Variables available:
 *   $client_name  (string) — Client display name
 *   $period_label (string) — e.g. "March 2026"
 *   $pdf_url      (string) — URL to the PDF file
 *   $tier         (string) — basic / professional / premium
 *   $report_id    (int)    — wham_report post ID
 */
defined( 'ABSPATH' ) || exit;

$dashboard_url = home_url( '/client-dashboard/?report=' . intval( $report_id ) );
$tier_label    = ucfirst( $tier ?: 'Basic' );
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WHAM Report — <?php echo esc_html( $period_label ); ?></title>
</head>
<body style="margin:0; padding:0; background:#f4f4f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

<!-- Wrapper -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4; padding:32px 16px;">
<tr><td align="center">

<!-- Container -->
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; overflow:hidden; max-width:560px; width:100%;">

    <!-- Header -->
    <tr>
        <td style="background:#1a1a1a; padding:28px 32px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <span style="font-size:24px; font-weight:800; color:#ffffff; letter-spacing:-0.02em;">WHAM</span>
                        <br>
                        <span style="font-size:10px; text-transform:uppercase; letter-spacing:0.08em; color:#999;">Web Hosting &amp; Maintenance</span>
                    </td>
                    <td align="right" style="vertical-align:top;">
                        <span style="font-size:11px; color:#999;"><?php echo esc_html( $period_label ); ?> Report</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Body -->
    <tr>
        <td style="padding:32px;">

            <p style="font-size:16px; font-weight:700; color:#1a1a1a; margin:0 0 6px 0;">
                Hi <?php echo esc_html( explode( ' ', $client_name )[0] ?? $client_name ); ?>,
            </p>

            <p style="font-size:14px; color:#444; line-height:1.6; margin:0 0 24px 0;">
                Your <?php echo esc_html( $period_label ); ?> website report is ready. Here's a snapshot of what we've been keeping an eye on for you this month.
            </p>

            <!-- Highlights Box -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                    <td style="background:#f8f8f8; border-radius:6px; padding:20px 24px; border-left:3px solid #1a1a1a;">
                        <p style="font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:#888; margin:0 0 8px 0; font-weight:600;">Your report includes</p>
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:3px 0; font-size:14px; color:#333;">✓ &nbsp;Software updates &amp; maintenance status</td>
                            </tr>
                            <?php if ( $tier === 'professional' || $tier === 'premium' ) : ?>
                            <tr>
                                <td style="padding:3px 0; font-size:14px; color:#333;">✓ &nbsp;Search performance &amp; website analytics</td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td style="padding:3px 0; font-size:14px; color:#333;">✓ &nbsp;Development hours summary</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- CTA Buttons -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                    <td align="center">
                        <a href="<?php echo esc_url( $dashboard_url ); ?>"
                           style="display:inline-block; padding:12px 28px; background:#1a1a1a; color:#ffffff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:600; margin-right:8px;">
                            View Report Online
                        </a>
                        <?php if ( $pdf_url ) : ?>
                        <a href="<?php echo esc_url( $pdf_url ); ?>"
                           style="display:inline-block; padding:12px 28px; background:#ffffff; color:#1a1a1a; text-decoration:none; border-radius:5px; font-size:14px; font-weight:600; border:1px solid #ccc;">
                            Download PDF
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <p style="font-size:13px; color:#888; line-height:1.5; margin:0;">
                The full PDF is also attached to this email for your records.
            </p>

        </td>
    </tr>

    <!-- Divider -->
    <tr>
        <td style="padding:0 32px;">
            <hr style="border:none; border-top:1px solid #eee; margin:0;">
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="padding:24px 32px;">
            <p style="font-size:12px; color:#999; margin:0 0 4px 0;">
                Questions about your report? Just reply to this email.
            </p>
            <p style="font-size:11px; color:#bbb; margin:0;">
                WHAM — Web Hosting &amp; Maintenance by Clear Phosphor
                <br>
                <?php echo esc_html( $tier_label ); ?> Plan &nbsp;·&nbsp; <?php echo esc_html( $period_label ); ?>
            </p>
        </td>
    </tr>

</table>
<!-- /Container -->

</td></tr>
</table>
<!-- /Wrapper -->

</body>
</html>
