<?php

namespace App\Models;

use App\Models\StoredFile;
use Illuminate\Database\Eloquent\Model;

class PhysicalFile extends Model
{
    protected $fillable = [
        'file_hash',
        'file_size',
        'reference_count',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'reference_count' => 'integer',
        ];
    }

    public function storedFiles()
    {
        return $this->hasMany(StoredFile::class);
    }
}
