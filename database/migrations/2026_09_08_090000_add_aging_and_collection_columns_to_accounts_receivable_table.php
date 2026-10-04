<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
    }

    /**
     * Reverse the migrations.
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
