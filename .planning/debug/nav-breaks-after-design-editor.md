---
status: investigating
trigger: "Designing is hard. Clicking 'Dashboard' on the breadcrumb/navigation does not return me to the dashboard. (This looks like bug on the viewing the job order, unable to change navigation, even performance report doesn't work)"
created: 2026-09-02T15:50:00Z
updated: 2026-09-03T01:15:00Z
---

## Current Focus
<!-- OVERWRITE on each update - always reflects NOW -->

hypothesis: Bug 1 and Bug 2 confirmed fixed by human re-test (nav works, editor mounts without crashing, no raw exception page). Bug 3 (NEW, found during that same human re-test): the blank-canvas flow renders the TOAST UI editor's chrome (toolbar, menu bar) but the actual drawable canvas area is empty/invisible — "blank canvas does nothing." Root-caused via source trace: `initCanvas()` (tui-image-editor.js:48219) only calls `ui.resizeEditor()` — the ONLY code path that sizes the editor's canvas container — from inside `initLoadImage()`'s `.then()` callback (tui-image-editor.js:49947), which itself is gated behind `if (loadImageInfo.path)` (tui-image-editor.js:48224). Bug 1's fix correctly changed the "no image" value from `undefined` (crash) to `{ path: '', name: '' }` (no crash) — but an empty path is still falsy, so `initLoadImage`/`resizeEditor` is still never called for the blank-canvas case. The crash is gone but the canvas was never actually usable in this flow — tui-image-editor has no "blank canvas, no image" mode; it always expects a real (even if blank/white) image to load and size against.
test: Generate a real blank image client-side (e.g. an offscreen HTML `<canvas>` element sized to the editor's cssMaxWidth/cssMaxHeight, filled white, exported via `.toDataURL('image/png')`) and pass THAT data URI as `loadImage.path` for the no-initial-image case, instead of an empty string. Verify via live CDP repro that `initLoadImage` now runs (network/promise trace), `resizeEditor` is called, and the canvas container has non-zero width/height with a visible white drawable surface. Re-run the draw/crop/shape tool smoke checks and confirm `exportPng()` still produces a valid flattened PNG for Send for Review.
expecting: A properly sized, visible, drawable white canvas immediately after "Start from Blank Canvas" — matching what "Import Reference Image" already gets for free (since a real image always has a real path, so it was never affected by this gap).
next_action: Implement the blank-image-data-URI fix in ToastImageEditor.vue, verify live, then request human re-confirmation again (do not mark resolved without a second explicit "confirmed" from the human — the first attempt was not sufficient for this exact reason).
reasoning_checkpoint:
  hypothesis: "Bug 1: the explicit `loadImage: undefined` in ToastImageEditor.vue's includeUI options overwrites tui-image-editor's internal default via a hasOwnProperty-based shallow extend(), causing initCanvas() to read `.path` off `undefined` and throw synchronously inside Vue's mounted() hook; because Vue 3.5's flushJobs() calls the uncaught-throw-prone flushPostFlushCbs() before resetting currentFlushPromise in its finally block, this single throw permanently disables Vue's scheduler for the rest of the page's life, which is why unrelated sidebar/breadcrumb nav clicks stop having any visible effect afterward. Bug 2: bootstrap/app.php's $exceptions->respond() only special-cases 403, not 422, so all 22 abort_unless/abort_if(...,422,...) call sites app-wide fall through to a raw debug-page response that Inertia's client then displays via its own non-Inertia-response overlay handling — independent of bug 1."
  confirming_evidence:
    - "Live CDP reproduction captured the exact Runtime.exceptionThrown (TypeError: Cannot read properties of undefined (reading 'path') at Ui.initCanvas) and paired Vue warn, immediately on mounting the editor with no initial image."
    - "Direct source trace of tui-image-editor.js confirms _initializeOption's extend() copies own-enumerable keys regardless of undefined value, and initCanvas()'s `loadImageInfo.path` read is exactly where it throws."
    - "Direct source trace of @vue/runtime-core's flushJobs()/flushPostFlushCbs()/queueFlush() confirms the uncaught-throw-before-currentFlushPromise-reset mechanism that would explain a page-wide, permanent loss of reactivity from a single component's mount-time crash."
    - "Direct source trace of @inertiajs/core confirms Accept header is always text/html (never application/json), confirming expectsJson() is false for all Inertia requests; grep confirms bootstrap/app.php only handles 403, not 422, in its respond() callback; grep confirms 22 other 422 abort sites share the exact same exposure app-wide."
  falsification_test: "After the fix, the same CDP repro script must show zero Runtime.exceptionThrown events on mount AND a simulated sidebar Dashboard Link click must produce both a URL change and updated page title/DOM content (not just a fired network request) — if nav still silently does nothing after the exception is gone, the scheduler-stall theory is wrong. For bug 2, PATCHing design/start on an already-in_design job order via router.patch must result in landing back on an Inertia-rendered page with a flashed error toast, not a raw HTML response."
  fix_rationale: "Bug 1's fix supplies the exact value tui-image-editor's own code already treats as 'no image' ({path:'',name:''}) instead of an invalid undefined, addressing the actual malformed-input root cause rather than adding a try/catch around the mount call (which would suppress the symptom without fixing the underlying invalid API usage, and wouldn't be needed at all once the crash itself is prevented). Bug 2's fix extends the exact existing precedent already established for 403 in the same respond() callback, reusing the project's own established flash-toast + redirect-back error convention rather than inventing a new pattern."
  blind_spots: "Have not verified every other tui-image-editor internal code path that might read options.loadImage beyond initCanvas — only that one confirmed crash site was fixed at its source (an always-valid options.loadImage object), which should cover all of them structurally. Have not exhaustively re-tested all 22 abort_unless/abort_if 422 call sites individually after the bootstrap/app.php fix — verified the general mechanism (403 precedent + status-code branch) applies uniformly, and confirmed the reported one (design/start) directly."
tdd_checkpoint: null

## Symptoms
<!-- Written during gathering, then immutable -->

expected: Clicking "Dashboard" or "Performance Report" in the sidebar nav (or the "Dashboard" breadcrumb) while on the Job Order Workspace page navigates away as normal. A 422 response from a form submission (e.g. `design/start` on an already-advanced job order) is handled gracefully by Inertia, not shown as a raw Laravel exception page.
actual: After mounting and interacting with the TOAST UI Image Editor in the Design card, sidebar nav links and the Dashboard breadcrumb stop responding to clicks anywhere on the Job Order Workspace page — no navigation occurs. Separately, a stale click on "Start from Blank Canvas"/"Import Reference Image" against a job order no longer in `in_consultation` status (likely triggered while navigation was already broken, from a stale render) produced a raw, full-page Laravel/Ignition 422 exception page instead of an Inertia-handled error banner.
errors: "Symfony\Component\HttpKernel\Exception\HttpException — This job order is not ready to start a design." — 422, `PATCH http://localhost:8000/artist/job-orders/1/design/start`, thrown from `app/Http/Controllers/Artist/DesignEditorController.php:44` (`abort_unless($jobOrder->status === JobOrderStatus::InConsultation, 422, 'This job order is not ready to start a design.');`). The presence of Laravel's raw debug page (not an Inertia-rendered error) means this specific request reached the server as a full browser navigation, bypassing Inertia's XHR client entirely.
reproduction: |
  1. `composer run dev` (already running in this environment).
  2. Log in as artist@inkspire.test / password (seeded via tinker, see .planning/debug context).
  3. Open job order #1 ("Tarpaulin, 3x5ft (UAT test order)"), status in_consultation, assigned to this artist — Job Order Workspace page.
  4. In the Design card, click "Start from Blank Canvas" (or "Import Reference Image"). TOAST UI editor mounts, tools/color picker work.
  5. Interact with the editor (load an image via its own "Load" button, draw/edit).
  6. Click "Dashboard" or "Performance Report" in the sidebar nav — no navigation happens.
  7. (Secondary symptom) At some point after this, a PATCH to `artist/job-orders/1/design/start` fired and produced a raw 422 Laravel exception page — exact trigger for step 7 not yet isolated (possibly a stale re-click of "Start from Blank Canvas" after Send for Review had already advanced the job order past in_consultation, or a bfcache/stale-DOM interaction after navigation broke).
started: First occurrence — this is new code from Phase 4 (Artist Workflow & Design Editor), specifically Plan 04-04's TOAST UI Image Editor integration, merged into main immediately before this UAT round. The feature (and therefore any possible regression window) did not exist before this phase.

## Eliminated
<!-- APPEND only - prevents re-investigating after /clear -->

- hypothesis: Broken/misconfigured route href on the nav Link components (a routing config mistake)
  evidence: resources/js/components/NavMain.vue uses a standard Inertia <Link :href="item.href"> with no custom logic. "Dashboard" and "Performance Report" resolve to two entirely different generated route URLs (dashboard() vs performance-report.index()) yet both fail identically — a per-link href bug would not explain two unrelated hrefs failing the same way. Points to a page-wide interactivity loss instead.
  timestamp: 2026-09-02T15:45:00Z
- hypothesis: tui-color-picker attaches its own document-level click listener for "click outside to close" that swallows all page clicks
  evidence: grep of node_modules/tui-color-picker/dist/tui-color-picker.js for document.addEventListener/document.on( found no matches.
  timestamp: 2026-09-02T15:45:00Z

## Evidence
<!-- APPEND only - facts discovered during investigation -->

- timestamp: 2026-09-02T15:45:00Z
  checked: resources/js/components/ToastImageEditor.vue (the Vue 3 wrapper around tui-image-editor)
  found: onMounted() constructs `new ImageEditor(editorContainer.value!, {...})` directly against the DOM; onBeforeUnmount() calls `editor?.destroy()`. This destroy only runs if Vue actually unmounts the component — which won't happen if the bug itself is what's preventing navigation (no route change → no unmount → no cleanup).
  implication: Any global (document-level) listeners or DOM elements the library attaches during mount are never cleaned up while the user is stuck on this page, which is consistent with (but does not prove) a lingering-overlay or dangling-listener explanation.
- timestamp: 2026-09-02T15:45:00Z
  checked: node_modules/tui-image-editor/dist/tui-image-editor.js and node_modules/fabric for document-level event listeners
  found: Multiple `document.addEventListener('keydown'/'keyup', ...)` and `document.addEventListener('mousemove'/'mouseup', ...)` calls (via `fabric.util.addListener(document, ...)`) for keyboard shortcuts and slider-drag interactions. No document-level `click` listener found in this pass.
  implication: Rules out a simple global-click-listener explanation for the nav failure; if the root cause is listener-based rather than DOM-overlay-based, it's more likely a keydown/keyup listener interfering with something else, or the explanation lies elsewhere (e.g. a DOM overlay, not a listener).
- timestamp: 2026-09-02T15:45:00Z
  checked: Attempted live reproduction via chrome-devtools MCP (mcp__chrome-devtools__new_page against http://localhost:8000/login)
  found: "Could not connect to Chrome. Check if Chrome is running. Cause: Failed to fetch browser webSocket URL from http://192.168.250.102:9222/json/version: fetch failed" — the MCP server is configured to attach to a remote Chrome instance (likely the user's own machine) which was not reachable from the execution environment at the time.
  implication: All investigation so far is static code review only; the actual DOM/CSS/JS runtime state during the failure has not been directly observed. This is the single most important next step.
- timestamp: 2026-09-03T00:00:00Z
  checked: Live CDP reproduction — launched a local headless Chrome (google-chrome --headless=new --remote-debugging-port=9333) and drove it directly over the DevTools Protocol via a scratch Node script (native WebSocket, no deps), since the pre-configured chrome-devtools MCP remote endpoint (192.168.250.102:9222) was still unreachable this session. Logged in as artist@inkspire.test, reset job order #1 to in_consultation via tinker, navigated to the Job Order Workspace, clicked "Start from Blank Canvas".
  found: A captured Runtime.exceptionThrown CDP event immediately on mount, "Uncaught (in promise)" TypeError — Cannot read properties of undefined (reading 'path'), at Ui.initCanvas (tui-image-editor.js:38733) <- new ImageEditor (tui-image-editor.js:46894) <- ToastImageEditor.vue:23 <- Vue's mounted-hook dispatch (hook.__weh -> flushPostFlushCbs -> flushJobs). Paired with a `[Vue warn]: Unhandled error during execution of mounted hook` console warning naming `<ToastImageEditor>`.
  implication: This is a genuine, reproducible, uncaught JS exception thrown synchronously inside ToastImageEditor's mounted() hook — not a DOM-overlay or dangling-listener issue as originally hypothesized. Overrides the original hypothesis in Current Focus.
- timestamp: 2026-09-03T00:05:00Z
  checked: node_modules/tui-image-editor/dist/tui-image-editor.js — initCanvas() (line 48218-48239), _getLoadImage() (48246-48249), _initializeOption() (47647-47664), and the tui-code-snippet extend() helper it uses (line 31663-31677)
  found: initCanvas() does `var loadImageInfo = this._getLoadImage(); if (loadImageInfo.path) {...}`. _getLoadImage() returns `this.options.loadImage`. _initializeOption() sets a default `loadImage: { path: '', name: '' }` then merges the caller's options object on top via extend(), which does `for (prop in source) if (hasOwnProperty) target[prop] = source[prop]` — copying own-enumerable keys regardless of value.
  implication: ToastImageEditor.vue's `loadImage: props.initialImageUrl ? {...} : undefined` sends an explicit own-enumerable `loadImage: undefined` key when there's no initial image (blank-canvas flow), which the shallow extend() uses to clobber the library's own `{ path: '', name: '' }` default, leaving `this.options.loadImage` as `undefined` and causing the `.path` read to throw. This is the exact, root-cause mechanism for bug 1's crash.
- timestamp: 2026-09-03T00:10:00Z
  checked: node_modules/@vue/runtime-core/dist/runtime-core.esm-bundler.js — queueFlush() (320-324), flushPostFlushCbs() (365-395), flushJobs() (397-437)
  found: flushJobs()'s finally block (422-436) calls `flushPostFlushCbs(seen)` (which has no try/catch around its `cb()` call at line 389) BEFORE `currentFlushPromise = null` (line 432). queueFlush() only schedules a new flush (`resolvedPromise.then(flushJobs)`) when `currentFlushPromise` is falsy.
  implication: An uncaught throw from any post-flush callback (e.g. a mounted() hook) escapes flushJobs()'s finally block before `currentFlushPromise` is reset, leaving it permanently non-null/stale. Every subsequent queueJob()/queuePostFlushCb() call application-wide then silently no-ops on scheduling a new flush, so the DOM stops updating in response to ANY reactive state change for the rest of the page's life — not just within the crashed component. This directly explains why unrelated sidebar/breadcrumb nav clicks stop having any visible effect: the click handlers still fire and Inertia's router still completes its network round-trip, but the resulting page swap never renders.
- timestamp: 2026-09-03T00:15:00Z
  checked: `php artisan tinker` inspection of job order #1's DB state before this session's reset
  found: status was already `in_design` (file_path empty) — not the seeded `in_consultation`, confirming an earlier UAT "Start from Blank Canvas" click had already succeeded server-side (advancing status) before nav broke on that same page load.
  implication: Confirms the sequence — first click succeeds and mounts the editor (crashing at the end of that render's postFlush phase per the evidence above), so a later click landing on the still-visible "Start from Blank Canvas" button (e.g. after a manual full-page reload, since design.canEdit/initialImageUrl-derived `started` state resets to false on any fresh full page load for the blank-canvas flow) resends the same PATCH against an already-advanced job order, producing the 422.
- timestamp: 2026-09-03T00:20:00Z
  checked: @inertiajs/core/dist/index.js getHeaders() (~2979-2986) and process()/handleNonInertiaResponse() (2467-2551); bootstrap/app.php's existing $exceptions->respond() callback; grep of all abort_unless/abort_if(..., 422, ...) call sites across app/Http/Controllers
  found: Inertia's client always sends `Accept: text/html, application/xhtml+xml` (never application/json) on every request, so Laravel's `Request::expectsJson()` is false for all Inertia requests including router.patch(). bootstrap/app.php already special-cases status 403 into a graceful Inertia::render('errors/Forbidden', ...) response inside $exceptions->respond(), but has no equivalent branch for 422 — so a 422 HttpException falls through to Laravel's default HTML debug-page renderer. Inertia's client (handleNonInertiaResponse, since the response lacks an X-Inertia header) then calls `dialog_default.show(response.data)`, injecting that raw HTML into a full-viewport overlay — this is Inertia's own documented dev-mode behavior for non-Inertia error responses, not a bypass of Inertia's XHR client. 22 call sites across 6 controllers (DesignEditorController, JobOrderQueueController, JobOrderWorkspaceController, QueueEntryController, FrontlineStaff/JobOrderController, SessionStatusController) throw 422 this same way.
  implication: Bug 2 is a genuine, pre-existing, systemic gap in the app's exception-to-Inertia-response bridging — reproducible independent of bug 1, confirming the task's own instruction to fix it "on its own merits." The fix should extend the existing $exceptions->respond() precedent (already used for 403) with an equivalent 422 branch, rather than being treated as a downstream symptom of bug 1.
- timestamp: 2026-09-03T01:10:00Z
  checked: Human re-verification in the real dev environment (screenshot). Sidebar nav ("Dashboard", "Performance Report") and breadcrumb both visible and un-crashed after opening job order #1 and mounting the editor via "Start from Blank Canvas" — bugs 1 and 2 hold up. New report: "Blank canva does nothing" — the editor's toolbar/menu chrome rendered but the canvas area beneath it was empty/black, no visible drawable surface.
  implication: Bugs 1 and 2 are fixed. This is a third, distinct, real gap — not a re-occurrence of bugs 1/2 — directly exposed by (though not caused by a mistake in) bug 1's fix. Traced to source: see updated Current Focus above.
- timestamp: 2026-09-03T01:15:00Z
  checked: node_modules/tui-image-editor/dist/tui-image-editor.js — initCanvas() (48219-48229), the ImageLoader.load() early-return branch for falsy imageName+img (~50955-50965, calls setCanvasImage('', null) then returns WITHOUT calling adjustCanvasDimension()), and initLoadImage()'s definition (49947-49957, the only call site of ui.resizeEditor()).
  found: resizeEditor() — the method that actually sizes the `.tui-image-editor-canvas-container` element — is called exactly once in the entire library, from inside initLoadImage()'s promise .then(). initCanvas() only invokes initLoadImage() `if (loadImageInfo.path)`. With an empty-string path (bug 1's fix), this condition is false, so initLoadImage/resizeEditor never run. The container element exists in the DOM (confirms the toolbar/chrome renders) but is never given dimensions, so the canvas beneath the toolbar is present but effectively invisible/unusable.
  implication: Confirms bug 3's root cause precisely. tui-image-editor was designed around "always load some image" (even for a blank start) — there is no library-native "just give me an empty, correctly-sized canvas" mode. The standard workaround for this exact library (documented in multiple upstream GitHub issues for the same "blank canvas" use case) is to synthesize a real blank/white image client-side and pass it as loadImage.path, which correctly drives initLoadImage → resizeEditor and gives the user a properly sized, visible, drawable canvas.

## Resolution
<!-- OVERWRITE as understanding evolves -->

root_cause: |
  BUG 1 (primary): resources/js/components/ToastImageEditor.vue passes
  `loadImage: props.initialImageUrl ? {...} : undefined` into tui-image-editor's
  `includeUI` options. When there's no initial image (blank-canvas flow),
  this sends an EXPLICIT own-enumerable `loadImage: undefined` key. tui-image-editor's
  `_initializeOption()` merges options via a shallow `extend()` (tui-image-editor.js:31663)
  that copies any own-enumerable source key regardless of value, so `undefined`
  clobbers the library's internal default `{ path: '', name: '' }`. `initCanvas()`
  then does `this._getLoadImage().path` (tui-image-editor.js:48222-48224) on that
  now-`undefined` value, throwing a synchronous TypeError inside ToastImageEditor's
  `mounted()` hook. Vue 3.5's scheduler runs mounted hooks as post-flush callbacks
  inside `flushJobs()`'s `finally` block via `flushPostFlushCbs()`
  (@vue/runtime-core/dist/runtime-core.esm-bundler.js:365-436), which has NO
  try/catch around its `cb()` invocation loop (line 389) — the uncaught throw
  escapes `flushJobs`'s `finally` block BEFORE reaching `currentFlushPromise = null`
  (line 432). Since `queueFlush()` (line 320-324) only schedules a new flush when
  `currentFlushPromise` is falsy, and it now stays permanently non-null (stale/rejected),
  EVERY subsequent Vue reactive update application-wide — including Inertia's
  page-swap re-render after a sidebar Link click — is queued but never flushed to
  the DOM. Nav clicks still fire and even complete their network round-trip, but
  the page visually never updates. This is a global, page-wide failure, not scoped
  to the editor component, which is why unrelated nav elements (sidebar Links,
  breadcrumb) stop responding.

  BUG 2 (confirmed independent, NOT downstream of bug 1): every
  `abort_unless(...)`/`abort_if(..., 422, ...)` business-rule-conflict check across
  6 controllers (DesignEditorController, JobOrderQueueController, JobOrderWorkspaceController,
  QueueEntryController, FrontlineStaff/JobOrderController, SessionStatusController —
  22 call sites total) throws a plain Symfony HttpException. Inertia's JS client
  always sends `Accept: text/html, application/xhtml+xml` (never `application/json`)
  on every request (@inertiajs/core/dist/index.js:2983), so Laravel's
  `Request::expectsJson()` returns false for ALL Inertia requests, including
  `router.patch()` calls. bootstrap/app.php already has a `$exceptions->respond()`
  callback that special-cases status 403 into a graceful `Inertia::render('errors/Forbidden', ...)`
  response, but has no equivalent branch for 422 — so any 422 HttpException falls
  through to Laravel's default renderer, which (since `expectsJson()` is false)
  returns the full raw Ignition/Whoops debug page. Inertia's client then sees a
  response with no `X-Inertia` response header and, per its documented behavior
  (@inertiajs/core/dist/index.js:2527-2550, `handleNonInertiaResponse` -> `dialog_default.show`),
  displays that raw HTML in a full-viewport overlay — this reproduces regardless
  of whether bug 1 has occurred first; it is a pre-existing gap in the app's
  exception-to-Inertia-response bridging, exposed here because job order #1 had
  already been advanced past in_consultation by an earlier successful "Start from
  Blank Canvas" click (confirmed live: `php artisan tinker` showed job order #1 at
  status=in_design with empty file_path before this session reset it back to
  in_consultation for a clean repro).
fix: |
  BUG 1: resources/js/components/ToastImageEditor.vue — changed the `loadImage`
  option's "no initial image" branch from `undefined` to `{ path: '', name: '' }`,
  matching tui-image-editor's own internal default exactly, so an empty/falsy
  `path` short-circuits `initCanvas()`'s `if (loadImageInfo.path)` check instead of
  throwing on `undefined.path`.

  BUG 2: bootstrap/app.php — added a second branch to the existing
  `$exceptions->respond()` callback (same pattern already used for 403) handling
  status 422 on genuine Inertia requests: flashes the exception message as an
  `error`-type toast via `Inertia::flash('toast', [...])` (same convention already
  used for success messages in DesignEditorController) and redirects back to the
  page the user was on, instead of falling through to the raw debug-page response.
  Kept scoped to 422 (not broadening to 500/other codes) so real unexpected server
  errors still surface their full trace via Inertia's dev-mode error overlay.
verification: |
  Self-verified via live CDP reproduction against the running dev server (no
  chrome-devtools MCP tools were available in this session; used a locally
  launched headless Chrome driven directly over the DevTools Protocol from a
  scratch Node script, per the debug file's own prior next_action).

  Bug 1: reset job order #1 to in_consultation, logged in as artist@inkspire.test,
  navigated to the Job Order Workspace, clicked "Start from Blank Canvas" —
  zero Runtime.exceptionThrown events (previously threw immediately), editor
  mounted correctly, clicked an editor menu button (crop) with no issue, then
  dispatched a real mouse click on the sidebar "Dashboard" Link — URL changed
  from /artist/job-orders/1 to /artist/dashboard, page title and body content
  updated to the Dashboard page. Nav confirmed fully functional after
  interacting with the editor.

  Bug 2: set job order #1 to in_design (simulating an already-started job
  order), did a fresh full-page load of the workspace (reproducing the stale
  "Start from Blank Canvas" button re-appearing, since `started` is derived
  from design.initialImageUrl not jobOrder.status), clicked it again. Network
  trace showed the final response was `200 .../artist/job-orders/1` with
  `x-inertia: true` (a genuine Inertia-rendered response, not a raw HTML debug
  page) — page stayed on the same job order workspace URL, page HTML contained
  no Whoops/Ignition markers, and the flashed error message "This job order is
  not ready to start a design." appeared in the rendered page (toast).

  Also added a Pest regression test (tests/Feature/Artist/SendForReviewTest.php)
  asserting an X-Inertia-flagged request to design/start on an already-in_design
  job order redirects (not 422) and flashes `toast.type === 'error'` with the
  expected message. Full test suite: 179 passed, 3 pre-existing skips, no
  regressions (confirmed the existing plain-HTTP assertStatus(422) tests for
  the same and other controllers still pass unaffected, since the new
  bootstrap/app.php branch is scoped to requests carrying the X-Inertia header).

  Pending: human confirmation in the real dev environment before archiving.
files_changed:
  - resources/js/components/ToastImageEditor.vue
  - bootstrap/app.php
  - tests/Feature/Artist/SendForReviewTest.php
