# Deferred Items — quick task 260910-mbb

Out-of-scope discoveries made while executing this plan. None were fixed;
each is either pre-existing or unrelated to this plan's changes.

## 1. `withSum` silently discards the `get([...])` column list

`Builder::withAggregate()` sets `select('job_orders.*')` when no columns have
been chosen yet, and `get($columns)` only applies its argument when
`$query->columns` is still null. Every query in the codebase that chains
`->withSum(...)->get([...narrow list...])` therefore returns the **whole**
model, not the narrow list.

Observed on `Cashier\DashboardController::index()`: the response carries
`file_path`, `validation_failure_reason`, `consultation_notes`, `accepted_at`
and every pricing column, despite the seven-column list on `get()`. The
Cashier is authorised to see this job order, so this is a tidiness/payload-size
issue rather than a privilege leak — but any future narrow-select written the
same way will be silently inert.

The `'is_rush'` this plan added to that column list is correct-but-inert for
the same reason. It was added anyway so the list stays truthful if the
aggregate is ever removed.

Fix would be `->select([...])->withSum(...)`, which needs a sweep of every
`withSum`/`withMax` call site plus a re-check of each page's prop types.

## 2. `npx vp check` fails on 235 pre-existing files

All of them are Markdown/JSON: `.claude/skills/**`, `.planning/**`,
`CLAUDE.md`, `README.md`, `boost.json`. No file under `app/`, `resources/`,
`routes/`, `database/` or `tests/` is flagged, before or after this plan's
changes. Running `vp check --fix` would rewrite the entire planning archive
and the committed skill files, so it was left alone.

## 3. `composer types:check` (Larastan) is broken

`Undefined constant Larastan\Larastan\LARAVEL_VERSION`. Pre-existing and
environment-level; `npm run types:check` (vue-tsc) was used instead, per the
plan's own instruction.
