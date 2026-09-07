<?php

use App\Models\SystemConfiguration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backfill is guarded by `whereNull('due_at')`, making a second call to
     * this `up()` a no-op for every row already backfilled or newly
     * approved with `due_at` already set (WR-10's re-runnable shape).
     */
    public function up(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->after('approved_at');
            $table->string('last_reminder_bracket')->nullable()->after('due_at');
            $table->timestamp('last_reminder_sent_at')->nullable()->after('last_reminder_bracket');
            $table->string('collection_status')->default('pending')->after('status');
            $table->text('write_off_reason')->nullable();
            $table->foreignId('write_off_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('write_off_requested_at')->nullable();
        });

        $creditTermDays = SystemConfiguration::getInt('credit_term_days', 30);

        DB::table('accounts_receivable')
            ->where('status', 'active')
            ->whereNull('due_at')
            ->whereNotNull('approved_at')
            ->orderBy('id')
            ->select('id', 'approved_at')
            ->chunkById(100, function ($rows) use ($creditTermDays) {
                foreach ($rows as $row) {
                    DB::table('accounts_receivable')
                        ->where('id', $row->id)
                        ->update(['due_at' => Carbon::parse($row->approved_at)->addDays($creditTermDays)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * Schema-only reversal — the backfilled data is not un-backfilled,
     * matching every other data-migration precedent in this codebase.
     */
    public function down(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->dropConstrainedForeignId('write_off_requested_by');
            $table->dropColumn([
                'due_at',
                'last_reminder_bracket',
                'last_reminder_sent_at',
                'collection_status',
                'write_off_reason',
                'write_off_requested_at',
            ]);
        });
    }
};
