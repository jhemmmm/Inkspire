<?php

use App\Enums\ArtistStatus;
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
            $table->string('artist_status')->default(ArtistStatus::Available->value)->after('last_assigned_at');
            $table->timestamp('break_started_at')->nullable()->after('artist_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['artist_status', 'break_started_at']);
        });
    }
};
