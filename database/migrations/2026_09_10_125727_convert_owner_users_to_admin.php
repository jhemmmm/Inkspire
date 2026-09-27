<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Move every Owner onto Admin.
     *
     * Owner and Admin did the same job, so the Owner role has been removed
     * from App\Enums\UserRole. Any row still holding 'owner' would now cast
     * to a value the enum cannot represent, which throws on read -- and the
     * account behind it is somebody's live login, so it has to be moved
     * rather than deleted.
     *
     * Admin inherits everything Owner could do (reports, credit and
     * write-off approval, design unlock), so this is not a demotion.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'owner')->update(['role' => UserRole::Admin->value]);
    }

    /**
     * No down path: 'owner' is no longer a value UserRole can hold, so
     * restoring it would write rows the application cannot read back.
     */
    public function down(): void {}
};
