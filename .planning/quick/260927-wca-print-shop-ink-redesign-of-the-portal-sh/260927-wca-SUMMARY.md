---
phase: quick-260927-wca
plan: 01
status: complete
date: 2026-09-27
---

# Quick Task 260927-wca: Print-shop ink panel redesign — Summary

**One-liner:** The six role portals get a deep-navy sidebar frame with an
animated brand-red active rail, a press-sheet hero band on every PageHeader
(CMYK bar, wash, halftone), CMYK ink chips on StatCards, a CMY bar on section
headings, a tinted table header and subtle reduced-motion-safe motion. It all
lands in tokens and shared components.

## What changed

- `app.css`: new `--ink-cyan/magenta/yellow/key` tokens (light + dark,
  contrast-checked for icons on their chips). The `--sidebar-*` tokens are
  retoned to navy. `--sidebar-primary` is a lifted brand red, used for marks only.
- Shell: `NavMain` (rail), `AppLogo` (white wordmark inside the sidebar only),
  `UserInfo` (gradient avatar, opacity-dimmed secondary lines),
  `AppSidebar` (footer divider), `AppSidebarHeader` (rounded top corners),
  `AppShell` (no navy frame in print), `app.ts` (themed progress bar).
- Components: `PageContainer` (fade-up), `PageHeader` (hero band),
  `SectionHeading` (CMY bar), `StatCard` (`ink` prop, chip + glow, lift only
  when linked and motion is welcome), `DataTableCard` (tinted thead),
  `EmptyState` (gradient chip).
- Pages: `ink="..."` attributes only, on 5 StatCard pages. Content borders
  that borrowed `border-sidebar-border` now use `border-border` in
  UserManagement, SystemConfiguration, ReportsWorkspace, AppHeader and the
  starter Dashboard.
- No `components/ui/*` edits.

## Verification

- `npm run types:check` passes. `npm run check` is clean for every file
  touched; `.planning/STATE.md` formatting was already failing.
- A headless-Chrome review of all admin pages in light and dark themes, at
  1440 and 375, then a five-lens review workflow (visual, UI checklist,
  cross-portal, Tailwind compile, a11y) with adversarial verification. It
  confirmed 18 findings, which collapse into 7 issues, all fixed: the
  sidebar-border leak into content borders (Reports selected state), the
  focus-lift ring misalignment, lift under reduced motion, NAVIGATION label
  contrast, the NavMain transition override, the hard-coded progress colour,
  and the navy frame in print.
- No PHP changed, so the Pest suite was not rerun.

## Deviations

- Only the admin account has a known password, so non-admin portals were
  reviewed statically rather than in the browser.
