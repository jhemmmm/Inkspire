# Pitfalls Research

**Domain:** Multi-role internal business management system (print shop) — RBAC, POS + third-party payment webhooks, append-only audit trail, file-upload-heavy design workflow
**Researched:** 2026-08-31
**Confidence:** MEDIUM-HIGH (PayMongo specifics and Laravel mechanics verified against official docs and multiple sources; DPI/print-production conventions and some Laravel Cloud specifics are MEDIUM/LOW — flagged inline)

## Critical Pitfalls

### Pitfall 1: Webhook signature verification breaks because the raw body was already touched

**What goes wrong:**
The PayMongo webhook handler verifies signatures against `$request->all()` or a re-encoded JSON payload instead of the exact raw bytes PayMongo sent, so legitimate webhooks are rejected as invalid (or, worse, someone "fixes" this by disabling verification).

**Why it happens:**
PayMongo signs the raw request body (`Paymongo-Signature` header, format `t=<timestamp>,te=<test-mode-signature>,li=<live-mode-signature>`; verification = HMAC-SHA256 of `t + "." + raw_body` compared against `te` in test mode or `li` in live mode). Any re-serialization (parsing to array, re-`json_encode`-ing, trimming whitespace via global middleware) changes the byte sequence and invalidates the comparison. Laravel doesn't mutate JSON bodies by default, but a developer computing the signature from `json_encode($request->all())` instead of `$request->getContent()` will silently break this.

**How to avoid:**
- Compute the HMAC over `$request->getContent()` (raw), never over re-parsed/re-encoded data.
- Use `hash_equals()` for the comparison (timing-safe), not `===`.
- Store **both** test and live webhook secrets and pick based on which signature segment (`te` vs `li`) is present/being verified — don't hardcode one environment's secret.
- Exclude the webhook route from CSRF verification (`VerifyCsrfToken` except-list / `bootstrap/app.php` `validateCsrfTokens(except: [...])`) since PayMongo won't send a CSRF token — a very common Laravel-specific gotcha for any webhook endpoint.
- Exclude the webhook route from session/auth middleware groups; it's an unauthenticated external POST.

**Warning signs:**
- Signature verification passes in local `curl` testing (where you control the exact bytes) but fails for real PayMongo-sent webhooks.
- A "temporary" bypass of signature checking exists anywhere in the codebase to unblock testing and never got removed.

**Phase to address:** Payments/POS integration phase — before any webhook-driven payment status is trusted.

---

### Pitfall 2: Webhook handler is not idempotent, causing duplicate payment application

**What goes wrong:**
PayMongo retries failed/unacknowledged webhook deliveries up to 12 times, and can send the same event more than once even on success (at-least-once delivery is standard for serious payment providers). If the handler blindly applies "mark job order as paid, decrement balance" on every delivery, a customer's payment gets applied twice — inflating revenue reports, double-crediting balances, or double-triggering "Ready for Pickup" notifications.

**Why it happens:**
Developers build the happy path first ("webhook arrives once, we process it once") and don't design for retries, out-of-order delivery (e.g. a retried `payment.paid` arriving after a later `payment.failed` for the same intent), or PayMongo re-sending because your endpoint was slow to acknowledge.

**How to avoid:**
- Store the PayMongo event/resource ID with a **unique DB constraint** (not just an in-memory or cache check) on a `webhook_events` table (or a unique column on `transactions`); wrap the "check-then-insert-then-apply" sequence in a DB transaction so concurrent duplicate deliveries can't both pass the check.
- Return an HTTP status between 200–209 quickly (PayMongo requires this to consider it delivered) — do the actual business-logic application either fast enough to stay well within timeout, or dispatch a queued job and ack the webhook immediately. Don't do slow synchronous work (report recalculation, notification fan-out) inline in the webhook response path.
- Guard transitions with a status check ("only apply `paid` if current payment_status isn't already `paid`/isn't further along"), not just an idempotency key, to also handle out-of-order retries.

**Warning signs:**
- Financial/sales reports show revenue higher than the sum of unique job orders' amounts.
- Customer balance goes negative or a transaction row is duplicated for the same PayMongo payment ID.

**Phase to address:** Payments/POS integration phase, with an explicit test that re-delivers the same webhook payload twice and asserts single application.

---

