<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Delete the now-orphaned Material catalog — Product/Service selection
     * already carries the material, so the separate catalog is retired.
     */
    public function up(): void
    {
        DB::table('specification_options')->where('category', 'material')->delete();
    }

    /**
     * No down path: the deleted labels carry no information to restore.
     */
    public function down(): void {}
};
