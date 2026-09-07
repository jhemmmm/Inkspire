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

        $this->backfillDueDates();
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

    /**
     * Stamp `due_at` on every Active row Phase 5 already approved before
     * this migration existed, computed as `approved_at` + `credit_term_days`
     * (D-02).
     *
     * A private helper on the migration's anonymous class, following the
     * WR-10 shape (`2026_09_05_120000_add_number_and_due_at_to_job_orders_table.php`):
     * it takes its own transaction and is re-runnable on its own, since the
     * schema-altering `up()` above is not re-invocable once already
     * migrated. `whereNull('due_at')` is what makes a re-run a no-op — a
     * row already backfilled, or newly approved with `due_at` already set,
     * is never touched again.
     */
    private function backfillDueDates(): void
    {
        $creditTermDays = SystemConfiguration::getInt('credit_term_days', 30);

        DB::transaction(function () use ($creditTermDays): void {
            DB::table('accounts_receivable')
                ->where('status', 'active')
                ->whereNull('due_at')
                ->whereNotNull('approved_at')
                ->orderBy('id')
                ->select('id', 'approved_at')
                ->chunkById(100, function ($rows) use ($creditTermDays): void {
                    foreach ($rows as $row) {
                        DB::table('accounts_receivable')
                            ->where('id', $row->id)
                            ->update(['due_at' => Carbon::parse($row->approved_at)->addDays($creditTermDays)]);
                    }
                });
        });
    }
};