### Pitfall 3: No real fallback when a webhook never arrives — cashier stuck or forced to "just mark it paid"

**What goes wrong:**
GCash/Maya payments complete on the customer's phone, but the webhook is delayed, lost, or the endpoint was briefly down. Without a real reconciliation path, staff either leave the transaction stuck in "pending" indefinitely, or (worse) a manual override lets the cashier self-report "paid" without any check against PayMongo — an easy fraud/error vector in a small shop with a cash drawer and a Cashier role that isn't the same as Owner.

**Why it happens:**
Webhook-driven flows are usually built assuming the webhook is reliable; the manual reconciliation path gets treated as an afterthought UI button rather than something that calls PayMongo's API to confirm actual status.

**How to avoid:**
- Build the manual reconciliation action to call PayMongo's "retrieve Payment Intent/Source" endpoint and apply *that* server-confirmed status — never let a human directly flip `payment_status` to "paid" for a gateway-routed payment without an API-confirmed check.
- Surface a clear "payment pending — waiting for gateway confirmation" state in the POS UI with an explicit "Check status now" action (calls the reconciliation endpoint on demand), instead of a silent spinner or a state indistinguishable from "not yet paid."
- Log every manual reconciliation action to the audit trail (who triggered it, what PayMongo returned, when) — this is exactly the kind of action the audit trail requirement exists for.

**Warning signs:**
- A code path lets any role directly set `payment_status = 'paid'` for a GCash/Maya transaction without calling PayMongo.
- No test exists that simulates "webhook never arrives" and verifies the reconciliation path still resolves the transaction correctly.

**Phase to address:** Payments/POS integration phase.

---

### Pitfall 4: Audit trail is "append-only" in the UI but not at the data layer

**What goes wrong:**
An Observer or service writes rows to `audit_trail` on `created`/`updated`/`deleted` events, and no controller exposes an edit/delete route for it — so it *looks* immutable. But (a) Eloquent's bulk operations (`Model::where(...)->update([...])`, `Model::query()->delete()`, `DB::table('audit_trail')->update(...)`) bypass individual model events and Observers entirely, and (b) if the application's own database user still has `UPDATE`/`DELETE` grants on the table, "no route calls it" is a UI-layer promise, not a structural guarantee — a bug, a future dev, or direct DB access can silently mutate history.

**Why it happens:**
"Structurally undeletable" is treated as equivalent to "no delete button exists," when the actual requirement (per PROJECT.md: audit trail undeletable by anyone including Owner, no update/delete code paths *at all*) implies defense at the database layer too, not just the application layer.

