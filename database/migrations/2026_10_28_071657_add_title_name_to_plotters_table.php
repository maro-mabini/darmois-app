<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The name the title is registered under is written separately from the
     * title number: one certificate can carry several names, and the name is
     * what the owner recognises in the list. Older plotters were saved before
     * the field existed, so it is nullable rather than required.
     */
    public function up(): void
    {
        Schema::table('plotters', function (Blueprint $table) {
            $table->string('title_name')->nullable()->after('survey_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plotters', function (Blueprint $table) {
            $table->dropColumn('title_name');
        });
    }
};
