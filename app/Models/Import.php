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
        'duration_ms',
        'read_duration_ms',
        'validation_duration_ms',
        'db_duration_ms',
        'error_file_duration_ms'
    ];
}