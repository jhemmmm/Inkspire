---
phase: quick-260910-fup
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
    - resources/css/app.css
    - vite.config.ts
    - resources/views/app.blade.php
    - public/logo.png
    - public/business_logo.png
    - resources/js/layouts/auth/AuthBrandLayout.vue
    - resources/js/layouts/AuthLayout.vue
    - resources/js/pages/auth/Login.vue
autonomous: true
requirements:
    - 'Quick task 260910-fup: reskin UI to demo royal-blue Inkspire design system (retheme shadcn tokens, swap font, add brand logos, rebuild login as two-panel card) — see task description in orchestrator prompt'

must_haves:
    truths:
        - 'Every shadcn-vue primitive across all 7 role portals renders in the royal-blue palette purely from resources/css/app.css token changes — zero edits under resources/js/components/ui/'
        - 'The login page renders as a two-panel card: white brand panel (logo, headline, 3 stats) on the left, royal-blue form panel (business logo, badge, form) on the right'
        - 'ForgotPassword.vue, ResetPassword.vue, and ConfirmPassword.vue still display their page-specific title and description text correctly inside the new layout'
        - 'Login still authenticates via the EMAIL field through the existing Wayfinder store.form() binding — no regression to the Fortify auth flow'
        - 'npm run build and npm run check both pass with the new theme, font, and layout'
    artifacts:
        - path: 'resources/css/app.css'
          provides: 'Royal-blue shadcn token retheme (:root + .dark) and Plus Jakarta Sans font-sans'
          contains: '--primary: hsl(223.6 69.2% 33.1%)'
        - path: 'resources/js/layouts/auth/AuthBrandLayout.vue'
          provides: 'Two-panel branded auth layout (single root element) consumed by AuthLayout.vue'
          min_lines: 40
        - path: 'public/logo.png'
          provides: 'Inkspire wordmark for the left panel'
        - path: 'public/business_logo.png'
          provides: 'Squarefoot Graphics & Ads logo for the right panel'
    key_links:
        - from: 'resources/js/layouts/AuthLayout.vue'
          to: 'resources/js/layouts/auth/AuthBrandLayout.vue'
          via: 'component import + render, replacing AuthSimpleLayout'
          pattern: 'AuthBrandLayout'
        - from: 'resources/js/pages/auth/Login.vue'
          to: 'public/logo.png / public/business_logo.png'
          via: 'img src inside AuthBrandLayout.vue'
          pattern: '/logo.png|/business_logo.png'
        - from: 'resources/css/app.css'
          to: 'resources/js/components/ui/**'
          via: 'CSS custom properties consumed by every shadcn primitive (bg-primary, border-input, etc.)'
          pattern: "var\\(--primary\\)"
---

<objective>
Reskin Inkspire's UI to the demo's royal-blue design system by retheming the shadcn-vue CSS token file, swapping the app font to Plus Jakarta Sans, adding the two brand logos as tracked static assets, and rebuilding the login page as a two-panel branded card — without porting any of demo/style.css's hand-rolled classes or touching generated shadcn primitives.

Purpose: The user explicitly likes the demo's login page, panel design, logo, and color scheme. Because every shadcn-vue primitive reads CSS custom properties from resources/css/app.css, retheming those variables reskins all 7 role portals with zero component edits — this is the lever for the whole task.

Output: Retheme resources/css/app.css (light + dark), Plus Jakarta Sans wired through vite.config.ts + app.css, public/logo.png + public/business_logo.png tracked as static assets, a new resources/js/layouts/auth/AuthBrandLayout.vue wired into AuthLayout.vue, and a redesigned resources/js/pages/auth/Login.vue matching the demo's blue-panel form.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@./CLAUDE.md

# Design reference (read-only — do NOT port classes/JS from these files)

@demo/style.css
@demo/index.html

# Files this plan edits

@resources/css/app.css
@vite.config.ts
@resources/views/app.blade.php
@resources/js/layouts/AuthLayout.vue
@resources/js/layouts/auth/AuthSimpleLayout.vue
@resources/js/pages/auth/Login.vue
@resources/js/components/PasswordInput.vue
@resources/js/components/TextLink.vue
</context>

<tasks>

