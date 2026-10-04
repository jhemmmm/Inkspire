<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The address a website order was confirmed from.
     *
     * A website order is matched to its customer by mobile number alone, so
     * the customer on file may carry a different email from the one the
     * visitor typed and proved they can read. This visit's emails go to that
     * address; the customer record is left as it was. Null for every visit
     * opened at the counter, which uses the customer's own email.
     */
    public function up(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->string('contact_email')->nullable()->after('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->dropColumn('contact_email');
        });
    }
};
