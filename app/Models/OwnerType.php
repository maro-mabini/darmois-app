<?php

namespace App\Models;

use Database\Factories\OwnerTypeFactory;
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
class OwnerType extends Model
{
    /** @use HasFactory<OwnerTypeFactory> */
    use HasFactory;

    /**
     * Get the records whose owner has this type.
     *
     * @return HasMany<RecordsOwner, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(RecordsOwner::class);
    }
}
