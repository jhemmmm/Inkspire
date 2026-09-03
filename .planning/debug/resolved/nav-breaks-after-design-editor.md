---
status: resolved
trigger: "Designing is hard. Clicking 'Dashboard' on the breadcrumb/navigation does not return me to the dashboard. (This looks like bug on the viewing the job order, unable to change navigation, even performance report doesn't work)"
created: 2026-09-02T15:50:00Z
updated: 2026-09-04T01:35:00Z
---

## Human Verification

Confirmed working by the human in their own real browser on 2026-09-04:
"Start from Blank Canvas" mounts a correctly-sized, interactive editor,
and sidebar navigation continues to work afterward. Fix committed at
8ca71ae. This closes the "do NOT move this file to resolved/ until the
human confirms" condition from the prior round.

## Current Focus
<!-- OVERWRITE on each update - always reflects NOW -->

hypothesis: CONFIRMED via live getComputedStyle() ancestor walk (see Evidence 2026-09-03T04:00:00Z). Root cause is NOT a CSS specificity conflict with Tailwind, and NOT the adjustCanvasDimensionBase() cssMaxWidth/cssMaxHeight mechanism (that part works correctly). It's a THIRD, previously-unexamined tui-image-editor option: `includeUI.uiSize`. tui-image-editor's `Ui` class `_makeUiElement()` (tui-image-editor.js:47750-47786) reuses OUR OWN `editorContainer` div directly as `_selectedElement` (adding class `tui-image-editor-container` onto our existing `tui-image-editor-wrapper` div, then replacing its innerHTML) — it is NOT a nested child element. `_setUiSize()` (tui-image-editor.js:47674-47680) then sets `_selectedElement.style.width/height` directly from `options.uiSize`, which defaults to `{width:'100%', height:'100%'}` (tui-image-editor.js:47658-47661) when `includeUI.uiSize` is not passed — and ToastImageEditor.vue never passes it. Our own div's PARENT (CardContent's `.space-y-4` div) has `height:auto` (content-driven, no explicit height), so `height:100%` on our div resolves as CSS spec dictates for a percentage-height box whose containing block has indefinite height: as if 'auto' were specified — collapsing to the library's own hardcoded `min-height:300px` (tui-image-editor.css `.tui-image-editor-container{min-height:300px;height:100%}`) since all of that div's children are `position:absolute` (contribute 0 to auto-height). Meanwhile the CANVAS itself is still correctly, explicitly sized to 900x600 via inline pixel styles (cssMaxWidth/cssMaxHeight -> resizeEditor() -> `.tui-image-editor` element gets explicit `height:600px` inline, confirmed still correct). But that 600px-tall canvas UI lives inside `.tui-image-editor-wrap` (`_editorElementWrap`), which is `position:absolute; top:0; bottom:0` inside `.tui-image-editor-main` (top:64px reserved for header, bottom:0) inside `.tui-image-editor-main-container` (top:0, bottom:64px reserved for the bottom menu bar) inside the 300px-min-height outer container — so `.tui-image-editor-wrap`'s own computed height ends up only 300-64-64=172px, with `overflow:auto`. The 600px-tall canvas UI overflows that 172px box and only the top slice is visible without manually scrolling inside that specific inner div — this is the "sliver" the human sees. The prior round's self-check only measured `.tui-image-editor-canvas-container`/`lower-canvas`/`upper-canvas` (which genuinely ARE 900x600 in their own right, since their own inline `height:100%` resolves against `.tui-image-editor`'s DEFINITE 600px height, a different, closer ancestor) — it never walked further up to `.tui-image-editor-wrap`/`.tui-image-editor-container`, so it never saw the clipping happening one level further out. Not a Tailwind/tui-image-editor.css specificity conflict at all — both stylesheets are behaving exactly as authored; the app simply never told the library how tall to make the outer chrome box.
test: Fix by passing `includeUI.uiSize: { width: '100%', height: '<CSS_MAX_HEIGHT + 128>px' }` explicitly in ToastImageEditor.vue's mount options (128 = tui-image-editor's own hardcoded 64px header + 64px bottom-menu-bar reserved chrome height for `menuBarPosition:'bottom'`, confirmed from tui-image-editor.css `.tui-image-editor-main{top:64px}` and `.tui-image-editor-controls{height:64px}`/`.tui-image-editor-main-container{bottom:64px}`). This gives `_selectedElement` (our own div) a DEFINITE inline pixel height instead of a `100%` that collapses to `min-height:300px`, which should flow a correct, non-clipped 600px down to `.tui-image-editor-wrap`.
expecting: Post-fix getComputedStyle() walk shows `.tui-image-editor-wrapper.tui-image-editor-container` computed height = 728px (not 300px), `.tui-image-editor-wrap` computed height = 600px (not 172px), and a screenshot shows a full, proportioned ~900x600 landscape canvas, not a sliver.
next_action: CONFIRMED — fix implemented and self-verified (see Evidence 2026-09-03T04:00:00Z and updated Resolution). Post-fix getComputedStyle() walk matches every "expecting" value exactly (728px/664px/600px/600px), a viewport screenshot and a full-page screenshot both show a correct, unclipped 900x600 canvas with the complete bottom menu bar visible, the crop/draw/send-for-review regression script passes identically to the bug 3 round, and `npm run types:check`/`npx vp check`/`php artisan test --compact --filter=Artist` all pass clean. Job order #1 has been reset to in_consultation (no designFile/revisionLogs) for the human's own re-test. Session is now awaiting_human_verify — do NOT move this file to resolved/ until the human confirms in their own real browser. If the human reports still-broken sizing, resume investigating (do not re-declare fixed a third time without fresh live evidence).
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
reasoning_checkpoint_bug3:
  hypothesis: "tui-image-editor's initCanvas() only calls ui.resizeEditor() — the sole method that sizes the `.tui-image-editor-canvas-container` element — from inside initLoadImage()'s promise .then() callback, which is itself gated behind `if (loadImageInfo.path)`. Passing an empty-string path (bug 1's fix) keeps this falsy, so the container is never sized for the blank-canvas flow. Passing a real (client-generated, white, correctly-dimensioned) data-URI image instead makes `loadImageInfo.path` truthy, driving the exact same initLoadImage -> resizeEditor path the 'Import Reference Image' flow already exercises successfully."
  confirming_evidence:
    - "Direct source trace (Evidence 2026-09-03T01:15:00Z): resizeEditor() is called exactly once in the whole library, only from initLoadImage()'s .then(), only reached when loadImageInfo.path is truthy."
    - "Live CDP reproduction post-fix: `.tui-image-editor-canvas-container` and its `lower-canvas`/`upper-canvas` children all report width=900, height=600 (matching cssMaxWidth/cssMaxHeight) via getBoundingClientRect()/canvas.width/canvas.height, versus the pre-fix state where the container existed but was never sized."
    - "getImageData() on the lower-canvas center pixel returns [255,255,255,255] (pure white) immediately after mount — a real drawable surface, not empty/transparent/black."
  falsification_test: "If the container's getBoundingClientRect() still reported 0x0 (or the canvas elements' width/height attributes were still 0) after clicking 'Start from Blank Canvas' with the fix applied, the hypothesis would be wrong. If getImageData() returned all-zero/transparent pixels instead of white, the hypothesis would be wrong (would indicate a sized-but-uninitialized canvas, a different failure mode)."
  fix_rationale: "Synthesizes a real image (matching the library's own expectation that loadImage.path always points to actual image data) instead of adding a manual resizeEditor()/manual container-sizing workaround that would fight the library's internal state machine (this.options.originalCanvasSize, previous initialImage bookkeeping used by other library methods like undo/redo baseline) and risk diverging from the already-working, already-tested 'Import Reference Image' code path."
  blind_spots: "Have not verified behavior across every browser engine (only tested in headless Chromium) — canvas.toDataURL('image/png') is broadly supported and used elsewhere in this same codebase's Import Reference Image flow (URL.createObjectURL) so this is a low-risk assumption. Have not tested extremely small viewport / mobile widths where cssMaxWidth/cssMaxHeight scaling behavior might differ, though this mirrors the exact dimensions already used successfully by the non-blank flow."
