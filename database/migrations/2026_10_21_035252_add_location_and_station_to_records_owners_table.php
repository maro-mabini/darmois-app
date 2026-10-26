<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Both are nullable: existing records predate these fields, and a record
     * whose location or station is not known yet is still a usable record.
     */
    public function up(): void
    {
        Schema::table('records_owners', function (Blueprint $table) {
            $table->string('location')->nullable()->after('owner_name');
            $table->string('station')->nullable()->after('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records_owners', function (Blueprint $table) {
            $table->dropColumn(['location', 'station']);
        });
    }
};
