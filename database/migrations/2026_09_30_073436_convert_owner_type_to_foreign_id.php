<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maps each legacy `owner_type` enum value to the display name now stored in
     * `owner_types.name`.
     *
     * @var array<string, string>
     */
    private const LEGACY_TYPES = [
        'arb' => 'ARB',
        'arbo' => 'ARBO',
        'none_arb' => 'None ARB',
    ];

    /**
     * Run the migrations.
     *
     * Only the owner types the existing rows actually reference are created
     * here, so an empty database stays empty. The baseline set of owner types is
     * seeded by OwnerTypeSeeder instead.
     */
    public function up(): void
    {
        Schema::table('records_owners', function (Blueprint $table) {
            $table->foreignId('owner_type_id')->nullable();
        });

        $legacyValues = DB::table('records_owners')
            ->whereNotNull('owner_type')
            ->distinct()
            ->pluck('owner_type');

        foreach ($legacyValues as $legacyValue) {
            $name = self::LEGACY_TYPES[$legacyValue] ?? $legacyValue;

            $ownerTypeId = DB::table('owner_types')->where('name', $name)->value('id')
                ?? DB::table('owner_types')->insertGetId([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('records_owners')
                ->where('owner_type', $legacyValue)
                ->update(['owner_type_id' => $ownerTypeId]);
        }

        Schema::table('records_owners', function (Blueprint $table) {
            $table->dropColumn('owner_type');
        });

        Schema::table('records_owners', function (Blueprint $table) {
            $table->foreignId('owner_type_id')->nullable(false)->change();
        });

        Schema::table('records_owners', function (Blueprint $table) {
            $table->foreign('owner_type_id')->references('id')->on('owner_types')->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Owner types added after this migration has run have no legacy enum
     * equivalent, so those rows keep a null `owner_type` instead of failing.
     */
    public function down(): void
    {
        Schema::table('records_owners', function (Blueprint $table) {
            $table->enum('owner_type', array_keys(self::LEGACY_TYPES))->nullable();
        });

        foreach (DB::table('owner_types')->pluck('name', 'id') as $id => $name) {
            if (! in_array($name, self::LEGACY_TYPES, true)) {
                continue;
            }

            DB::table('records_owners')
                ->where('owner_type_id', $id)
                ->update(['owner_type' => array_search($name, self::LEGACY_TYPES, true)]);
        }

        Schema::table('records_owners', function (Blueprint $table) {
            $table->dropForeign(['owner_type_id']);
        });

        Schema::table('records_owners', function (Blueprint $table) {
            $table->dropColumn('owner_type_id');
        });
    }
};
