# Sprint 01 - Foundation Reliability (Week 1-2)

This sprint executes the first block of the roadmap toward v1.0 quality.

## Objectives
- Build operational safety tools for maintainers.
- Improve upgrade confidence and traceability.
- Establish health checks that catch critical failures early.

## Scope
1. System Health admin page
2. Schema repair and cron repair actions
3. Migration history logging
4. Baseline runtime checks (schema/tables/cron/orphans/retention)
5. Schema drift checks (missing columns, primary key mismatch, missing indexes)
6. Diagnostics export and API/CLI parity for ops workflows

## Delivered
- [x] Health submenu + page scaffold
- [x] KPI snapshot for schema/cron/retention/report freshness
- [x] Maintenance actions:
  - [x] Run cleanup now
  - [x] Clear dashboard cache
  - [x] Repair schema (dbDelta)
  - [x] Reschedule cron
- [x] Table integrity report
- [x] Health checks report
- [x] Migration history log
- [x] Schema audit report (columns, primary key, secondary indexes)
- [x] Diagnostics JSON export from Health page
- [x] REST health + diagnostics endpoints
- [x] WP-CLI command support (`wp ordelix ...`)
- [x] Self-test suite (admin + WP-CLI) for health baseline verification
- [x] Data quality baseline checks (campaign/revenue/coupon consistency) in Health
- [x] Compliance helper tools in Settings:
  - [x] Date-range JSON export bundle
  - [x] Date-range analytics erase
  - [x] Full analytics daily-table erase (explicit confirmation)
- [x] WP-CLI compliance operations:
  - [x] `wp ordelix data-export --from=YYYY-MM-DD --to=YYYY-MM-DD`
  - [x] `wp ordelix data-erase-range --from=YYYY-MM-DD --to=YYYY-MM-DD`
  - [x] `wp ordelix data-erase-all --confirm=YES`
- [x] Capability matrix baseline:
  - [x] `ordelix_analytics_view` for read-only access
  - [x] `ordelix_analytics_manage` for write/maintenance operations
  - [x] Health + WP-CLI capability repair action (`repair_caps` / `wp ordelix caps-repair`)
  - [x] Health/runtime checks include capability matrix audit
- [x] Regression smoke harness:
  - [x] `wp ordelix regression-smoke --from=YYYY-MM-DD --to=YYYY-MM-DD`
  - [x] PowerShell runner script `scripts/phase1-smoke.ps1`
  - [x] GitHub Actions CI workflow `.github/workflows/ci.yml` (lint + WP smoke)

## Validation
- PHP lint passed for updated files:
  - `includes/class-oa-db.php`
  - `includes/class-oa-reports.php`
  - `includes/class-oa-admin.php`
  - `includes/views/health.php`

## Next sprint candidates
- Expand regression smoke into CI-driven automated tests (migration + ingestion fixtures).
- Add optional ZIP diagnostics bundle (JSON + selected CSV snapshots) for support cases.
- Add role/capability matrix pass (view-only vs manage) and update docs.
