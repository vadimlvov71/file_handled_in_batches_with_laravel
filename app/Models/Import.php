<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    protected $fillable = [
        'file_path',
        'status',
        'total',
        'processed',
        'valid',
        'invalid',
        'error_file_path',
        'error_message',
        'started_at',
        'finished_at',
        'duration_ms',
        'read_duration_ms',
        'validation_duration_ms',
        'db_duration_ms',
        'error_file_duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'total' => 'integer',
            'processed' => 'integer',
            'valid' => 'integer',
            'invalid' => 'integer',
            'duration_ms' => 'integer',
            'read_duration_ms' => 'integer',
            'validation_duration_ms' => 'integer',
            'db_duration_ms' => 'integer',
            'error_file_duration_ms' => 'integer',
        ];
    }
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}