reasoning_checkpoint_bug4:
  hypothesis: "tui-image-editor's Ui class reuses our own editorContainer div directly as its top-level `.tui-image-editor-container` element (no nested wrapper is created) and sizes it via `_setUiSize()` from `options.uiSize`, which defaults to `{width:'100%',height:'100%'}` when `includeUI.uiSize` is not passed. Because our div's own parent (CardContent's space-y-4 div) has no explicit height (height:auto, content-driven), the 100% resolves to nothing and the library's own CSS fallback `min-height:300px` becomes the actual rendered height. The inner canvas UI (`.tui-image-editor`) is still correctly, explicitly sized to 900x600 via a completely separate mechanism (cssMaxWidth/cssMaxHeight -> resizeEditor()), so it overflows the ~172px-tall `.tui-image-editor-wrap` box that results from a 300px outer container minus 128px of hardcoded header+bottom-menu-bar chrome, and gets scroll-clipped (overflow:auto) to a thin visible top slice — the 'sliver' the human sees."
  confirming_evidence:
    - "Live getComputedStyle() ancestor walk on the real /artist/job-orders/1 route (Evidence 2026-09-03T04:00:00Z) shows `.tui-image-editor-wrapper.tui-image-editor-container` (our own div, confirmed by className) at computed height 300px with computed minHeight 300px and inline style height:100% — i.e. the percentage resolved to nothing and min-height is what's actually pinning it."
    - "Direct source trace: tui-image-editor.js:47750-47764 (_makeUiElement) shows `selectedElement = element` (the element passed into `new ImageEditor(...)`, i.e. our own div) gets `.tui-image-editor-container` added to its existing classList and its innerHTML replaced — it is not a new nested element."
    - "Direct source trace: tui-image-editor.js:47658-47661 (_initializeOption default) confirms `uiSize: {width:'100%',height:'100%'}` is the default when includeUI.uiSize is not supplied; tui-image-editor.js:47674-47680 (_setUiSize) confirms this is applied as `_selectedElement.style.width/height` directly."
    - "Live evidence shows the canvas-container/canvas elements (a nearer ancestor pair with their own, different, definite 600px containing block from `.tui-image-editor`'s explicit inline height) are correctly 900x600 — explaining why the PRIOR round's narrower check (which stopped at that level) passed while a human still saw a visibly broken result one level further up the DOM."
  falsification_test: "After passing includeUI.uiSize with an explicit pixel height, a live getComputedStyle() walk must show `.tui-image-editor-wrapper.tui-image-editor-container` computed height matching the new explicit value (not 300px/min-height), and `.tui-image-editor-wrap` computed height must equal 600px (not 172px) with no visual clipping in a screenshot. If the outer container's computed height still shows 300px/min-height after the fix, the hypothesis is wrong."
  fix_rationale: "Passing includeUI.uiSize explicitly uses the exact configuration knob tui-image-editor's own code exposes for this exact purpose (sizing the outer chrome box, a distinct concern from cssMaxWidth/cssMaxHeight which only caps the inner canvas) — this is the root-cause fix, not a workaround. Only uiSize.height needs changing (uiSize.width stays '100%', since width already renders correctly at the full available card width and changing it would risk narrowing the editor unnecessarily on wide layouts or overflowing on narrow ones — that dimension isn't broken)."
  blind_spots: "The 128px (64+64) header+bottom-menu chrome allowance is read directly from tui-image-editor's own pinned dist CSS for menuBarPosition:'bottom' (the only menu position this app uses) — if that library version's CSS changes in a future dependency bump, this constant would need re-deriving. Have not tested behavior at very narrow viewport widths (mobile) where cssMaxWidth's own width-vs-height scale-factor logic could interact with a fixed uiSize.height in ways not exercised by this specific 900x600 blank-canvas case."

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
- timestamp: 2026-09-03T02:00:00Z
  checked: Implemented the fix in resources/js/components/ToastImageEditor.vue — added a `createBlankImageDataUrl(width, height)` helper (offscreen `<canvas>`, filled `#ffffff`, `.toDataURL('image/png')`) and changed the no-initial-image `loadImage` branch to `{ path: createBlankImageDataUrl(CSS_MAX_WIDTH, CSS_MAX_HEIGHT), name: 'blank' }` instead of `{ path: '', name: '' }`. Extracted `cssMaxWidth`/`cssMaxHeight` (900/600) into named `CSS_MAX_WIDTH`/`CSS_MAX_HEIGHT` constants shared by both the data-URI generation and the `includeUI` options so they can never drift out of sync. `npm run types:check` (vue-tsc --noEmit) passed clean; `npx vp check resources/js/components/ToastImageEditor.vue` (format+lint) passed clean.
  implication: Fix applied per the plan already recorded in Current Focus; ready for live verification.
