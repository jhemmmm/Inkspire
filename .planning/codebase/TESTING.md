# Testing Patterns

**Analysis Date:** 2026-08-31

## Test Framework

**Runner:**

- Pest PHP 5.1 (`pestphp/pest ^5.1`), with `pestphp/pest-plugin-laravel ^5.0` — see `composer.json`.
- Underlying PHPUnit config: `phpunit.xml`.
- No JavaScript/Vue test runner is installed (no Vitest, Jest, or `@vue/test-utils` in `package.json`). Frontend behavior is covered indirectly through Inertia feature tests on the PHP side (`assertInertia`), not standalone component tests.

**Assertion Library:**

- Pest's `expect()` API plus Laravel's HTTP test assertions (`$response->assertOk()`, `assertRedirect()`, `assertSessionHasErrors()`, etc.) and Inertia testing assertions (`Inertia\Testing\AssertableInertia`).

**Run Commands:**

```bash
php artisan test --compact          # Run all tests (preferred entry point)
php artisan test --compact path/to/Test.php     # Run one file
php artisan test --compact --filter=testName    # Run by name filter
vendor/bin/pest                     # Direct Pest runner (same arguments accepted)
composer test                       # config:clear + lint:check + types:check + full suite (CI-style)
composer ci:check                   # npm run check + types:check + full composer test
```

## Test File Organization

**Location:**

- `tests/Feature/**` — HTTP/Inertia-level tests, one file per feature/flow, mirroring route/controller grouping (`tests/Feature/Settings/ProfileUpdateTest.php`, `tests/Feature/Settings/SecurityTest.php`, `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/Auth/PasswordConfirmationTest.php`, `tests/Feature/Auth/PasswordResetTest.php`, `tests/Feature/DashboardTest.php`).
- `tests/Unit/**` — reserved for framework-independent logic; currently only the generated `tests/Unit/ExampleTest.php` stub exists (no real unit tests yet).
- Per `testing-best-practices` skill (`.claude/skills/testing-best-practices/SKILL.md`): write a feature test first; write a unit test only for logic that doesn't use the framework.

**Naming:**

- Test files: `{Subject}Test.php` (PascalCase, suffixed `Test`), placed in a subfolder matching the controller/feature namespace under `Settings/` or `Auth/`.
- Individual test names: `test('lowercase, human-readable sentence describing behavior', function () { ... });` — no `it()` usage observed, exclusively the `test()` helper.

**Structure:**

```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── AuthenticationTest.php
│   │   ├── PasswordConfirmationTest.php
│   │   └── PasswordResetTest.php
│   ├── Settings/
│   │   ├── ProfileUpdateTest.php
│   │   └── SecurityTest.php
│   ├── DashboardTest.php
│   └── ExampleTest.php
├── Unit/
│   └── ExampleTest.php
├── Pest.php          # global Pest config, expectations, helper functions
└── TestCase.php       # base test case with shared helper methods
```

## Test Structure

**Suite Organization (functional Pest style, no `describe()` blocks observed):**

```php
<?php

use App\Models\User;

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
});
```

(`tests/Feature/Settings/ProfileUpdateTest.php`)

**Patterns:**

- Arrange: build state with `User::factory()->create()` (and factory states, e.g. `User::factory()->withTwoFactor()->create()` in `tests/Feature/Auth/AuthenticationTest.php`).
- Act: chain HTTP test client calls off `$this`, using named routes exclusively — `route('profile.update')`, never raw path strings.
- Assert: chain Laravel response assertions first (`assertOk()`, `assertRedirect()`, `assertSessionHasErrors()`), then use `expect()` for model/state assertions.
- Conditional skip pattern for optional Fortify features: `$this->skipUnlessFortifyHas(Features::twoFactorAuthentication());` defined on `tests/TestCase.php` and used at the top of relevant tests (`tests/Feature/Settings/SecurityTest.php`, `tests/Feature/Auth/AuthenticationTest.php`).
- No explicit `beforeEach`/`afterEach` hooks observed in current tests; setup is inlined per test.
- `Tests\TestCase` is extended for `Feature` tests only via `pest()->extend(TestCase::class)->in('Feature')` in `tests/Pest.php`. `RefreshDatabase` is commented out in `tests/Pest.php` — confirm DB reset strategy before adding DB-dependent tests (see Coverage/Isolation notes below).

## Mocking

**Framework:** Mockery (`mockery/mockery ^1.6`) is installed as a dev dependency but no usage observed in current test files — no mocks/fakes/spies appear in the existing Feature tests.

**Patterns:**

- No mocking pattern established yet in the codebase. Current tests exercise real Eloquent models against the configured test database rather than mocking dependencies.
- Rate limiting is tested by directly manipulating the real `RateLimiter` facade rather than mocking it: `RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);` (`tests/Feature/Auth/AuthenticationTest.php`).
- Fortify feature flags are toggled via `Features::twoFactorAuthentication([...])` and `config(['fortify.features' => []])` rather than mocked (`tests/Feature/Settings/SecurityTest.php`).

