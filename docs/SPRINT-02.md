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
4. Post/redirect/get notice flow for segment operations
5. UI consistency for segment controls in filter area

## Delivered
- [x] Segment storage option (`oa_saved_segments`) keyed by scope
- [x] Segment-aware filter resolution (selected segment + explicit query overrides)
- [x] Saved view selector in advanced filter panel
- [x] Manage-only actions:
  - [x] Save current filters as a named view
  - [x] Delete existing view
- [x] Segment operation notices (saved/deleted/validation errors)
- [x] Segment control UI polish in `assets/admin.css`

## Validation
- PHP lint passed:
  - `includes/class-oa-admin.php`
- Manual UX target:
  - Saved view loads correctly into report filters
  - Save/delete actions visible only for manage users

## Next sprint candidates
- Add “default view per page” preference.
- Add global “shared vs private” segment support.
- Add segment export/import for migration across sites.