- timestamp: 2026-09-03T02:30:00Z
  checked: Live CDP reproduction against the running dev server (chrome-devtools MCP remote endpoint still unreachable this session — used a locally launched headless Chrome, `google-chrome --headless=new --remote-debugging-port=9333`, driven directly over the DevTools Protocol from a scratch Node script using the platform's native `WebSocket`/`fetch`, same technique as the prior round). Reset job order #1 to `in_consultation` with its `designFile`/`revisionLogs` relations cleared, logged in as artist@inkspire.test, did a fresh full-page load of the Job Order Workspace, clicked "Start from Blank Canvas".
  found: Zero `Runtime.exceptionThrown` events on mount (same as bug 1's fix). `.tui-image-editor-canvas-container`'s `getBoundingClientRect()` returned `{ width: 900, height: 600 }` (previously would have been 0x0 pre-fix, matching the root-cause trace). Both the `lower-canvas` and `upper-canvas` child `<canvas>` elements report `width=900 height=600` (real bitmap resolution) with `cssWidth/cssHeight: 100%/100%`, confirming `resizeEditor()` ran. `getImageData()` sampled at the canvas center returned `[255, 255, 255, 255]` — genuine opaque white, not black/empty/transparent.
  implication: The core bug-3 symptom ("blank canva does nothing" / invisible canvas) is fixed — the container and both canvas layers are now correctly sized and the surface is visibly white and ready to draw on, exactly matching the fix's prediction.
- timestamp: 2026-09-03T02:32:00Z
  checked: Menu-tool smoke test in the same live session — clicked `.tie-btn-crop` (Crop) and confirmed `.tui-image-editor-submenu` appeared with `.tie-crop-preset-button` elements present; clicked `.tie-btn-draw` (Draw) and confirmed the submenu appeared with Free/Straight/Color/Range controls, already in active "Free" draw mode (tui-image-editor's own `Draw.changeStartMode()` auto-activates free-draw the moment the submenu opens — confirmed via source read after an initial false negative where clicking ".free" a second time was toggling draw mode back OFF, not on). Dispatched a real `mousedown`-on-canvas / `mousemove`+`mouseup`-on-`document` sequence (matching fabric.js's own internal listener re-binding behavior traced in `node_modules/fabric/dist/fabric.js`'s `Canvas._onMouseDown`) to simulate an actual drag-stroke.
  found: Crop submenu with preset buttons rendered correctly. Draw submenu rendered correctly and was already in active draw mode. After the simulated drag, a full-canvas pixel scan found 1172 non-white pixels forming a stroke (first non-white pixel at (408,293), color `[223,244,255,255]`, a light-blue antialiased edge matching the submenu's default blue color swatch), and the exact drag-endpoint center pixel read `[76,195,255,255]` (solid stroke color) — confirming a real stroke was drawn onto the canvas bitmap, not just a UI-only interaction.
  implication: Existing menu tools (crop, draw) still function correctly against the newly-fixed, properly-sized canvas — no regression from the blank-image-data-URI change. (The one investigative wrong turn — redundantly re-clicking "Free" and toggling draw mode off — was self-corrected via source trace before drawing the final conclusion; documented here per the eliminated-hypothesis discipline even though it wasn't a formal Eliminated-section hypothesis about the bug itself, just about test methodology.)
- timestamp: 2026-09-03T02:33:00Z
  checked: Full "Send for Review" flow in the same live session — clicked `[data-test="send-for-review-button"]`, which calls `editorRef.value.exportPng()` (returns a base64 PNG data URI), converts it to a `Blob`/`File`, and POSTs it via Inertia's form helper to `artist/job-orders/1/design/send-for-review`.
  found: Network trace captured a `POST http://localhost:8000/artist/job-orders/1/design/send-for-review` request with `hasPostData: true` (the multipart file upload). Post-submit page state showed the flashed toast "Sent for review. Waiting on the client's verdict.", the Design card switched to "Waiting on the client's verdict.", and a new revision log row appeared ("9/3/2026, 2:32:49 AM — Pending") in the Review card's history — all consistent with a genuinely successful, server-accepted upload (a corrupt/invalid PNG or empty canvas export would not have passed the controller's file validation and produced this response).
  implication: `exportPng()` still produces a valid flattened PNG accepted by the send-for-review endpoint — no regression in the export path from the blank-canvas fix. All four items from the task's live-verification checklist are confirmed.
- timestamp: 2026-09-03T03:00:00Z
  checked: Human re-verification in the real dev environment (screenshot) of the bug-3 fix on the live /artist/job-orders/1 page.
  found: The canvas IS now visible and white (bug 3's core symptom is genuinely fixed — confirms the loadImage-data-URI approach was directionally correct). But the drawable canvas area renders as a thin horizontal sliver — visually near-full-width but only roughly 20-30px tall — not a proper ~900x600 (or even reasonably-proportioned) landscape rectangle. The outer editor chrome box (toolbar top to bottom menu icons) also looks visually shorter than the configured 600px. Human quote: "the design height is little. How are we supposed to edit on it?"
  implication: A real, new discrepancy between the prior round's self-verification (which claimed `.tui-image-editor-canvas-container`/`lower-canvas`/`upper-canvas` all measured 900x600 via getBoundingClientRect()) and what a human actually sees on the same route. Either that check measured the wrong thing (e.g. inline style/backstore pixel attributes rather than true rendered CSS box size), ran against a different DOM/CSS context than the real page, or there's viewport/timing sensitivity the scripted repro didn't hit. This needs a fresh live check on the exact real route with getComputedStyle() on the full ancestor chain plus an actual screenshot — not just element measurements — before declaring fixed again.

- timestamp: 2026-09-03T04:00:00Z
  checked: Live CDP reproduction against the real running dev server (local headless Chrome, google-chrome --headless=new --remote-debugging-port=9333, driven via the same scratch Node/WebSocket CDP script technique as prior rounds — chrome-devtools MCP tools not available this session, remote endpoint 192.168.250.102:9222 unreachable). Reset job order #1 to in_consultation with designFile/revisionLogs cleared, fresh full-page load of http://localhost:8000/artist/job-orders/1, clicked "Start from Blank Canvas". Walked getComputedStyle() + getBoundingClientRect() on EVERY ancestor from the `lower-canvas` element up through `<body>` (not just the canvas/canvas-container measured last round), and captured a screenshot.
  found: Zero Runtime.exceptionThrown events (bugs 1/3 hold). `lower-canvas`/`upper-canvas`/`.tui-image-editor-canvas-container` (depth 0-1) all correctly compute to 900x600 (matches prior round's check — that part was never wrong). `.tui-image-editor` (depth 2, `_editorElement`) correctly has inline `height:600px;width:900px` (resizeEditor() ran correctly). BUT walking further up: `.tui-image-editor-wrap` (depth 5, `_editorElementWrap`, position:absolute, overflow:auto) computed height = only 172px. `.tui-image-editor-main` (depth 6) = 172px. `.tui-image-editor-main-container` (depth 7) = 236px. `.tui-image-editor-wrapper.tui-image-editor-container` (depth 8 — OUR OWN Vue-rendered div, confirmed by className containing both `tui-image-editor-wrapper` AND `tui-image-editor-container` on the SAME element) computed height = exactly 300px, with computed minHeight = 300px and inline style `height:100%` — i.e. the 100% resolved to nothing and the library's own CSS `min-height:300px` (tui-image-editor.css) is what's actually pinning it. Its parent, our CardContent `.space-y-4` div (depth 9), has `height:auto` (336px, purely content-driven, no explicit height set anywhere in our Vue code or Tailwind classes on that element).
  implication: Confirms the root cause precisely: with the outer container at 300px, minus 64px header minus 64px bottom-menu-bar (both hardcoded in tui-image-editor.css), only 172px remains for `.tui-image-editor-wrap`, into which the library still tries to fit its correctly-600px-tall `.tui-image-editor` box — overflowing and getting scroll-clipped (overflow:auto) to the top 172px. The mechanism is `includeUI.uiSize` (a THIRD, previously-unexamined config option distinct from `cssMaxWidth`/`cssMaxHeight`) defaulting to `{width:'100%',height:'100%'}` (tui-image-editor.js:47658-47661) when not passed by us — and our own wrapper div's parent has no explicit height, so that 100% collapses per CSS spec to the library's own `min-height:300px` fallback. Screenshot (bug4-before.png) visually confirms a squished/cropped canvas area consistent with a ~172px-tall visible window into a 600px-tall canvas. Not a Tailwind/tui-image-editor.css specificity conflict — both hypothesis (a) and (b) from the prior round's Current Focus are ELIMINATED; this is option (c), a third mechanism neither had considered.

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

  BUG 3 (confirmed independent, exposed by bug 1's fix but not caused by a
  mistake in it): tui-image-editor's `initCanvas()` (tui-image-editor.js:48219)
  only calls `ui.resizeEditor()` — the sole method in the library that sizes
  the `.tui-image-editor-canvas-container` element — from inside
  `initLoadImage()`'s promise `.then()` callback (tui-image-editor.js:49947),
  itself gated behind `if (loadImageInfo.path)` (tui-image-editor.js:48224).
  Bug 1's fix correctly changed the "no image" value from `undefined` (crash)
  to `{ path: '', name: '' }` (no crash), but an empty string `path` is still
  falsy, so `initLoadImage`/`resizeEditor` never ran for the blank-canvas
  case — the editor's toolbar/menu chrome rendered but the canvas container
  itself was never sized, leaving it present in the DOM but invisible/unusable
  ("Blank canva does nothing"). tui-image-editor has no library-native
  "blank canvas, no image" mode; it always expects a real image (even if
  blank/white) to load and size against.
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

  BUG 3: resources/js/components/ToastImageEditor.vue — added a
  `createBlankImageDataUrl(width, height)` helper that draws a filled-white
  offscreen `<canvas>` and exports it via `.toDataURL('image/png')`. The
  no-initial-image `loadImage` branch now passes
  `{ path: createBlankImageDataUrl(CSS_MAX_WIDTH, CSS_MAX_HEIGHT), name: 'blank' }`
  instead of `{ path: '', name: '' }`. `CSS_MAX_WIDTH`/`CSS_MAX_HEIGHT` (900/600)
  were extracted into named constants shared by both the data-URI generation
  and the `includeUI.cssMaxWidth`/`cssMaxHeight` options so the synthesized
  blank image always matches the editor's configured display size. This makes
  `loadImageInfo.path` truthy, driving `initLoadImage()` -> `resizeEditor()`
  exactly like the already-working "Import Reference Image" path.

  BUG 4: resources/js/components/ToastImageEditor.vue — added an explicit
  `includeUI.uiSize: { width: '100%', height: '\${CSS_MAX_HEIGHT + EDITOR_CHROME_HEIGHT}px' }`
  option (previously unset, silently defaulting to tui-image-editor's own
  `{ width: '100%', height: '100%' }`, which collapsed to its `min-height:
  300px` CSS fallback since our wrapper's parent has no explicit height).
  `EDITOR_CHROME_HEIGHT` (128) is a new named constant documented with its
  derivation: tui-image-editor's own hardcoded 64px header + 64px bottom
  menu bar (confirmed from its own dist CSS, `.tui-image-editor-main {
  top: 64px }` / `.tui-image-editor-controls { height: 64px }` /
  `.tui-image-editor-main-container { bottom: 64px }`), which must be
  reserved on top of the canvas's own `CSS_MAX_HEIGHT` so the outer chrome
  box is tall enough to not clip the inner canvas. `uiSize.width` was left
  at `'100%'` (unchanged/not part of the bug — width already rendered
  correctly at the available card width).
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

  BUG 3: reset job order #1 to in_consultation with its designFile/revisionLogs
  relations cleared, live CDP repro against the running dev server (local
  headless Chrome driven directly over CDP, no MCP tools available this
  session either). Clicked "Start from Blank Canvas" — zero
  Runtime.exceptionThrown events; `.tui-image-editor-canvas-container`
  and both its `lower-canvas`/`upper-canvas` children reported
  `width=900 height=600` (matching cssMaxWidth/cssMaxHeight, previously
  0x0); `getImageData()` at the canvas center returned `[255,255,255,255]`
  (genuine opaque white). Exercised Crop (submenu + presets render) and
  Draw (submenu renders, active free-draw mode by default, a real
  simulated pointer drag-stroke dispatched via native `mousedown`
  on-canvas/`mousemove`+`mouseup`-on-`document` — matching fabric.js's own
  internal listener rebinding — produced 1172 non-white pixels on the
  canvas bitmap, confirming an actual stroke was drawn, not just a UI
  state change). Ran the full "Send for Review" flow — `exportPng()` ->
  file upload POST to `design/send-for-review` succeeded (200, flashed
  "Sent for review" toast, new revision log row appeared) — confirming
  the export path still produces a valid, server-accepted flattened PNG.

  `npm run types:check` (vue-tsc) passed. `npx vp check` (format+lint)
  passed clean on the modified file (a pre-existing, unrelated failure in
  the repo-wide `npm run check` comes from an untracked `demo/index.html`
  file, not from this change). `php artisan test --compact --filter=Artist`
  passed 57/57 both before and after the bug 3 fix — no regressions.

  BUG 4: reset job order #1 to in_consultation with designFile/revisionLogs
  cleared, live CDP repro (local headless Chrome over CDP, same technique).
  Clicked "Start from Blank Canvas" — zero Runtime.exceptionThrown events.
  Walked getComputedStyle() on the FULL ancestor chain from `lower-canvas`
  up through `<body>` (not just the canvas/canvas-container, per this
  round's explicit instruction not to repeat the prior round's narrower
  check): `.tui-image-editor-wrapper.tui-image-editor-container` (our own
  div) now computes to height 728px (previously 300px, matching the new
  explicit inline style); `.tui-image-editor-main-container` computes to
  664px (previously 236px); `.tui-image-editor-main` computes to 600px
  (previously 172px); `.tui-image-editor-wrap` computes to 600px
  (previously 172px) — now exactly matching the canvas's own 600px height,
  with no more overflow/clipping. Took both a viewport screenshot
  (bug4-after.png) and a full-page screenshot capturing the complete
  editor (bug4-full.png) — both show a correctly proportioned, full
  900x600 white canvas with the toolbar above and the complete bottom
  menu bar (crop/flip/rotate/draw/shape/icon/text/filter icons) fully
  visible below it, visually distinct from the pre-fix screenshot
  (bug4-before.png), which showed the same canvas area compressed into a
  short strip with the bottom menu bar overlapping close beneath it.

  Re-ran the full tool-interaction + Send for Review regression script
  used in the bug 3 round (crop submenu + presets, draw submenu + a real
  simulated pointer drag-stroke producing 1172 non-white pixels on the
  canvas bitmap, `exportPng()` -> `design/send-for-review` POST succeeding
  with a flashed "Sent for review" toast and new revision log row) — all
  passed identically to the bug 3 round, confirming no regression from the
  uiSize change. Zero exceptions throughout.

  `npm run types:check` (vue-tsc) passed clean. `npx vp check` (format +
  lint) passed clean on the modified file. `php artisan test --compact
  --filter=Artist` passed 57/57 (same as bug 3 round — this fix is
  frontend-only, no PHP changed).

  Pending: human confirmation in the real dev environment before archiving
  (this is the third such pending confirmation in this session — bugs 1/2
  were already confirmed once by the human; bugs 3 and 4 have not yet been
  shown to the human).

  BUG 4 (confirmed independent, exposed by bug 3's fix but not caused by a
  mistake in it): tui-image-editor's `Ui` class reuses the element passed
  into `new ImageEditor()` directly as its own top-level
  `.tui-image-editor-container` chrome box (tui-image-editor.js:47750-47786,
  `_makeUiElement()` — `selectedElement = element`, our own
  `editorContainer` div, gets `.tui-image-editor-container` added to its
  existing classList and its innerHTML replaced; it is not a new nested
  element). That box's own size comes from a THIRD, previously-unexamined
  config option, `includeUI.uiSize` (distinct from `cssMaxWidth`/
  `cssMaxHeight`, which only cap the inner canvas), applied via
  `_setUiSize()` (tui-image-editor.js:47674-47680,
  `_selectedElement.style.width/height = uiSize.width/height`) and
  defaulting to `{ width: '100%', height: '100%' }`
  (tui-image-editor.js:47658-47661) when not supplied. ToastImageEditor.vue
  never passed `uiSize`. Our own wrapper div's parent (CardContent's
  `space-y-4` div) has no explicit height (`height:auto`, content-driven),
  so the unset `height:100%` resolves per CSS spec as if `auto` were
  specified — collapsing to tui-image-editor's own CSS fallback
  `min-height:300px` (all of that box's children are `position:absolute`
  and contribute nothing to auto-height). The canvas itself was still
  correctly, explicitly sized to 900x600 via the separate cssMaxWidth/
  cssMaxHeight -> resizeEditor() mechanism (bug 3's fix), but that 600px
  UI now lives inside a chain of `position:absolute` ancestors
  (`.tui-image-editor-main-container`, `.tui-image-editor-main`,
  `.tui-image-editor-wrap`) whose heights are all derived by subtracting
  tui-image-editor's own hardcoded 64px header + 64px bottom-menu-bar
  chrome from that undersized 300px outer box — leaving only ~172px for
  `.tui-image-editor-wrap` (which has `overflow:auto`), so the 600px
  canvas UI overflowed and was scroll-clipped to a thin top slice — the
  reported "sliver". The prior round's self-check only measured
  `.tui-image-editor-canvas-container`/`lower-canvas`/`upper-canvas`
  (correctly 900x600 via their own, nearer, definite-height ancestor,
  `.tui-image-editor`, which is unaffected by this bug) and never walked
  further up to `.tui-image-editor-wrap`/`.tui-image-editor-container`,
  so it never observed the clipping happening one level further out. Not
  a Tailwind/tui-image-editor.css specificity conflict — both of the
  prior round's leading suspects (a and b) are eliminated; this is a
  third, previously un-considered mechanism.
files_changed:
  - resources/js/components/ToastImageEditor.vue
  - bootstrap/app.php
  - tests/Feature/Artist/SendForReviewTest.php
