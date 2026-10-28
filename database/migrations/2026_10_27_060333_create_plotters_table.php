<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A plotter is a parcel described by its technical description: the tie
     * points (line, bearing, distance) that the plotter page turns into the
     * shape of the land. Everything is a string because the source is a typed
     * title -- the area carries its own unit and the description is pasted
     * verbatim, punctuation and all.
     */
    public function up(): void
    {
        Schema::create('plotters', function (Blueprint $table) {
            $table->id();
            $table->string('title_number');
            $table->string('lot_number');
            $table->string('survey_number');
            $table->string('area')->nullable();
            $table->string('location')->nullable();
            $table->text('raw_text');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plotters');
    }
};
