<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The short public name a customer is sent to — "Artist 1" — rather than
     * the artist's own name.
     *
     * Nullable and unconstrained rather than derived from the row's id: the
     * shop numbers its artists 1..N, that numbering outlives any one account
     * (an artist leaves, the next hire takes the free number), and only
     * artists carry one at all.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('artist_label')->nullable()->after('role');
        });

        $this->backfillExistingArtists();
    }

    /**
     * Number the artists that already exist, oldest account first, so the
     * label is never missing on an established install.
     */
    private function backfillExistingArtists(): void
    {
        DB::table('users')
            ->where('role', UserRole::Artist->value)
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $id, int $index) => DB::table('users')
                ->where('id', $id)
                ->update(['artist_label' => 'Artist '.($index + 1)]));
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('artist_label');
        });
    }
};
