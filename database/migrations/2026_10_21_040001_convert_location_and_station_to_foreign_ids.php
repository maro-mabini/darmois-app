<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Free text that records already carry is kept: every distinct value
     * becomes a location or station row before the string column is dropped.
     * Both foreign keys stay nullable because a record whose location is not
     * known yet is still a usable record.
     */
    public function up(): void
    {
        Schema::table('records_owners', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('owner_name');
            $table->foreignId('station_id')->nullable()->after('location_id');
        });

        $this->backfill('location', 'location_id', 'locations');
        $this->backfill('station', 'station_id', 'stations');

        Schema::table('records_owners', function (Blueprint $table) {
            $table->dropColumn(['location', 'station']);
        });

        Schema::table('records_owners', function (Blueprint $table) {
            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnUpdate();
            $table->foreign('station_id')->references('id')->on('stations')->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     *
     * The string columns are restored from the names the ids point at, so a
     * rollback keeps the data readable even though the lists themselves go.
     */
    public function down(): void
    {
        Schema::table('records_owners', function (Blueprint $table) {
            $table->string('location')->nullable();
            $table->string('station')->nullable();
        });

        foreach (['location' => ['location_id', 'locations'], 'station' => ['station_id', 'stations']] as $column => [$foreignKey, $table]) {
            $names = DB::table($table)->pluck('name', 'id');

            foreach ($names as $id => $name) {
                DB::table('records_owners')
                    ->where($foreignKey, $id)
                    ->update([$column => $name]);
            }
        }

        Schema::table('records_owners', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropForeign(['station_id']);
        });

        Schema::table('records_owners', function (Blueprint $table) {
            $table->dropColumn(['location_id', 'station_id']);
        });
    }

    /**
     * Point the records at a row for the free text they already hold.
     */
    private function backfill(string $column, string $foreignKey, string $table): void
    {
        $values = DB::table('records_owners')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->pluck($column);

        foreach ($values as $name) {
            $id = DB::table($table)->where('name', $name)->value('id')
                ?? DB::table($table)->insertGetId([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('records_owners')
                ->where($column, $name)
                ->update([$foreignKey => $id]);
        }
    }
};
