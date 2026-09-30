<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Maps each legacy `record_type` enum value to the display name now stored
     * in `record_types.name`.
     *
     * @var array<string, string>
     */
    private const LEGACY_TYPES = [
        'case_file' => 'Case File',
        'profile' => 'Profile',
        'dar_clearance' => 'DAR Clearance',
        'approval_of_transfer' => 'Approval of Transfer',
    ];

    /**
     * Run the migrations.
     *
     * Only the record types the existing rows actually reference are created
     * here, so an empty database stays empty. The baseline set of record types
     * is seeded by RecordTypeSeeder instead.
     */
    public function up(): void
    {
        Schema::table('records_owners', function (Blueprint $table) {
            $table->foreignId('record_type_id')->nullable();
        });

        $legacyValues = DB::table('records_owners')
            ->whereNotNull('record_type')
            ->distinct()
            ->pluck('record_type');

        foreach ($legacyValues as $legacyValue) {
            $name = self::LEGACY_TYPES[$legacyValue] ?? Str::headline($legacyValue);

            $recordTypeId = DB::table('record_types')->where('name', $name)->value('id')
                ?? DB::table('record_types')->insertGetId([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('records_owners')
                ->where('record_type', $legacyValue)
                ->update(['record_type_id' => $recordTypeId]);
        }

        Schema::table('records_owners', function (Blueprint $table) {
            $table->dropColumn('record_type');
        });

        Schema::table('records_owners', function (Blueprint $table) {
            $table->foreignId('record_type_id')->nullable(false)->change();
        });

        Schema::table('records_owners', function (Blueprint $table) {
            $table->foreign('record_type_id')->references('id')->on('record_types')->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Record types added after this migration has run have no legacy enum
     * equivalent, so those rows keep a null `record_type` instead of failing.
     */
    public function down(): void
    {
        Schema::table('records_owners', function (Blueprint $table) {
            $table->enum('record_type', array_keys(self::LEGACY_TYPES))->nullable();
        });

        foreach (DB::table('record_types')->pluck('name', 'id') as $id => $name) {
            if (! in_array($name, self::LEGACY_TYPES, true)) {
                continue;
            }

            DB::table('records_owners')
                ->where('record_type_id', $id)
                ->update(['record_type' => array_search($name, self::LEGACY_TYPES, true)]);
        }

        Schema::table('records_owners', function (Blueprint $table) {
            $table->dropForeign(['record_type_id']);
        });

        Schema::table('records_owners', function (Blueprint $table) {
            $table->dropColumn('record_type_id');
        });
    }
};
