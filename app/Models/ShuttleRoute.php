<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ShuttleRoute extends Model
{
    /** @use HasFactory<\Database\Factories\ShuttleRouteFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'origin', 'destination', 'status'];

    /** @return HasMany<ShuttleSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(ShuttleSchedule::class, 'route_id');
    }
}
