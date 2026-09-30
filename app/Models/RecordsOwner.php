<?php

namespace App\Models;

use Database\Factories\RecordsOwnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $owner_name
 * @property int $owner_type_id
 * @property int $record_type_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OwnerType $ownerType
 * @property-read RecordType $recordType
 * @property-read Collection<int, File> $files
 */
#[Fillable(['owner_name', 'owner_type_id', 'record_type_id'])]
class RecordsOwner extends Model
{
    /** @use HasFactory<RecordsOwnerFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get the type of the record's owner.
     *
     * @return BelongsTo<OwnerType, $this>
     */
    public function ownerType(): BelongsTo
    {
        return $this->belongsTo(OwnerType::class);
    }

    /**
     * Get the record type that categorises this record.
     *
     * @return BelongsTo<RecordType, $this>
     */
    public function recordType(): BelongsTo
    {
        return $this->belongsTo(RecordType::class);
    }

    /**
     * Get the files belonging to the record owner.
     *
     * @return HasMany<File, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'records_owners_id');
    }
}
