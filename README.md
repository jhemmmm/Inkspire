# Inkspire

## Dev/Test Accounts

Local dev database only (`database/database.sqlite`) — one account per role, all with password `password`.

| Role | Email | Portal |
|------|-------|--------|
| Owner | owner@inkspire.test | `/owner/dashboard` |
| Admin | admin@inkspire.test | `/owner/dashboard` |
| Frontline Staff | frontline_staff@inkspire.test | `/frontline-staff/dashboard` |
| Artist | artist@inkspire.test | `/artist/dashboard` |
| Cashier | cashier@inkspire.test | `/cashier/dashboard` |
| Production Staff | production_staff@inkspire.test | `/production-staff/dashboard` |
| Accounting Staff | accounting_staff@inkspire.test | `/accounting-staff/dashboard` |

These accounts are seeded ad hoc in the local SQLite DB, not via `DatabaseSeeder` — re-run the same `tinker` snippet (or a future seeder) after `migrate:fresh`.
