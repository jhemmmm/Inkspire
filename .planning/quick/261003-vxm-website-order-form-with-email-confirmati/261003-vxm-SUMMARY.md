# Quick 261003-vxm: Website order form with email confirmation

Public `/order` form: an order is parked as a prunable `OnlineOrder`, a signed 48-hour link is emailed, and only confirming creates the customer, an O-lane visit and job orders (then sends `JobOrdersReceived`).

## Commits
- 7ab24aa backend (model, migration, factory, request, controller, mail, routes, prune schedule, expired-link branch, 12 tests)
- follow-up fix: Larastan errors in OnlineOrder / controller
- Order.vue, OrderConfirm.vue, customer wording in JobOrderRowFields / JobOrderPriceFields
- Welcome call to action + WelcomePageTest assertion

## Verification
- `php artisan test` on Public, Artist, FrontlineStaff, JobOrder: 354 passed.
- `composer types:check`: 19 errors (baseline), none in touched files. `npm run types:check` clean. Pint and `vp check` clean on touched files.
- Migration NOT run against the dev database (file only). UI type-checked and linted but NOT driven in a browser by me.

## Deviations
- Mail confirm link and the page's confirm POST use separate signed routes (`confirm.show` GET, `confirm` POST, same path), as planned.
- No Toaster exists on layout-less public pages, so errors use a `role="alert"` summary in the sticky footer.

## Known stubs
None.

## Orchestrator review and browser verification

Commit c30cb4f (review of the unauthenticated surface, then a browser pass):
- Item `description` is now the catalog product's name, copied server-side; `pricing_entry_id` is required and must be active. A visitor's own text was otherwise put into an email the shop sends to any address they typed (markdown links included).
- Only a print-ready row keeps its upload; a file on a design request was stored with no format or size check.
- Orders are limited where mail is sent (5 per 10 minutes per connection, `RateLimiter` in `store()`); the route throttle is now only a coarse `30,1`. Failed validation no longer uses up the allowance and no longer ends in a bare 429.
- Error list moved above the form and focused on error (it covered a phone screen inside the sticky footer).
- `@container` moved to `<main>`: "Your details" had container-query classes with no container.
- Field cells use `content-start`, so an input no longer shifts when its neighbour shows an error (also applied to `artist/NewJobOrder.vue`).
- Home page header: links stay on one line; below `sm` the pill is "Order online" and the "Track an order" shortcut is dropped (the tracker is the first panel on a phone).

Driven in headless Chrome against a seeded SQLite copy with the log mailer, logged out, at 1366px and 375px, light and dark:
- Home page: three "Order online" links; header on one line from 375px to 1366px; hero button opens the form; no prices on the form.
- Empty submit: plain-language summary, scrolled into view and focused.
- Type switched both ways; product picked then changed; items added to the cap of five and removed back; file chosen.
- Submit: "Check your email" with the address; "Place another order" returns an empty form.
- The emailed signed link (read from the log) opens the summary; Confirm creates the customer, an `O`-lane visit and both job orders, and shows their tracking links.
- Reopening the link shows the confirmed state and creates nothing; a tampered signature gives HTTP 403 with the expired page.
- The tracking link opens the order; the queue display shows no `O-` ticket.
- No console errors from these pages. (The queue display logs a pre-existing hydration mismatch on its clock; not touched here.)

Suites after the fixes (Public, Artist, FrontlineStaff, JobOrder, RoleBoundary): 368 passed. `vue-tsc` clean. Larastan 19, all pre-existing, none in a touched file.

Not done: `php artisan migrate` on the dev database. The `online_orders` migration exists and must be run before `/order` works there.
