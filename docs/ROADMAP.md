# Ordelix Analytics Roadmap

This document is the execution path to take the plugin from current state to a complete, production-grade analytics product.

## Product vision
- Privacy-first analytics for WordPress and WooCommerce.
- Fast, clear, action-oriented UI.
- Reliable data pipeline with explainable metrics.
- Strong operational safety (migrations, retention, compliance, monitoring).

## Current baseline (as of v0.4.9)
- Dashboard, insights, anomalies, goals, funnels, campaigns.
- WooCommerce revenue and coupon analytics.
- CSV export API and email reports.
- Saved views with shared/private visibility, per-user defaults, and import/export migration.
- Quick saved-view chips in report filter bars for one-click segment switching.
- Segment usage metadata to rank/favorite frequently used views.
- Attribution mode selector for campaign crediting (first touch / last touch).
- Campaign exports include attribution metadata for downstream BI audit trails.
- Modernized admin UI foundation.
- Regression smoke harness via WP-CLI (`regression-smoke`) and PowerShell runner.
- Active sprint logs: `docs/SPRINT-01.md`, `docs/SPRINT-02.md`

## Definition of "complete" (v1.0 gate)
The plugin is considered complete for v1.0 when all of these are true:
- Data correctness and migration safety are validated.
- UI consistency is complete across all admin pages.
- Global filter experience is coherent across reports.
- Compliance controls are documented and testable.
- Core test coverage exists for critical flows.
- Release and support documentation is ready.

---

## Phase plan

## Phase 1 - Foundation and reliability (v1.0)
Priority: P0

### Scope
- Migration hardening
  - idempotent upgrade routines
  - DB health checks and repair actions
  - versioned migration log
- Data quality layer
  - timezone normalization checks
  - bot/noise filter improvements
  - dedupe guardrails for purchase and event writes
- Privacy/compliance tooling
  - retention validation screen
  - data export/delete helpers for compliance operations
- Access control
  - capability map for admin/analyst/editor read/write paths

### UI/UX completion in this phase
- Standardize all forms, controls, spacing, table headers, empty states.
- Build one reusable component style guide and apply to all pages.
- Final pass on responsive behavior (desktop/tablet/mobile).

### Acceptance criteria
- No failed migrations across fresh install + upgrade paths.
- Core report totals remain stable under regression tests.
- No inconsistent form control styles in admin pages.
- Admin page Lighthouse accessibility checks: no severe issues.

---

## Phase 2 - Analytics depth (v1.1)
Priority: P1

### Scope
- Global filters (device/source/medium/campaign/page/event) applied consistently.
- Saved views/segments.
- Funnel drop-off analysis by step and trend over time.
- Cohort and retention report basics.
- Attribution modes: first touch, last touch.

### Acceptance criteria
- Saved segments can be created, loaded, deleted.
- All report pages honor active global filters.
- Funnel diagnostics expose clear drop-off reasons and trend deltas.

---

## Phase 3 - Commerce intelligence (v1.2)
Priority: P1

### Scope
- Revenue and coupon drilldowns by product/category/channel/campaign.
- AOV trends and new vs returning customer splits.
- Refund-aware net revenue views.
- Coupon efficiency scorecards.

### Acceptance criteria
- Revenue views reconcile against WooCommerce totals within defined tolerance.
- Coupon views support export and filter parity with campaigns/revenue.

---

## Phase 4 - Actionability and integrations (v1.3)
Priority: P2

### Scope
- Custom alert rules (threshold + condition builder).
- Digest and alert delivery via email and webhook (Slack-ready).
- "What changed" panel with top movers and likely drivers.

### Acceptance criteria
- Alert rule lifecycle (create/edit/disable/delete) is stable.
- Alert payloads include deep links to filtered reports.

---

## Phase 5 - Platform maturity (v2.0)
Priority: P2

### Scope
- Full automated test matrix (unit/integration/e2e).
- Performance budget and stress test harness.
- Advanced extension hooks and developer docs.
- Optional plugin modules architecture hardening.

### Acceptance criteria
- CI test gates block regressions in core metrics.
- Target performance budget is met for large datasets.

---

## Execution model

## Sprint cadence
- 2-week sprints.
- Sprint 1-3: Phase 1 (v1.0).
- Sprint 4-5: Phase 2 (v1.1).
- Sprint 6-7: Phase 3 (v1.2).
- Sprint 8: Phase 4 kickoff.

## Delivery artifacts per sprint
- Change log entry.
- Test evidence (what was validated).
- Migration notes if schema touched.
- UI screenshots for changed pages.
- Rollback notes.

## Risk register (top)
- Migration regressions on upgrade from older versions.
- Data drift from edge-case event ingestion.
- UI inconsistency reintroduced by future page changes.
- Third-party plugin conflicts in admin screens.

## Mitigations
- Add migration smoke tests and backup/restore checklist.
- Add metric snapshot regression tests.
- Keep shared admin UI tokens/components centralized in `assets/admin.css` + templates.
- Keep notice isolation logic in Ordelix admin assets.

---

## Working backlog checklist

## P0 (must complete for v1.0)
- [x] Migration safety toolkit and health panel.
- [ ] Data quality audit utilities.
- [x] Compliance helper actions and docs.
- [x] Role/capability matrix finalization.
- [ ] Complete UI consistency pass on all pages.
- [ ] Core automated tests for reports and ingestion.
- [ ] Release docs and troubleshooting guide.

## P1
- [ ] Global filters and saved segments.
- [ ] Funnel diagnostics and retention reporting.
- [x] Attribution modes.
- [ ] Commerce intelligence drilldowns.

## P2
- [ ] Alert rule builder and webhook integrations.
- [ ] CI performance harness and extension maturity work.

---

## Notes for future planning updates
- Update this file at end of each sprint.
- Keep version target and checklist statuses current.
- Add links to shipped PRs/commits next to completed items.