<task type="auto">
  <name>Task 1: Retheme shadcn tokens, swap font to Plus Jakarta Sans, add brand logo assets</name>
  <files>resources/css/app.css, vite.config.ts, resources/views/app.blade.php, public/logo.png, public/business_logo.png</files>
  <action>
Retheme resources/css/app.css. Keep the existing hsl() format, variable names, and file structure exactly — only change values. In the `:root` block, replace these values (converted from the demo's hex palette at demo/style.css lines 10-45):
--background: hsl(257.1 100% 98.6%); --foreground: hsl(222.2 41.5% 12.7%); --card: hsl(0 0% 100%); --card-foreground: hsl(222.2 41.5% 12.7%); --popover: hsl(0 0% 100%); --popover-foreground: hsl(222.2 41.5% 12.7%); --primary: hsl(223.6 69.2% 33.1%); --primary-foreground: hsl(0 0% 100%); --secondary: hsl(218.5 86.7% 91.2%); --secondary-foreground: hsl(222.2 41.5% 12.7%); --muted: hsl(231.4 100% 95.9%); --muted-foreground: hsl(232 9.9% 29.6%); --accent: hsl(229.7 100% 94.3%); --accent-foreground: hsl(225 71.4% 27.5%); --destructive: hsl(0 75.5% 41.6%); --destructive-foreground: hsl(0 0% 100%); --border: hsl(236.5 16.8% 80.2%); --input: hsl(236.5 16.8% 80.2%); --ring: hsl(223.6 69.2% 33.1%); --chart-1: hsl(223.6 69.2% 33.1%); --chart-2: hsl(224.9 53.4% 50.4%); --chart-3: hsl(219.7 82.2% 64.7%); --chart-4: hsl(249.5 56.6% 62.9%); --chart-5: hsl(219.2 62% 76.3%); --radius: 0.75rem; --sidebar-background: hsl(0 0% 100%); --sidebar-foreground: hsl(232 9.9% 29.6%); --sidebar-primary: hsl(223.6 69.2% 33.1%); --sidebar-primary-foreground: hsl(0 0% 100%); --sidebar-accent: hsl(231.4 100% 95.9%); --sidebar-accent-foreground: hsl(225 71.4% 27.5%); --sidebar-border: hsl(236.5 16.8% 80.2%); --sidebar-ring: hsl(223.6 69.2% 33.1%); --sidebar: hsl(0 0% 100%).

In the `.dark` block, replace these values (a deepened royal-blue counterpart, not grayscale — do not delete the `.dark` block):
--background: hsl(229.7 57.4% 12%); --foreground: hsl(232.5 100% 95.3%); --card: hsl(229.8 50.6% 15.9%); --card-foreground: hsl(232.5 100% 95.3%); --popover: hsl(229.8 50.6% 15.9%); --popover-foreground: hsl(232.5 100% 95.3%); --primary: hsl(224.9 53.4% 50.4%); --primary-foreground: hsl(0 0% 100%); --secondary: hsl(225.9 45.1% 22.2%); --secondary-foreground: hsl(218.5 86.7% 91.2%); --muted: hsl(228 43.5% 18%); --muted-foreground: hsl(227.1 26.3% 68.6%); --accent: hsl(226.2 48.9% 26.1%); --accent-foreground: hsl(227.1 100% 89%); --destructive: hsl(1.1 83.2% 62.5%); --destructive-foreground: hsl(0 0% 100%); --border: hsl(227.8 39.1% 27.1%); --input: hsl(227.8 39.1% 27.1%); --ring: hsl(224.9 53.4% 50.4%); --chart-1: hsl(227.9 100% 71.8%); --chart-2: hsl(219.7 82.2% 64.7%); --chart-3: hsl(225.3 76.6% 74.9%); --chart-4: hsl(250.2 76.9% 74.5%); --chart-5: hsl(229.9 100% 86.1%); --sidebar-background: hsl(228.3 54.5% 12.9%); --sidebar-foreground: hsl(226.8 39.8% 79.8%); --sidebar-primary: hsl(224.9 53.4% 50.4%); --sidebar-primary-foreground: hsl(0 0% 100%); --sidebar-accent: hsl(227.3 48.1% 21.2%); --sidebar-accent-foreground: hsl(226.5 100% 92.2%); --sidebar-border: hsl(227.8 39.1% 27.1%); --sidebar-ring: hsl(224.9 53.4% 50.4%); --sidebar: hsl(228.3 54.5% 12.9%).

Then swap the font in the same file: change both `--font-sans` declarations (the one inside `@theme inline` at the top and the one inside the `@layer utilities { body, html { ... } }` block) from `'Instrument Sans'`/`Instrument Sans` to `'Plus Jakarta Sans'`, keeping the rest of the fallback stack (ui-sans-serif, system-ui, sans-serif, emoji fallbacks) unchanged.

In vite.config.ts, change the `bunny('Instrument Sans', { weights: [400, 500, 600] })` call inside the `laravel({ fonts: [...] })` block to `bunny('Plus Jakarta Sans', { weights: [400, 500, 600, 700, 800] })`. Bunny Fonts serving "Plus Jakarta Sans" at these weights was already confirmed during planning (`curl https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800` returns 200 with matching @font-face rules) — no Google Fonts fallback needed.

In resources/views/app.blade.php, update the flash-of-wrong-theme inline `<style>` block: change `html { background-color: oklch(1 0 0); }` to `html { background-color: hsl(257.1 100% 98.6%); }` and `html.dark { background-color: oklch(0.145 0 0); }` to `html.dark { background-color: hsl(229.7 57.4% 12%); }` — these must match the new light/dark --background values exactly or the page will flash the old color before hydration.

Finally, copy the two brand logo PNGs into public/ as tracked assets: `cp demo/logo.png public/logo.png` and `cp demo/business_logo.png public/business_logo.png`. Copy the files as-is, do not modify or re-encode them. Do NOT copy demo/style.css or demo/main.js anywhere in the app.
</action>
<verify>
<automated>test -f public/logo.png && test -f public/business_logo.png && grep -c "Plus Jakarta Sans" resources/css/app.css && grep -c "Plus Jakarta Sans" vite.config.ts && grep -c "223.6 69.2% 33.1%" resources/css/app.css</automated>
</verify>
<done>resources/css/app.css has the new light+dark royal-blue token values and Plus Jakarta Sans font-sans in both spots; vite.config.ts loads Plus Jakarta Sans at weights 400/500/600/700/800; app.blade.php's inline flash-prevention colors match the new --background values; public/logo.png and public/business_logo.png exist as new tracked files.</done>
</task>

<task type="auto">
  <name>Task 2: Build AuthBrandLayout two-panel layout and wire it into AuthLayout</name>
  <files>resources/js/layouts/auth/AuthBrandLayout.vue, resources/js/layouts/AuthLayout.vue</files>
  <action>
Create resources/js/layouts/auth/AuthBrandLayout.vue as a new single-root-element Vue SFC (`<script setup lang="ts">`) reproducing the demo's two-panel login card (demo/index.html lines 17-64, demo/style.css lines 1261-1400) using Tailwind utilities only — do not import or reference demo/style.css.

Props: `title?: string` and `description?: string` (same shape as AuthSimpleLayout.vue, required because ForgotPassword.vue, ResetPassword.vue, and ConfirmPassword.vue pass these via `defineOptions({ layout: { title, description } })` and route through AuthLayout.vue).

Structure, outer to inner:

- Root wrapper: `min-h-screen flex items-center justify-center bg-muted p-6` (bg-muted resolves to the new --muted token, matching the demo's surface-container-low ground color).
- Card: `flex flex-col md:flex-row w-full max-w-[860px] min-h-[520px] rounded-[20px] overflow-hidden shadow-[0_20px_60px_rgba(0,40,142,0.12),0_4px_16px_rgba(0,0,0,0.08)] max-[699px]:max-w-[420px]` — two columns by default, stacking to a single column with a narrower max-width under 700px via Tailwind v4's arbitrary `max-[699px]:` variant, matching demo/style.css's `@media (max-width: 700px)` block.
- LEFT panel (branding), a `<div class="flex-1 bg-white flex flex-col justify-center p-[48px_44px]">` containing in order: (1) `<img src="/logo.png" alt="Inkspire" class="h-18 w-auto object-contain mb-5" />`; (2) a divider `<div class="w-10 h-[3px] rounded-full bg-primary mb-5" />`; (3) headline `<h2 class="text-[22px] font-extrabold leading-snug tracking-tight text-foreground mb-3">Your all-in-one<br />print shop portal.</h2>`; (4) sub-copy `<p class="text-[13px] leading-relaxed text-muted-foreground mb-8">Faster workflows, smarter order management, and real-time team coordination — built for Squarefoot Graphics &amp; Ads.</p>`; (5) a stats row `<div class="flex items-center gap-5">` containing three stat blocks separated by `<div class="w-px h-7 bg-border" />` dividers, each stat block a `<div>` with a number line `<div class="text-xl font-extrabold leading-none text-primary">` and a label line below it `<div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">`. Use exactly these three stats: "7" / "Staff Roles" (Inkspire has exactly 7 roles — the demo's markup says 6, which is wrong for this project; use 7), "24/7" / "Available", "Live" / "Queue Monitor".
- RIGHT panel (form), a `<div class="flex-1 bg-primary flex flex-col items-center justify-center p-[48px_44px] text-center">` containing in order: (1) `<img src="/business_logo.png" alt="Squarefoot Graphics &amp; Ads" class="w-50 h-auto object-contain mb-3" />`; (2) `<h1 v-if="title" class="mb-0.5 text-[17px] font-extrabold tracking-tight text-white">{{ title }}</h1>`; (3) `<p class="mb-3.5 max-w-[300px] text-[13px] font-medium tracking-wide text-white/75">{{ description || 'Printing Management System' }}</p>` — this falls back to the demo's static sub-copy when no description prop is passed; (4) the static role badge `<span class="mb-5 inline-flex items-center rounded-full border border-white/25 bg-white/15 px-3.5 py-1 text-[10px] font-bold uppercase tracking-widest text-white/90">Staff Portal</span>`; (5) `<div class="w-full max-w-[280px]"><slot /></div>` to host the form content.

This design means: when both title and description are empty (Login.vue will set them to '' in Task 3), the panel shows exactly the demo's static "Printing Management System" + "Staff Portal" look. When ForgotPassword/ResetPassword/ConfirmPassword pass their real title/description, those render above/in place of the static copy so those three pages still read correctly.

Then update resources/js/layouts/AuthLayout.vue: change the import from `AuthSimpleLayout` to `AuthBrandLayout` (from `@/layouts/auth/AuthBrandLayout.vue`) and update the `<AuthLayout>` template usage accordingly, keeping the same `title`/`description` prop passthrough and `<slot />`. Do not delete AuthSimpleLayout.vue or AuthSplitLayout.vue — they are out of scope and may still be referenced elsewhere.
</action>
<verify>
<automated>grep -c "AuthBrandLayout" resources/js/layouts/AuthLayout.vue && grep -c "defineProps" resources/js/layouts/auth/AuthBrandLayout.vue && grep -c "<slot" resources/js/layouts/auth/AuthBrandLayout.vue</automated>
</verify>
<done>resources/js/layouts/auth/AuthBrandLayout.vue exists as a single-root SFC accepting title/description props and rendering the two-panel card with logo/business_logo image references, a slot for the form, and the fallback "Printing Management System"/"Staff Portal" copy; resources/js/layouts/AuthLayout.vue imports and renders AuthBrandLayout instead of AuthSimpleLayout.</done>
</task>

<task type="auto" tdd="false">
  <name>Task 3: Redesign Login.vue for the blue panel and run final verification</name>
  <files>resources/js/pages/auth/Login.vue</files>
  <action>
Update resources/js/pages/auth/Login.vue to read correctly against AuthBrandLayout's royal-blue right panel, while preserving all existing behavior.

Layout props: change `defineOptions({ layout: { title: '', description: '' } })` (empty strings) so AuthBrandLayout falls back to its static "Printing Management System" sub-text and the "Staff Portal" badge — matching the demo's exact login look. Do not remove the `defineOptions` call itself, just empty the two strings.

Imports: add `Link` to the existing `import { Form, Head } from '@inertiajs/vue3';` import (becomes `import { Form, Head, Link } from '@inertiajs/vue3';`). Add `import { Lock, Mail } from '@lucide/vue';` for the field-label icons. Remove the now-unused `import TextLink from '@/components/TextLink.vue';` import (it is being replaced by a plain `<Link>` for this page only — do not edit TextLink.vue itself, it is still used by other auth pages).

Status flash block: change the class from `text-green-600` to `text-emerald-200` (readable on the blue panel) — keep `mb-4 text-center text-sm font-medium` and the `v-if="status"` binding, the `{{ status }}` interpolation, and the surrounding div unchanged.

Email field: replace `<Label for="email">Email address</Label>` with `<Label for="email" class="mb-1.5 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-widest text-white/70"><Mail class="size-3.5" />Email Address</Label>`. Add this class to the existing `<Input id="email" ...>` element: `class="h-11 rounded-[10px] border-[1.5px] border-white/20 bg-white/12 px-3.5 text-white placeholder:text-white/35 selection:bg-white/25 selection:text-white focus-visible:border-white/70 focus-visible:ring-white/20 focus-visible:ring-[3px] dark:border-white/20 dark:bg-white/12 dark:text-white"`. Keep every other attribute on `<Input>` byte-for-byte unchanged: `id="email" type="email" name="email" required autofocus :tabindex="1" autocomplete="email" placeholder="email@example.com"` — the field MUST stay `type="email"` / `name="email"` (Fortify authenticates by email, not username — do not rename to match the demo's "username" field).

Password field: replace `<Label for="password">Password</Label>` with `<Label for="password" class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-widest text-white/70"><Lock class="size-3.5" />Password</Label>`, keeping it inside the existing `flex items-center justify-between` wrapper alongside the forgot-password link. Replace the `<TextLink v-if="canResetPassword" :href="request()" class="text-sm" :tabindex="5">Forgot your password?</TextLink>` with `<Link v-if="canResetPassword" :href="request()" :tabindex="5" class="text-xs font-medium text-white/50 transition-colors hover:text-white/90">Forgot your password?</Link>` — keep the `v-if`, `:href`, `:tabindex`, and link text identical; only the component and its classes change (avoids fighting TextLink.vue's hardcoded `text-foreground` class, which is still used unmodified by other pages). Add the same override class string used on the email `<Input>` to `<PasswordInput id="password" name="password" required :tabindex="2" autocomplete="current-password" placeholder="Password" class="...">`, keeping its other attributes unchanged.

Remember-me row: change `<Label for="remember" class="flex items-center space-x-3">` to `<Label for="remember" class="flex items-center space-x-3 text-sm text-white/80">`, and add a class to `<Checkbox id="remember" name="remember" :tabindex="3">`: `class="border-white/40 data-[state=checked]:border-white/70 data-[state=checked]:bg-white data-[state=checked]:text-primary focus-visible:ring-white/30"`.

Sign-in button: add a class to the existing `<Button type="submit" ... data-test="login-button">`: `class="mt-4 w-full bg-white text-primary hover:bg-white/90 focus-visible:ring-white/40"`, keeping `type="submit"`, `:tabindex="4"`, `:disabled="processing"`, `data-test="login-button"`, the `<Spinner v-if="processing" />`, and the "Log in" text unchanged.

Do not change the `<Form v-bind="store.form()" :reset-on-success="['password']" v-slot="{ errors, processing }">` wrapper, the `<InputError>` usages, or the overall `grid gap-6` / `flex flex-col gap-6` structure — only the classes and label content described above.

After all edits, run the full verification: `npm run build` (must succeed, confirms Vite/Tailwind/Wayfinder compile cleanly with the new font, tokens, and layout) and `npm run check` (must pass — vue-tsc + lint/format checks across the changed files). Do not run `npm install` or `composer install/require` — no dependency changes are part of this plan.
</action>
<verify>
<automated>grep -c 'name="email"' resources/js/pages/auth/Login.vue && grep -c 'type="email"' resources/js/pages/auth/Login.vue && grep -c 'bg-white/12' resources/js/pages/auth/Login.vue && grep -c 'data-test="login-button"' resources/js/pages/auth/Login.vue && npm run build && npm run check
</automated>
</verify>
<done>resources/js/pages/auth/Login.vue keeps type="email"/name="email" and the Wayfinder Form binding, but every field label, input, checkbox, and button now reads correctly against the royal-blue right panel (white/translucent inputs, white labels/icons, white sign-in button); npm run build and npm run check both pass.</done>
</task>

</tasks>

<threat_model>

## Trust Boundaries

| Boundary                      | Description                                                                                                                                                         |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Browser to Bunny Fonts CDN    | Plus Jakarta Sans font files are fetched from fonts.bunny.net at build/runtime via the existing `bunny()` helper -- same mechanism already used for Instrument Sans |
| Static file server to public/ | public/logo.png and public/business_logo.png are new binary assets served directly, no server-side processing                                                       |
| Browser to /login             | Login form fields and their name attributes are visually restyled only -- no change to server-side validation, CSRF, or the Fortify auth pipeline                   |

## STRIDE Threat Register

| Threat ID           | Category               | Component                                                     | Disposition | Mitigation Plan                                                                                                                                                                                                                                                                     |
| ------------------- | ---------------------- | ------------------------------------------------------------- | ----------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| T-quick260910fup-01 | Tampering              | vite.config.ts `bunny('Plus Jakarta Sans', ...)` font source  | accept      | Uses the same `laravel-vite-plugin/fonts` `bunny()` helper already trusted for Instrument Sans in this project; no new CDN or trust surface introduced, font weights confirmed served (200 OK) during planning                                                                      |
| T-quick260910fup-02 | Tampering              | public/logo.png, public/business_logo.png (new static assets) | accept      | Static binary images copied verbatim from demo/ (already present in the repository, provided by the project owner); no executable content, no server-side processing                                                                                                                |
| T-quick260910fup-03 | Information Disclosure | resources/js/pages/auth/Login.vue email/password fields       | mitigate    | `type="email"`, `name="email"`, `name="password"`, `required`, `autocomplete` attributes and the Wayfinder `store.form()` binding are left byte-for-byte unchanged -- only CSS classes and label markup change, so no new data-exposure surface is introduced by the visual restyle |
| T-quick260910fup-SC | Tampering              | npm/composer package installs                                 | n/a         | This plan makes zero dependency changes (`npm install`/`composer install`/`require` are explicitly out of scope); no new packages are introduced, so the Package Legitimacy Gate does not apply                                                                                     |

</threat_model>

<verification>
1. `npm run build` completes without errors (Vite compiles resources/css/app.css, vite.config.ts's new font config, and the new/edited Vue files cleanly).
2. `npm run check` passes (vue-tsc type-check + vite-plus lint/format checks across resources/css/app.css, vite.config.ts, resources/views/app.blade.php, resources/js/layouts/AuthLayout.vue, resources/js/layouts/auth/AuthBrandLayout.vue, resources/js/pages/auth/Login.vue).
3. Confirm no file under resources/js/components/ui/ was touched: `git diff --name-only | grep "components/ui/"` must return empty.
4. Manual visual confirmation (human step, not automatable): serve the app on port 8001 (`php artisan serve --port=8001`, since port 8000 is occupied by an unrelated project), open `http://localhost:8001/login` in a browser, and confirm:
   - The login page renders as a two-panel card: white left panel (Inkspire logo, headline, 3 stats reading "7 / Staff Roles"), royal-blue right panel (business logo, "Printing Management System", "Staff Portal" badge, and the restyled white-on-blue form).
   - Log in with `owner@inkspire.test` / `DemoPass123!` and confirm the authenticated portal (e.g. the Owner dashboard) shows a royal-blue primary button and a royal-blue active sidebar item -- confirming the token retheme reached the rest of the app with zero component edits.
</verification>

<success_criteria>

- [ ] resources/css/app.css has the new light + dark royal-blue token values and Plus Jakarta Sans in both `--font-sans` spots
- [ ] vite.config.ts loads Plus Jakarta Sans at weights 400/500/600/700/800
- [ ] resources/views/app.blade.php's inline flash-prevention background colors match the new `--background` values
- [ ] public/logo.png and public/business_logo.png exist as tracked files
- [ ] resources/js/layouts/auth/AuthBrandLayout.vue exists, is a single-root SFC, accepts title/description props, and is wired into resources/js/layouts/AuthLayout.vue
- [ ] resources/js/pages/auth/Login.vue keeps the email-based auth field and Wayfinder Form binding intact, restyled for the blue panel
- [ ] No file under resources/js/components/ui/ was modified
- [ ] `npm run build` and `npm run check` both pass
- [ ] Human has visually confirmed the two-panel login card and the royal-blue theme on an authenticated portal page
      </success_criteria>

<output>
Create `.planning/quick/260910-fup-reskin-ui-to-demo-royal-blue-inkspire-de/260910-fup-SUMMARY.md` when done
</output>
