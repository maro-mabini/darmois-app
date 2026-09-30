<?php

namespace App\Models;

use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $records_owners_id
 * @property string $file_name
 * @property string|null $file_description
 * @property Carbon|null $date_of_file
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RecordsOwner $recordsOwner
 */
#[Fillable(['records_owners_id', 'file_name', 'file_description', 'date_of_file'])]
class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_file' => 'date',
        ];
    }

    /**
     * Get the record owner the file belongs to.
     *
     * @return BelongsTo<RecordsOwner, $this>
     */
    public function recordsOwner(): BelongsTo
    {
        return $this->belongsTo(RecordsOwner::class, 'records_owners_id');
    }
}
