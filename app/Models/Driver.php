<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    /** @use HasFactory<\Database\Factories\DriverFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'employee_id', 'contact_number', 'license_number', 'license_expires_at', 'status', 'notes'];

    /** @return HasMany<ShuttleSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(ShuttleSchedule::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['license_expires_at' => 'date:Y-m-d'];
    }
}