**How to avoid:**
- Enforce at the DB grant level: if the hosting setup allows a separate, lower-privileged application DB user/connection for the `audit_trail` table (`GRANT SELECT, INSERT` only, no `UPDATE`/`DELETE`), use it. **Verify this is actually possible on Laravel Cloud's managed MySQL before committing to it as the enforcement mechanism** — managed platforms often provision a single full-privilege application user; if a second restricted connection/user isn't available, the fallback is a MySQL trigger on the table that raises an error on `UPDATE`/`DELETE`, since that survives even a compromised or careless application-layer bypass. (LOW confidence on Laravel Cloud's exact multi-user support — verify directly against Laravel Cloud docs/dashboard before the phase that implements this.)
- Never write audited-model mutations via bulk `->update()`/`->delete()` query builder calls — route all mutations that need auditing through Eloquent instance saves (`$model->save()`, `$model->delete()`) so Observers fire, or make the audit-write an explicit, unskippable step in a service class rather than relying solely on Observers.
- Auth events (failed login, lockout, logout) don't originate from an Eloquent model mutation at all — they need an explicit audit-write in Fortify's login/lockout event listeners, not just model Observers. It's easy to build full audit coverage for job orders/transactions and forget this class of event entirely.

**Warning signs:**
- Any `->update([...])` or `->delete()` call in the codebase targets a table that also has an Observer expected to log it (search for chained query-builder mutations on audited models).
- The database user configured in `.env` for the app has `UPDATE`/`DELETE` on `audit_trail` in `SHOW GRANTS`.
- Failed-login/lockout scenarios produce no audit_trail row when manually tested.

**Phase to address:** Should be established in the foundational/RBAC phase (audit trail is cross-cutting and needed from the first mutating feature onward), with the DB-grant question resolved before or during the payments phase since money-related audit entries are the highest-stakes.

---

### Pitfall 5: DPI auto-validation trusts the embedded metadata tag instead of computing effective DPI

**What goes wrong:**
The system reads the DPI value from image metadata (EXIF for JPEG, the `pHYs` chunk for PNG) and compares it against the configurable threshold. But many real-world uploads have this tag missing, wrong, or stuck at a generic value (e.g. 72 or 96 DPI from a screenshot, phone camera default, or an image re-saved by a web tool) even when the actual pixel dimensions are more than large enough to print cleanly at the job's target size — and the inverse: a small image with a fraudulently-set high DPI tag passes validation but would print blurry.

**Why it happens:**
"DPI" is often treated as a single stored property to read off, when it's actually a relationship between pixel dimensions and *intended physical output size* — a value that's meaningless without knowing what size the customer wants printed (a 3000×3000px image is 300 DPI at 10"×10" but only 30 DPI at 100"×100" for a large banner).

**How to avoid:**
- Compute *effective* DPI as `pixel_width / intended_print_width_inches` (and same for height) using the job's specified output dimensions, and only fall back to the embedded metadata tag as a secondary signal, never the primary check.
- Handle vector/PDF uploads (AI, EPS, PDF, PSD) with a separate validation path — these formats don't carry a meaningful raster DPI tag the same way; validate embedded raster resolution within the PDF/PSD or artboard-to-target-size ratio instead, or explicitly route them to Type B (needs consultation) rather than attempting automatic DPI rejection.
- Give a specific, human-readable rejection message ("Detected ~85 DPI at your requested 24"×36" size; we need at least 150 DPI — try a higher-resolution file or a smaller print size") rather than a generic "file rejected," since Frontline staff handling walk-in customers need to explain the rejection on the spot.
- Re-validate (or explicitly preserve/recompute) resolution after a design is edited in TOAST UI Image Editor — canvas export (`canvas.toDataURL()`/similar) commonly resets or strips DPI metadata and can silently downgrade effective resolution on save; the auto-validation done at initial upload doesn't automatically hold true for the post-edit export.

**Warning signs:**
- Legitimate high-resolution customer photos get rejected because their DPI tag reads 72 despite huge pixel dimensions.
- A design passes DPI validation at upload but the Artist's edited/exported final version is visibly lower quality.
- No test covers a PDF or PSD upload going through the same DPI check path as JPEG/PNG.

**Phase to address:** Job Order intake / Type A file upload phase.

---

### Pitfall 6: RBAC enforced only by hiding UI, not by server-side policy checks

**What goes wrong:**
Each of the 7 roles gets its own dedicated portal/layout, and the temptation is to consider "role-gated" done once the sidebar/nav and page components differ per role. But Inertia responses are still normal HTTP endpoints — if a controller/route doesn't independently verify the authenticated user's role (and `is_active` status) server-side, any authenticated user can reach another role's endpoint directly (e.g. a Cashier hitting the Accounting AR write-off route by URL), regardless of what their own portal's UI shows them.

**Why it happens:**
Building 7 separate portal UIs creates a natural (false) sense that access is already segmented, because each role literally can't *see* the other portals' nav links. Frontend route-hiding gets mistaken for authorization.

**How to avoid:**
- Centralize authorization in Laravel Policies/Gates (or role-checking middleware applied per route group) rather than scattering `if ($user->role === 'owner')` conditionals through controllers — with 7 roles and financial/approval-sensitive actions (On-Credit approval, design-lock override, write-offs), a single missed conditional is a real leak, not a cosmetic bug.
- Build an explicit Owner-vs-Admin permission matrix from the manuscript (who approves On-Credit, who can override the design lock, who manages system_configurations, who manages users) and test each boundary — Owner and Admin are the two roles most likely to get permission creep/conflation during implementation since they're "the two people running the shop."
- Combine role middleware with an `is_active` check — a deactivated staff account should be blocked mid-session (not just at next login attempt), which pairs naturally with the "one active session per user" requirement: deactivating a user should also invalidate their existing session server-side, not just prevent future logins.
- Watch data shaping, not just route access: a shared page (e.g. job order detail) rendered for multiple roles must scope which *fields* Inertia sends per role (e.g. cost/profit margin visible to Owner/Accounting but not Frontline), not just which routes are reachable.

**Warning signs:**
- No feature test exists asserting a 403/redirect when a non-Owner role hits an Owner-only route directly.
- Authorization logic is duplicated as inline conditionals across multiple controllers rather than centralized in Policies.
- A deactivated user's existing session keeps working after `is_active` is flipped to false.

**Phase to address:** Foundational RBAC phase, before any role-specific feature work — this is the phase every other phase depends on for correctness.

---

### Pitfall 7: Login lockout becomes a denial-of-service vector against employees

**What goes wrong:**
"Lockout after 5 failed attempts" is implemented as a hard account lock keyed only on username. Since staff usernames in a small shop are often guessable/known internally, a malicious or careless coworker (or an external actor who's learned a username) can intentionally lock out a legitimate employee mid-shift by entering 5 wrong passwords, disrupting operations with no self-service recovery.

**Why it happens:**
"5 failed attempts → lockout" from the manuscript is implemented literally as a full account lock rather than combined with IP-awareness or a decaying/backoff mechanism, because a hard lockout is the simplest interpretation.

**How to avoid:**
- Combine the lockout key with IP where reasonable (already scaffolded in this repo as `username|ip` per `RateLimiter::for('login', ...)`) so an attacker without physical/network access to the shop's location can't trivially lock out a known username from anywhere.
- Ensure lockout events are captured in the audit trail (they're explicitly in scope: "login/logout/failed attempts/lockouts") so the Owner can review whether lockouts look like legitimate mistakes or targeted abuse.
- Decide and document the actual unlock mechanism (time-based expiry vs Admin/Owner-initiated unlock) — this is a real operational question for a small shop that won't have a helpdesk.

**Warning signs:**
- No audit_trail entries are generated for lockout events despite the requirement.
- Lockout has no expiry and no Admin/Owner "unlock this account" action, leaving IT support as the only recovery path (there isn't one — solo developer, no external deadline, per PROJECT.md).

**Phase to address:** Foundational RBAC/auth-hardening phase.

---

### Pitfall 8: Queue number / status transitions race under concurrent staff actions

**What goes wrong:**
Two Frontline staff registering walk-in customers simultaneously (or two Production staff advancing the same job order's status at once) can produce duplicate queue numbers or invalid status transitions (e.g. a job order jumping from "For Production" straight to "Ready for Pickup," skipping "Quality Check," because two rapid client-side submissions raced each other) if the write path isn't guarded server-side.

**Why it happens:**
Single-developer testing rarely surfaces concurrency bugs — they only appear under real multi-staff simultaneous use, which is exactly this system's normal operating condition (a shop counter with multiple staff at once).

**How to avoid:**
- Generate queue numbers inside a DB transaction with row-level locking (`lockForUpdate()`) or an atomic increment, not a "read max, add 1, save" pattern in application code.
- Validate status transitions server-side against the allowed sequence (For Production → Printing → Quality Check → Ready for Pickup) regardless of what the client submits — never trust the client to only offer the "next valid" button; enforce the state machine in the controller/service layer.
- Decide and implement the queue-number reset boundary (daily, per what timezone) explicitly rather than leaving it implicit.

**Warning signs:**
- Two customers with the same queue number on a busy day.
- A job order's status history (if logged) shows a stage was skipped.

**Phase to address:** Queue management phase (queue numbers); Production Monitoring phase (status transitions).

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|-----------------|------------------|
| Checking `$user->role === 'x'` inline in controllers instead of Policies/Gates | Faster to write for the first few routes | Inconsistent enforcement across 7 roles as routes multiply; hard to audit who-can-do-what | Never, past the first 1-2 routes — centralize immediately |
| Trusting embedded EXIF/pHYs DPI tag as the sole resolution check | Simple, fast to implement | Rejects valid high-res images with stripped/generic metadata, accepts fraudulent tags | Only as a fallback signal, never as primary check |
| In-memory/cache-only webhook idempotency check (no unique DB constraint) | Quick to implement, works in dev | Race condition under real concurrent retries → duplicate payment application | Never for money-moving webhooks |
| Audit logging via Observer only, no DB-level grant restriction | Fast, no infra changes needed | "Append-only" is only a promise, not a guarantee, if audit_trail table still has UPDATE/DELETE grants | Acceptable temporarily only if DB-grant enforcement is explicitly tracked as follow-up before production data exists |
| Manual "mark as paid" button for gateway payments without calling PayMongo to confirm | Unblocks a stuck POS transaction quickly during testing | Removes the entire point of webhook-verified payments; opens a fraud/error path in production | Never in production; acceptable only behind a feature flag during dev/testing |

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|-----------------|-------------------|
| PayMongo webhooks | Verifying signature against re-parsed/re-encoded JSON instead of raw bytes | Verify HMAC-SHA256 over `t + "." + raw_request_body` using `hash_equals()`, picking the `te`/`li` secret based on mode |
| PayMongo webhooks | Route left behind Laravel's CSRF/session middleware groups | Exclude the webhook route from CSRF verification and session/auth middleware — it's an unauthenticated external POST |
| PayMongo webhooks | Building against the legacy Sources API for GCash/Maya | PayMongo recommends the Payment Intent workflow for new integrations (Sources is the older/legacy path); build against Payment Intents |
| PayMongo webhooks | Assuming missed events get re-sent automatically | PayMongo does not resend events it considers already delivered/expired after retries exhaust (12 retries, then auto-disable after 3 consecutively-exhausted events) — the manual reconciliation path must call PayMongo's retrieve-status API, not wait passively |
| TOAST UI Image Editor | Assuming the exported/saved image after editing preserves the original file's resolution/DPI metadata | Re-validate or explicitly recompute effective resolution on the post-edit export; don't assume upload-time validation still holds after editing |
| Laravel Cloud (scheduler) | Assuming a `$schedule->command(...)->daily()` entry alone is sufficient for AR aging escalation jobs | Verify Laravel Cloud's scheduler actually invokes `schedule:run` for this app (check Laravel Cloud docs/dashboard) rather than assuming it "just works" like the local dev server |
| Laravel Cloud (managed MySQL) | Assuming a second, restricted-privilege DB user/connection is available for enforcing append-only grants | Verify directly against Laravel Cloud's database provisioning options before relying on DB-level GRANT restriction as the audit-trail enforcement mechanism; have a trigger-based fallback ready |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|-----------------|
| Unbounded `audit_trail` growth with no index strategy | Owner's "view audit trail" report page gets slow to load/filter | Index on `user_id`, `action`, `created_at`; paginate server-side rather than loading full history into the Inertia response | Once the table accumulates months of every mutating action + every auth event across all staff |
| Client-side polling interval too aggressive across many simultaneous staff sessions | Server load climbs with staff headcount even at low real transaction volume | Use a sensible interval (5-10s), scope polling responses to only the data that could have changed (e.g. a lightweight "has anything changed" endpoint or Inertia partial reload), avoid full-page re-fetches | Noticeable once ~6-7 roles' portals are all polling simultaneously during business hours |
| Large print-file uploads routed synchronously through default PHP `upload_max_filesize`/`post_max_size`/`max_execution_time` | Uploads of large banner/tarpaulin design files (100MB+) fail or time out | Raise PHP + web-server limits deliberately (not just PHP's defaults of 2M/8M) and set an appropriate `max_execution_time`; consider whether very large files need a different upload path (chunked or direct-to-storage) rather than a single synchronous request | Any Type A upload of a large-format print file at print resolution, likely from day one given this shop's product mix |

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Allowing file uploads validated only by extension/MIME claim, not actual content | Malicious file (e.g. disguised script, polyglot file, decompression-bomb image) accepted as a "design file" | Validate using Laravel's content-sniffing (`mimes`/`mimetypes` rules use `finfo`, not just extension) and cap maximum pixel dimensions before passing to any image-processing library, to avoid memory-exhaustion DoS |
| Manual override for gateway payment status without calling PayMongo to confirm | Cashier (accidentally or intentionally) marks an unpaid GCash/Maya transaction as paid | Manual reconciliation always calls PayMongo's retrieve-status endpoint and applies the confirmed result — never a free-form status flip |
| Audit trail enforced only by absence of an edit/delete route | History can still be altered via bulk query-builder calls or direct DB access if grants aren't restricted | DB-level grant restriction or triggers, plus a rule that no audited model is ever bulk-updated/deleted via the query builder |
| Login lockout keyed only on username with no IP awareness | Employee usernames known internally enable trivial DoS lockouts against coworkers | Combine username+IP in the rate limiter (already the case in this scaffold's default Fortify config) and log lockouts to the audit trail |
| Treating role-based nav hiding as sufficient authorization | Any authenticated user reaches another role's sensitive route directly by URL | Server-side Policy/Gate check on every route, independent of what each portal's UI shows |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-------------------|
| Silent/indefinite "processing" spinner while waiting on a PayMongo webhook | Cashier and customer don't know if payment succeeded, failed, or is just slow — leads to double-attempts or frustrated customers at the counter | Explicit "payment pending — waiting for gateway confirmation" state with a visible, working "Check status now" manual reconciliation action |
| Generic "file rejected" message for DPI/format failures | Frontline staff can't explain to the customer what's actually wrong or how to fix it | Show the detected value vs. the configured threshold in plain language ("we need at least X DPI at your requested size, this file measures ~Y") |
| Polling-based notifications with too long an interval | "Ready for Pickup" alerts to Frontline feel laggy, undermining trust that the system is "real-time-ish" as intended | Tune polling interval to the shop's actual pace of activity; make staleness visibly bounded (e.g. a subtle "last updated Xs ago" indicator) rather than silent |

## "Looks Done But Isn't" Checklist

- [ ] **Webhook handler:** Often missing raw-body signature verification and idempotency at the DB-constraint level — verify by re-delivering the same test payload twice and confirming single application, and by mutating a byte of the payload and confirming rejection.
- [ ] **Audit trail:** Often missing DB-level grant/trigger enforcement and coverage of non-Eloquent events (failed logins, lockouts) — verify via `SHOW GRANTS` on the app's DB user and by manually triggering a failed-login/lockout scenario and checking for a resulting row.
- [ ] **DPI/file validation:** Often missing effective-DPI computation (vs. relying on the metadata tag alone) and a separate path for vector/PDF formats — verify with a high-pixel-count image that has a stripped/generic DPI tag, and with a PDF/PSD upload.
- [ ] **RBAC:** Often missing server-side enforcement to match frontend nav-hiding, and missing `is_active` mid-session enforcement — verify with a feature test hitting another role's route directly, and by deactivating a user with an active session and confirming they're blocked before their session naturally expires.
- [ ] **Manual payment reconciliation:** Often a free-form "mark as paid" button rather than one that actually calls PayMongo to confirm — verify it fails/refuses when PayMongo reports the payment as unpaid.
- [ ] **Queue numbers / status transitions:** Often correct in single-user manual testing but not concurrency-safe — verify with a test that fires two simultaneous registration/status-update requests and asserts no duplicate/skip occurs.

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|------------------|
| Missed webhook, transaction stuck pending | LOW | Manual reconciliation action calling PayMongo's retrieve-status API resolves it; no data loss if idempotency was already in place |
| Duplicate webhook processed before idempotency was added | HIGH | Add the unique constraint retroactively; write a one-off Artisan command to find and de-duplicate affected transactions, then recompute any dependent report/AR figures |
| Discovered a code path bypassed audit logging for a window of time | MEDIUM | Because the log is append-only by design, don't fabricate retroactive entries — fix the code path immediately and add an explicit "audit gap" note/flag for that window rather than pretending completeness |
| RBAC leak discovered (wrong role reached/saw restricted data) | HIGH | Patch the route/policy immediately; review the audit trail to determine what was actually accessed and by whom; this is exactly the scenario the audit trail exists to make discoverable |
| Duplicate queue numbers or skipped status transition found in production | LOW-MEDIUM | Add the missing DB transaction/locking and server-side state-machine validation; manually correct the specific affected records (queue numbers/status are operational data, not append-only, so direct correction is acceptable) |

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|-------------------|----------------|
| RBAC enforced only in UI, not server-side | Foundational RBAC phase | Feature test asserting 403 for each role hitting another role's route |
| Audit trail not structurally append-only | Foundational/cross-cutting, resolved before payments phase | `SHOW GRANTS` check + test that bulk update/delete attempts on audited tables are either impossible or don't silently skip logging |
| Login lockout usable as DoS against employees | Foundational RBAC/auth-hardening phase | Confirm lockout key includes IP, and lockout events appear in audit trail |
| Webhook signature verification / raw body handling | Payments/POS integration phase | Test with a real (or captured) PayMongo test-mode webhook payload, and a mutated-byte payload expected to fail |
| Webhook idempotency / duplicate processing | Payments/POS integration phase | Re-deliver identical payload twice, assert single transaction effect |
| Missing/delayed webhook with no real reconciliation | Payments/POS integration phase | Simulate no webhook arriving; confirm manual reconciliation calls PayMongo and resolves correctly |
| DPI validation trusting metadata tag alone | Job Order intake / file upload phase | Test with high-res image with stripped DPI tag (should pass) and low-res image with fraudulent high DPI tag (should fail) |
| Queue number / status transition race conditions | Queue management phase (numbers); Production Monitoring phase (transitions) | Concurrent-request test asserting no duplicates/skips |

## Sources

- [PayMongo — Webhook Setup & Management](https://docs.paymongo.com/docs/developer-tools-webhook-setup-management) — MEDIUM/HIGH, official docs
- [PayMongo — Webhooks Resource](https://docs.paymongo.com/reference/webhook-resource) — HIGH, official docs (retry policy: up to 12 retries, auto-disable after 3 consecutive fully-exhausted events, 200-209 ack requirement)
- [PayMongo — Older Workflows / Payment Intent migration guidance](https://developers.paymongo.com/docs/older-workflows) — MEDIUM, official docs (Payment Intent recommended over Sources for GCash/Maya)
- [PayMongo — Payment Reconciliation](https://docs.paymongo.com/docs/payment-acceptance-payment-reconciliation) — MEDIUM, official docs
- [Webhook Idempotency and Deduplication guide](https://www.hooklistener.com/learn/webhook-idempotency-and-deduplication) — MEDIUM, cross-provider webhook idempotency patterns
- [Hookdeck — How to Implement Webhook Idempotency](https://hookdeck.com/webhooks/guides/implement-webhook-idempotency) — MEDIUM
- [DesignGurus — How do you enforce immutability and append-only audit trails?](https://www.designgurus.io/answers/detail/how-do-you-enforce-immutability-and-appendonly-audit-trails) — MEDIUM, generic architecture guidance (DB-role/trigger enforcement pattern)
- [Laravel audit trail via Observers + getDirty/getOriginal](https://blog.shakiltech.com/laravel-audit-trail-building-a-system-that-remembers/) — LOW/MEDIUM, single-author blog, cross-checked against generic immutability guidance above
- [MySQL — Partial/Privilege Revocation reference](https://dev.mysql.com/doc/mysql-security-excerpt/8.0/en/partial-revokes.html) — HIGH, official MySQL docs (mechanism for restricting UPDATE/DELETE grants)
- [DPI Checker tools and print-resolution conventions (Catdi, Templated, ImResizer, etc.)](https://www.catdi.com/tools/dpi-checker) — MEDIUM, converging industry convention (300 DPI standard for close-viewing print, 150 DPI for large-format) across multiple independent tool vendors; the "effective DPI must be computed from pixel dimensions vs. target size, not read from metadata alone" conclusion is standard print-production knowledge, not from a single source
- [Laravel Daily — Adding Gates and Policies for RBAC](https://laraveldaily.com/lesson/roles-permissions/adding-gates-policies) — MEDIUM, established Laravel ecosystem source
- [Laravel — logoutOtherDevices / AuthenticateSession middleware](https://laravel-news.com/logout-other-devices) — MEDIUM, Laravel News (reputable Laravel ecosystem publication)
- [Handling Race Conditions in Laravel: Pessimistic Locking](https://ohansyah.medium.com/handling-race-conditions-in-laravel-pessimistic-locking-d88086433154) — LOW/MEDIUM, single-author blog but describes standard `lockForUpdate()`/DB transaction pattern consistent with Laravel's own documented locking API
- Laravel Cloud database/scheduler specifics (separate DB user privileges, scheduler cron wiring) — LOW confidence, not directly verified against Laravel Cloud's current documentation in this research pass; flagged explicitly above as needing direct verification before relying on them

---
*Pitfalls research for: multi-role print-shop management system (RBAC + POS/webhooks + audit trail + file-upload design workflow)*
*Researched: 2026-08-31*
