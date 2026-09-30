<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Both enum columns are inlined rather than read from a PHP enum: owner types
     * and record types later became user managed rows, so the classes that used
     * to define these values no longer exist. Each column is dropped again by the
     * migration that replaces it with a foreign key.
     */
    public function up(): void
    {
        Schema::create('records_owners', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('owner_name');
            $table->enum('owner_type', ['arb', 'arbo', 'none_arb']);
            $table->enum('record_type', ['case_file', 'profile', 'dar_clearance', 'approval_of_transfer']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('records_owners');
    }
};
