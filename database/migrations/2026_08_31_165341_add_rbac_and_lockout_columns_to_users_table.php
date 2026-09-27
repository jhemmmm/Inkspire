<?php

use App\Enums\UserRole;
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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default(UserRole::Admin->value)->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->unsignedInteger('failed_login_attempts')->default(0)->after('is_active');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->string('current_session_id')->nullable()->after('locked_until');
            $table->timestamp('last_activity_at')->nullable()->after('current_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'is_active',
                'failed_login_attempts',
                'locked_until',
                'current_session_id',
                'last_activity_at',
            ]);
        });
    }
};
