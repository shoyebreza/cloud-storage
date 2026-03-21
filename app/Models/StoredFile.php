<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoredFile extends Model
{
    protected $fillable = [
        'user_id',
        'physical_file_id',
        'file_name',
        'file_size',
        'deleted_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function physicalFile(): BelongsTo
    {
        return $this->belongsTo(PhysicalFile::class);
    }
}
