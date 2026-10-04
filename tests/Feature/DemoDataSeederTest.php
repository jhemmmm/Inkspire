<?php

use App\Models\AccountsReceivable;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;

test('a fresh database can seed all demo roles and admin-approved receivables', function () {
    $this->seed(DatabaseSeeder::class);

    $this->seed(DemoDataSeeder::class);

    $admin = User::query()->where('email', 'admin@inkspire.test')->sole();
    $receivable = AccountsReceivable::query()->whereNotNull('written_off_at')->sole();
    expect(User::query()->count())->toBe(6);
    expect($receivable->approved_by)->toBe($admin->id);
});
