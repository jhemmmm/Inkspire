<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When Accounting last emailed the customer about this balance. Separate
     * from the last_reminder_* columns, which drive the automatic reminders
     * sent to staff.
     */
    public function up(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->timestamp('customer_emailed_at')->nullable()->after('last_reminder_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->dropColumn('customer_emailed_at');
        });
    }
};
