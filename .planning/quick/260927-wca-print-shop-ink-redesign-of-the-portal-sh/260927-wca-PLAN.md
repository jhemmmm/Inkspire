---
phase: quick-260927-wca
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
    - resources/css/app.css
    - resources/js/app.ts
    - resources/js/components/AppSidebar.vue
    - resources/js/components/AppSidebarHeader.vue
    - resources/js/components/AppLogo.vue
    - resources/js/components/NavMain.vue
    - resources/js/components/UserInfo.vue
    - resources/js/components/PageContainer.vue
    - resources/js/components/PageHeader.vue
    - resources/js/components/SectionHeading.vue
    - resources/js/components/StatCard.vue
    - resources/js/components/DataTableCard.vue
    - resources/js/components/EmptyState.vue
    - resources/js/pages/admin/Dashboard.vue
    - resources/js/pages/artist/Dashboard.vue
    - resources/js/pages/artist/PerformanceReport.vue
    - resources/js/pages/accounting-staff/AccountsReceivable/Index.vue
    - resources/js/pages/accounting-staff/Expenses/Index.vue
autonomous: true
requirements:
    - 'Quick task 260927-wca: the portal panel looks too plain and not alive — refresh it in the "print-shop ink" direction the user picked (shell + shared components, subtle motion)'

must_haves:
    truths:
        - 'Every portal renders a deep-navy sidebar (light and dark theme) with a white wordmark, and the active nav item carries an animated brand-red rail'
        - 'PageHeader renders as a hero band: CMYK colour bar on top, soft primary/cyan wash, faded halftone dot field — decorative layers aria-hidden and hidden in print'
        - 'StatCard takes an `ink` prop (cyan | magenta | yellow | key, default cyan) that tints its icon chip and corner glow; `tone="attention"` still overrides to destructive'
        - 'A StatCard wrapped in a link lifts and gains a shadow on hover; a bare StatCard does not move'
        - 'Page content fades up on mount; every animation and transition is disabled under prefers-reduced-motion'
        - 'Only semantic tokens are used in templates — the four inks are new tokens in app.css with light and dark values'
        - 'No file under resources/js/components/ui/ is edited'
        - 'npm run types:check, npm run check and the PHP feature suite still pass'
    artifacts:
        - path: 'resources/css/app.css'
          provides: '--ink-cyan/magenta/yellow/key tokens (light + dark) and navy --sidebar-* tokens'
          contains: '--ink-cyan'
        - path: 'resources/js/components/StatCard.vue'
          provides: 'ink prop + chip/glow + link-hover lift'
          contains: 'ink'
    key_links:
        - from: 'resources/css/app.css'
          to: 'StatCard / PageHeader / SectionHeading / EmptyState'
          via: '--color-ink-* theme tokens (bg-ink-cyan etc.)'
          pattern: 'ink-(cyan|magenta|yellow|key)'
---

<objective>
Make the six role portals feel alive without touching page logic: a navy
sidebar frame, a print-shop hero band on every page title, CMYK ink accents on
stat tiles and section headings, a tinted table header, a livelier empty state,
and subtle motion. The work lands in theme tokens and the shared shell and
components, so all 27 PageHeader pages and 18 DataTableCard pages pick it up at
once.
</objective>

<design>
Ink meaning (StatCard `ink`), kept consistent across portals:
- cyan    — work in flow: queue, jobs, production, designs (the default)
- magenta — people and decisions: staff, approvals waiting on someone
- yellow  — money: outstanding, collected, expenses, receivables
- key     — records and performance figures

Brand red stays an identity mark only (active-nav rail, CMYK-adjacent accents,
Inertia progress bar) — never the fill of a clickable control, per the app.css
brand/destructive split.
</design>

<tasks>
<task id="1">
  <files>resources/css/app.css, resources/js/app.ts</files>
  <action>Add --ink-cyan/magenta/yellow/key (light + dark) and map them in @theme inline. Retone --sidebar-* to a deep navy (both themes) with a cyan focus ring. Set the Inertia progress colour to the brand red.</action>
  <verify>Vite compiles; bg-ink-cyan resolves in the browser.</verify>
  <done>Tokens exist in both themes; no raw colours in templates.</done>
</task>
<task id="2">
  <files>AppSidebar, AppSidebarHeader, AppLogo, NavMain, UserInfo</files>
  <action>Navy shell: white wordmark inside the sidebar only, role pill on sidebar-accent, animated brand-red active rail, footer divider, gradient avatar fallback, muted text via opacity so it reads on navy and on popovers, header top corners rounded to match the inset.</action>
  <verify>Screenshot admin portal, light + dark, 1440 + 375 (mobile sheet).</verify>
  <done>Sidebar legible in both themes; active item obvious.</done>
</task>
<task id="3">
  <files>PageContainer, PageHeader, SectionHeading, StatCard, DataTableCard, EmptyState, 5 StatCard pages</files>
  <action>Hero band, CMYK section marker, ink chips + glow + link lift, tinted table head, gradient empty-state chip, fade-up entrance (motion-reduce safe). Pass ink on StatCard pages (attribute only); drop admin Dashboard's now-redundant hover class.</action>
  <verify>types:check, check, feature tests, screenshots of admin pages both themes + 375px.</verify>
  <done>All must_haves hold.</done>
</task>
</tasks>
