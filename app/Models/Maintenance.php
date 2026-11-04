<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maintenance extends Model
{
    protected $fillable = [
        'camera_id',
        'issue',
        'scheduled_date',
        'status',
        'assigned_to',
    ];

    protected function casts(): array
    {
        return ['scheduled_date' => 'date:Y-m-d'];
    }

    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }
}
