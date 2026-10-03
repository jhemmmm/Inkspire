# Quick 261003-voe: Artist New Job Order (Part 2) Summary

Artists can book an emailed client request from a new "New Job Order" page. Orders land in the artist's own O-lane queue when on shift, and the customer gets a JobOrdersReceived mail with one tracking link per job order.

## Commits
- 8962a93: backend (routes, JobOrderIntakeController, StoreJobOrderRequest, JobOrdersReceived mail + view, 11 Pest tests)
- (second commit): NewJobOrder.vue page, artist nav entry, controller typing fix

## Notes
- Existing/new customer modes clear each other and the payload carries only one of customer_id or customer.
- Off-shift artist: ClaimJobOrderForArtist returns false, order stays in Available Jobs; toast says so. Mixed visits name claimed and pooled numbers separately.
- Mail sent after commit in try/catch with report().
- tracking_token appears only in the mail; not in any prop or toast.

## Deviations
- [Rule 1] The shared `contactNumberRules()` unique rule infers its column from the attribute name and fails under `customer.contact_number` (SQL error); the request uses `Rule::unique('customers', 'contact_number')` for that field.
- [Rule 1] Larastan flagged findOrFail typing; switched to `Customer::query()->findOrFail($request->integer('customer_id'))`.

## Verification
- Artist, FrontlineStaff, RoleBoundary tests: 232 passed. Pint clean. `npm run types:check` clean. `npx vp check` clean on touched files. Larastan: 19 errors (baseline), none in touched files.
- The UI was type-checked and linted but NOT driven in a browser by me.
- Wayfinder was regenerated with `--with-form` (without it `.form()` types vanish across the app).

## Self-Check: PASSED

## Orchestrator review and browser verification

Commit 4244b2e (review fixes, found by driving the page rather than by the tests):
- Validation errors named raw keys ("The job_orders.0.description field is required."). `JobOrderValidationRules` now has `jobOrderMessages()` and `jobOrderAttributes()`, used by `StoreQueueEntryRequest`, `AddJobOrderRequest` and the artist's `StoreJobOrderRequest`. A missing customer shows one message ("Pick a customer, or register a new one.") instead of two.
- `JobOrderRowFields` named its Type A/B radio group from the row's random uuid. On a page that renders a row on first load (this one), the server render and the browser disagreed and Vue logged a hydration mismatch. It now uses `useId()`.

Driven in headless Chrome against a seeded SQLite copy, as the demo Artist, at 1366px and 375px, light and dark:
- Sidebar entry opens the page; heading and description present.
- Empty submit: error toast plus plain-language inline errors; nothing saved.
- Customer picked, then changed to a different one; New customer and back again (the unused mode is cleared each way); keyboard-only pick.
- Type B for an existing customer: redirected to the dashboard, toast "JO-… added to your queue.", the order is in My Queue, `assigned` to the artist, visit in the `O` lane.
- New customer with a print-ready PDF: customer created, order `for_production`, unassigned, `O` lane.
- The receipt email was written by the log mailer with a tracking link.
- No console errors, no horizontal overflow, primary button on screen at 375px.

Not driven in the browser: the off-shift path (covered by a feature test).

Affected suites after the fixes: 351 passed. `vue-tsc` clean. Larastan 19, all pre-existing, none in a touched file.
