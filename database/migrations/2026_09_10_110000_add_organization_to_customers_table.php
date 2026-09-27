<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record the company or organization a customer is buying for.
     *
     * Nullable rather than a `customer_type` enum: a walk-in is a person who
     * may or may not be representing an organization, and one optional field
     * covers both without forcing every existing row to pick a side.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('organization')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('organization');
        });
    }
};
