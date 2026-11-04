<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Camera extends Model
{
    protected $fillable = [
        'brand',
        'model',
        'category',
        'daily_rate',
        'availability',
        'description',
        'specs',
    ];

    protected function casts(): array
    {
        return ['specs' => 'array'];
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }
}
