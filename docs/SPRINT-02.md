# Sprint 02 - Segments and Reporting UX (Week 3-4)

This sprint starts Phase 2 (analytics depth) by making cross-report filtering reusable.

## Objectives
- Introduce saved views (segments) for report filters.
- Keep filter UX compact while increasing power.
- Maintain role separation (view vs manage) for segment operations.

## Scope
1. Saved view model by report scope (traffic/goals/funnels/campaigns/coupons/revenue)
2. Saved view load flow in advanced filters
3. Save/delete segment controls (manage-only)
4. Shared/private segment visibility rules
5. Default view per page (per-user)
6. Post/redirect/get notice flow for segment operations
7. UI consistency for segment controls in filter area

## Delivered
- [x] Segment storage option (`oa_saved_segments`) keyed by scope
- [x] Segment-aware filter resolution (selected segment + explicit query overrides)
- [x] Saved view selector in advanced filter panel
- [x] Manage-only actions:
  - [x] Save current filters as a named view
  - [x] Delete existing view
- [x] Visibility model for segments:
  - [x] Private segments (owner only)
  - [x] Shared segments (all users with analytics access)
- [x] Default view per page (per-user preference)
- [x] Segment migration tooling:
  - [x] Export segments JSON from Settings
  - [x] Import segments (merge)
  - [x] Import segments (replace)
- [x] Segment operation notices (saved/deleted/default/errors)
- [x] Segment control UI polish in `assets/admin.css`
- [x] UTM attribution modes in Settings + tracker (`first_touch` / `last_touch`)
- [x] Campaign report attribution badge + export metadata contract

## Validation
- PHP lint passed:
  - `includes/class-oa-admin.php`
- Manual UX target:
  - Saved view loads correctly into report filters
  - Save/delete actions visible only for manage users
  - Default view persists per user and auto-applies when no explicit filter query is set

## Next sprint candidates
- Add audit metadata (last used, usage count) for segment lifecycle cleanup.
- Add quick favorite-segment chips near the filter bar.
- Add attribution model metadata to report headers/exports for clearer auditability.
