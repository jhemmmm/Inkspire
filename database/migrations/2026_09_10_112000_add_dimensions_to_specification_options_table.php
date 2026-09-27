<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The printed dimensions a print-size option represents, in inches.
     *
     * These are what make a meaningful quality check possible: sharpness is
     * pixels per printed inch, so "is this file good enough?" cannot be
     * answered without knowing how large it will be printed.
     *
     * Nullable, and only meaningful for the `print_size` category. An option
     * with no dimensions recorded skips the effective-DPI check rather than
     * guessing at one.
     */
    public function up(): void
    {
        Schema::table('specification_options', function (Blueprint $table) {
            $table->decimal('width_inches', 8, 2)->nullable()->after('label');
            $table->decimal('height_inches', 8, 2)->nullable()->after('width_inches');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('specification_options', function (Blueprint $table) {
            $table->dropColumn(['width_inches', 'height_inches']);
        });
    }
};
