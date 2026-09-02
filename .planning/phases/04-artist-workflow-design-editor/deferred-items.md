# Deferred Items — Phase 4

Items discovered during execution that are out of scope for the current task/plan.

## 04-01: Larastan (`composer types:check`) fails to bootstrap in this worktree

**Discovered during:** Plan 04-01, Task 3 verification pass.

**Symptom:**
```
PHP Warning: PHP Startup: Unable to load dynamic library '.../vendor/phpstan/phpstan/turbo-ext/linux-gnu-x86_64/phpstan_turbo-8.4.so'
(/lib/x86_64-linux-gnu/libc.so.6: version `GLIBC_2.33' not found)
...
In LarastanStubFilesExtension.php line 25:
Undefined constant "Larastan\Larastan\LARAVEL_VERSION"
```

**Analysis:** The `phpstan/phpstan` turbo-ext compiled binary requires a newer glibc than this
sandboxed worktree environment provides. Loading `bootstrap/app.php` and bootstrapping the
Kernel in isolation (`php -r '...'`) works correctly and defines `LARAVEL_VERSION` as expected —
this rules out an application-code or route-registration problem. The failure appears to be a
toolchain/environment incompatibility between the installed `phpstan/phpstan` binary and this
container's glibc version, not something introduced by this plan's changes.

**Scope:** Pre-existing environment issue, not caused by Plan 04-01's code changes. The plan's own
`<verification>` block only requires Pest tests + `migrate:fresh`, both of which pass. Not
auto-fixed per the Scope Boundary rule (out-of-scope, environment-level).

**Suggested follow-up:** Verify this same command on a real dev machine / CI runner (outside this
sandboxed worktree) before treating it as a real blocker. If it reproduces there too, investigate
downgrading/reinstalling `phpstan/phpstan` without the turbo-ext, or pin a compatible version.
