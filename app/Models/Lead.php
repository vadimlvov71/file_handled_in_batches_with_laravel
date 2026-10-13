<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    protected $fillable = [
        'external_id',
        'import_id',
        'registered_at',
        'first_name',
        'last_name',
        'phone',
        'email',
        'city',
        'source',
        'campaign',
        'service',
        'budget',
        'status',
        'manager',
        'comment',
        'last_contact_at',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}