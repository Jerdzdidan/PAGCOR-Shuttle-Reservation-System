<?php

namespace App\Models;

use App\Models\Concerns\RecordsUserActivity;
use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use HasFactory, RecordsUserActivity;

    /** @var list<string> */
    protected $fillable = ['name', 'employee_id', 'contact_number', 'license_number', 'license_expires_at', 'status', 'notes'];

    /** @return HasMany<ShuttleSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(ShuttleSchedule::class);
    }

    /** @return HasMany<ShuttleServiceOccurrence, $this> */
    public function serviceOccurrences(): HasMany
    {
        return $this->hasMany(ShuttleServiceOccurrence::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['license_expires_at' => 'date:Y-m-d'];
    }
}
