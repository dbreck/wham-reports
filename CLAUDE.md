# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

WHAM Reports is a WordPress plugin for automated monthly client reporting. It collects data from four sources (MainWP, Google Search Console, GA4, Monday.com), generates PDF reports via DomPDF, and provides a client-facing dashboard. Built for the WHAM (Web Hosting And Maintenance) service by Clear ph Design.

## Hosting & Deployment

- **Host:** Flywheel (managed WordPress)
- **SSH:** `ssh team+clearph+wham@ssh.getflywheel.com -i ~/.ssh/flywheel_clearph`
- **Server plugin path:** `/www/wp-content/plugins/wham-reports/`
- **No Composer on server** — install dependencies locally, then upload via: `tar czf /tmp/wham-vendor.tar.gz vendor composer.json composer.lock` piped through `ssh -T ... "cd /www/wp-content/plugins/wham-reports && tar xzf -" < /tmp/wham-vendor.tar.gz`
- **No SCP/SFTP** on Flywheel — use tar-over-stdin or `cat > /tmp/file < localfile` + `tar xzf`

## Dependencies

```bash
composer require dompdf/dompdf google/apiclient
```

The plugin loads `vendor/autoload.php` from `WHAM_REPORTS_PATH` in `class-pdf-generator.php`. The autoloader path is critical for DomPDF and Google API Client.

## Architecture

### Entry Point & Autoloading

`wham-reports.php` — singleton `WHAM_Reports` class. Registers a PSR-style autoloader mapping `WHAM_Reports\Foo_Bar` to `includes/class-foo-bar.php`.

### Data Sources (includes/)

Each source class has a `collect()` method returning a normalized array:

| Class | Source | Report Category | API |
|-------|--------|----------------|-----|
| `MainWP_Source` | MainWP | Updates & Maintenance | Direct DB (`wp_mainwp_wp` table) or REST API fallback |
| `GSC_Source` | Google Search Console | SEO (Search) | Search Analytics API via service account JWT |
| `GA4_Source` | Google Analytics 4 | SEO (Analytics) | GA4 Data API v1 via service account JWT |
| `Monday_Source` | Monday.com | Dev Hours | GraphQL API (board `9141194124`) |

### Orchestration

`Data_Collector` — coordinates all sources, creates `wham_report` CPT posts with JSON data in `_wham_report_data` meta, generates PDFs, sends emails, and updates Monday.com status.

### PDF Generation

`PDF_Generator` — renders PHP templates to HTML, converts via DomPDF (primary) or wkhtmltopdf (fallback). PDFs saved to `wp-content/uploads/wham-reports/{year}/`.

### Client Dashboard

`Report_Renderer` + `[wham_dashboard]` shortcode. Users with `wham_client` role see only their reports (matched via `_wham_monday_client_id` user meta). REST API at `wham/v1/reports`.

### Templates

- `templates/pdf/report-basic.php` / `report-professional.php` — HTML templates for PDF (inline styles)
- `templates/dashboard/` — client-facing dashboard views
- `templates/admin/` — settings, mapping, admin dashboard
- `templates/email/` — report delivery email

## Tier System

Three client tiers control data depth:
- **Basic** — MainWP summary, GSC aggregate metrics, Monday.com hours
- **Professional** — adds GA4 analytics, GSC top queries/pages with period comparison, plugin update details
- **Premium** — same as Professional (room for expansion)

## Key WordPress Options

| Option | Purpose |
|--------|---------|
| `wham_monday_api_token` | Monday.com API token |
| `wham_gsc_credentials_json` | Google service account JSON (GSC) |
| `wham_ga4_credentials_json` | Google service account JSON (GA4, falls back to GSC creds) |
| `wham_mainwp_app_password` | MainWP REST API app password |
| `wham_client_map` | JSON mapping: monday_id -> {mainwp_site_id, gsc_property, ga4_property, tier, client_name, client_url, client_email} |

## Cron

Monthly report generation scheduled via `wham_generate_reports` cron event (1st of month, 6:00 AM). Manual trigger available in admin.

## Monday.com Column IDs

Items (clients): `color_mkqxgshx` (Plan Type), `numeric_mkvgfs2a` (Monthly Included Hours), `link_mkqx3m9` (Website)
Subitems (monthly entries): `duration_mksn85dy` (Time Tracking), `status` (Report Status), `date0` (Date Sent)
