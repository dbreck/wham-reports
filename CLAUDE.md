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
| `MainWP_Source` | MainWP | Updates & Maintenance | Direct DB (3-table JOIN: `mainwp_wp` + `mainwp_wp_sync` + `mainwp_wp_options`) or REST API fallback |
| `GSC_Source` | Google Search Console | SEO (Search) | Search Analytics API via service account JWT |
| `GA4_Source` | Google Analytics 4 | SEO (Analytics) | GA4 Data API v1 via service account JWT |
| `Monday_Source` | Monday.com | Dev Hours | GraphQL API (board `9141194124`) |

### Orchestration

`Data_Collector` — coordinates all sources, creates `wham_report` CPT posts with JSON data in `_wham_report_data` meta, generates PDFs, sends emails, and updates Monday.com status.

### PDF Generation

`PDF_Generator` — renders PHP templates to HTML, converts via DomPDF (primary) or wkhtmltopdf (fallback). PDFs saved to `wp-content/uploads/wham-reports/{year}/`.

### Client Dashboard

`Report_Renderer` + `[wham_dashboard]` shortcode. Users with `wham_client` role see only their reports (matched via `_wham_monday_client_id` user meta). REST API at `wham/v1/reports`.

### Insights & Charts (v2.0.0+)

- `Insights_Engine` — auto-generates wins, watch items, recommendations, and health scores (green/amber/red) from metric thresholds
- `Chart_Generator` — generates PNG chart images via QuickChart.io API, caches in `wp-content/uploads/wham-reports/charts/`
- `GitHub_Updater` — AJAX-based update checker; prefers `wham-reports.zip` release asset over zipball; inline update UI on plugins page

### Templates

- `templates/pdf/report-basic.php` — 1-page health scorecard with 6 traffic-light cards (table-based layout for DomPDF)
- `templates/pdf/report-professional.php` — 4-6 page narrative report with embedded charts, KPI cards, data tables, recommendations
- `templates/dashboard/` — client-facing dashboard views (report-detail.php has insights, charts, recommendations)
- `templates/admin/` — settings, mapping, admin dashboard, report-draft review, schedule configuration
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
| `wham_github_token` | GitHub PAT for private repo update checks (or use `WHAM_GITHUB_TOKEN` constant) |
| `wham_require_review` | If enabled, reports are created as drafts requiring admin approval before publishing |

## Cron

Monthly report generation scheduled via `wham_generate_reports` cron event (1st of month, 6:00 AM). Manual trigger available in admin.

## Monday.com Column IDs

Items (clients): `color_mkqxgshx` (Plan Type), `numeric_mkvgfs2a` (Monthly Included Hours), `link_mkqx3m9` (Website)
Subitems (monthly entries): `duration_mksn85dy` (Time Tracking), `status` (Report Status), `date0` (Date Sent)

## Client Mapping Page (templates/admin/mapping.php)

The mapping page is the most complex admin template. Key architecture:
- **Dynamic rows** — no hardcoded empty rows; JS `createRow()` builds rows with all fields + user picker
- **Reference table** — 11 Monday.com clients with auto-matched MainWP Site ID (DB lookup) and GA4 Property ID (Admin API)
- **User picker** — dropdown panel per row with checkboxes, search filter, live count; user data passed to JS as `pickerUsers` JSON for dynamic rows
- **User meta sync** — on save, clears all `_wham_monday_client_id` meta then re-sets from form checkboxes
- **+ buttons** — copy reference data into a new mapping row, detect duplicates (flash + scroll to existing)

## Standard Operating Procedures

- **Load relevant skills** before starting work. At minimum, load `wordpress-plugin-dev` and `plugin-settings` skills for any plugin development tasks. Use `/skill-lookup` if the task might benefit from additional specialized skills.
- **Use agent teams** for non-trivial implementation tasks (2+ files or distinct parallel workstreams) per global SOPs.
- **Deploy after changes** — the user can only see changes once the plugin is updated on Flywheel. After making changes, transfer modified files via SSH (see "Deployment Shortcut" below). Always confirm with the user before deploying.

## GitHub Repo & Updates

- **Repo:** `github.com/dbreck/wham-reports` (public)
- **Branch:** `master`
- **Release flow:** `./build-release.sh` creates `wham-reports.zip` → `gh release create vX.Y.Z wham-reports.zip --title "vX.Y.Z"`
- **Important:** Always attach `wham-reports.zip` as release asset — GitHub's auto-generated zipball names it `wham-reports-X.Y.Z.zip` which installs as a separate plugin

## Deployment

Two methods:
1. **GitHub releases** (preferred): `./build-release.sh` + push + `gh release create vX.Y.Z wham-reports.zip` → "Check for updates" on plugins page (AJAX, no redirect)
2. **SSH direct** (fallback): `ssh -T -i ~/.ssh/flywheel_clearph team+clearph+wham@ssh.getflywheel.com "cat > /www/wp-content/plugins/wham-reports/path/to/file.php" < local/path/to/file.php`

## MainWP Database Schema

Data spans 3 tables (Flywheel prefix: `wp_kdqwvtlbbw_`):
- **`mainwp_wp`** — `id, name, url, plugins (JSON array), themes (JSON array), plugin_upgrades (JSON object keyed by slug), theme_upgrades (JSON)`
- **`mainwp_wp_sync`** — `wpid, version (WP version), dtsSync (unix timestamp)`
- **`mainwp_wp_options`** — per-site key/value pairs: `phpversion`, `site_info` (JSON)
- All data is JSON, not PHP serialized — use `json_decode()` not `maybe_unserialize()`
- `plugin_upgrades` keys are slugs like `plugin-dir/plugin-file.php` — never use as numeric index