**What to Mock:**

- Per `.claude/skills/testing-best-practices/rules/isolation.md` (see Rule Index in the skill) — consult before adding fakes/mocks for outbound HTTP, time, randomness, or databases. Not yet exercised in this codebase's test suite.

**What NOT to Mock:**

- Do not mock Eloquent models or the database in Feature tests — the existing suite always uses real `User::factory()->create()` records against the test database.

## Fixtures and Factories

**Test Data:**

```php
// database/factories/UserFactory.php
public function definition(): array
{
    return [
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => static::$password ??= Hash::make('password'),
        'remember_token' => Str::random(10),
    ];
}

public function unverified(): static
{
    return $this->state(fn (array $attributes) => [
        'email_verified_at' => null,
    ]);
}

public function withTwoFactor(): static {}   // stub, not yet implemented
```

- Default factory password is always the literal string `'password'` (cached via `static::$password`), so tests authenticate with `'password' => 'password'` in request payloads (see `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/Settings/ProfileUpdateTest.php`).
- Factory states used for named scenarios: `->unverified()`, `->withTwoFactor()` (currently an empty stub — calling it has no effect; needs implementation before two-factor factory-based tests can rely on it).

**Location:**

- `database/factories/UserFactory.php`. Follow the pattern of `Factory<Model>` PHPDoc generic and `@return array<string, mixed>` on `definition()` per PHP convention rules in CONVENTIONS.md.

## Coverage

**Requirements:** No coverage threshold enforced in `composer.json`, `phpunit.xml`, or CI config. No `--coverage` flag wired into `composer test`/`composer ci:check`.

**View Coverage:**

```bash
php artisan test --coverage
```

(Requires Xdebug or PCOV; not configured by default in this project.)

## Test Types

**Unit Tests:**

- Scope: framework-independent logic only (per `testing-best-practices` skill). None exist yet beyond the generated `tests/Unit/ExampleTest.php` stub (`expect(true)->toBeTrue()`).

**Integration/Feature Tests:**

- Primary test type in this codebase. Every real test exercises full HTTP request/response cycles through named routes, including auth (`actingAs`), session assertions, and Inertia page/prop assertions.
- Covers: authentication (`tests/Feature/Auth/AuthenticationTest.php`), password confirmation and reset (`PasswordConfirmationTest.php`, `PasswordResetTest.php`), dashboard access control (`tests/Feature/DashboardTest.php`), profile settings CRUD (`tests/Feature/Settings/ProfileUpdateTest.php`), and security/two-factor settings (`tests/Feature/Settings/SecurityTest.php`).

**E2E Tests:** Not used. `pestphp/pest-plugin-browser` is not installed; per the `testing-best-practices` skill, mention it only if the user explicitly asks for real-browser tests.

## Common Patterns

**Inertia Page/Prop Assertions:**

```php
use Inertia\Testing\AssertableInertia as Assert;

$this->actingAs($user)
    ->withSession(['auth.password_confirmed_at' => time()])
    ->get(route('security.edit'))
    ->assertInertia(fn (Assert $page) => $page
        ->component('settings/Security')
        ->where('canManageTwoFactor', true)
        ->where('twoFactorEnabled', false),
    );
```

(`tests/Feature/Settings/SecurityTest.php`) — use `->component()` to assert the rendered Vue page, `->where()` for individual prop values, and `->missing()` to assert a prop was intentionally omitted (e.g. when a feature flag is off).

**Auth/Session State Simulation:**

```php
$this->actingAs($user)
    ->withSession(['auth.password_confirmed_at' => time()])
    ->get(route('security.edit'));
```

Used to bypass password-confirmation middleware in tests that need an already-confirmed session.

**Testing Redirect + No-Error Flows (successful mutation):**

```php
$response
    ->assertSessionHasNoErrors()
    ->assertRedirect(route('profile.edit'));
```

**Testing Validation Failure Flows:**

```php
$response
    ->assertSessionHasErrors('current_password')
    ->assertRedirect(route('security.edit'));
```

**Guest/Unauthenticated Redirect Checks:**

```php
test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});
```

(`tests/Feature/DashboardTest.php`)

**Conditional Feature Skipping:**

```php
test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    // ...
});
```

Defined once in `tests/TestCase.php`, reused across Auth and Settings tests to guard against environments where the Fortify two-factor feature is disabled.

**Async Testing:** Not applicable — no async/queued behavior tested in the current suite (`QUEUE_CONNECTION=sync` in `phpunit.xml` test environment).

**Error Testing:** Handled exclusively through HTTP-level assertions (`assertSessionHasErrors`, `assertTooManyRequests`) rather than catching PHP exceptions directly in tests.

---

_Testing analysis: 2026-08-31_
