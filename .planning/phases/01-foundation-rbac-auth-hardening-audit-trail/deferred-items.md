# Deferred Items — Phase 1

Pre-existing issues discovered during execution that are out of scope for the current task
(not caused by this plan's changes).

## 01-01: Larastan — `UserFactory::withTwoFactor()` missing return statement

- **File:** `database/factories/UserFactory.php:49`
- **Issue:** `public function withTwoFactor(): static {}` has an empty body but declares a `static` return type. Larastan (level 7) flags `return.missing`.
- **Pre-existing:** Yes — this method was already present, unmodified, before Plan 01-01 touched this file (only `definition()` and new role-state methods were added/changed).
- **Status:** Not fixed. Out of scope per plan scope-boundary rules. Revisit when a later plan actually implements two-factor auth factory support (or removes the stub).
