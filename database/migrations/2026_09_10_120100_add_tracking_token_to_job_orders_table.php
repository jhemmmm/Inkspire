<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The unguessable per-job-order token printed on the customer's QR handoff
 * slip, backing the public `GET track/{token}` route (QR-01).
 *
 * Security intent: this token is a BEARER CREDENTIAL. Whoever holds the slip
 * reads whatever the public tracking action returns, so the token must never
 * be selected onto a public surface — the only thing it may ever do is look a
 * job order up.
 *
 * The column stays nullable at the database level rather than being tightened
 * with a later `->change()`: a `change()` on SQLite rebuilds the whole table
 * and can silently drop indexes, and this project runs SQLite in dev against
 * MySQL in production. The uniqueness guarantee is the index added below; the
 * presence guarantee is JobOrder's `creating` hook, which assigns a token to
 * every row a controller, factory or seeder ever creates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->string('tracking_token', 32)->nullable()->after('number');
        });

        // Backfilled per-row rather than in one UPDATE, because each value
        // must be distinct before the unique index below can be added.
        DB::table('job_orders')->whereNull('tracking_token')->pluck('id')
            ->each(fn (int $id) => DB::table('job_orders')
                ->where('id', $id)
                ->update(['tracking_token' => Str::random(32)]));

        Schema::table('job_orders', function (Blueprint $table) {
            $table->unique('tracking_token');
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropUnique(['tracking_token']);
        });

        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn('tracking_token');
        });
    }
};
