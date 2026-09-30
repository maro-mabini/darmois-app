<?php

namespace App\Models;

use Database\Factories\RecordTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, RecordsOwner> $records
 */
#[Fillable(['name'])]
class RecordType extends Model
{
    /** @use HasFactory<RecordTypeFactory> */
    use HasFactory;

    /**
     * Get the records that use this record type.
     *
     * @return HasMany<RecordsOwner, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(RecordsOwner::class);
    }
}
