<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class Employee extends Authenticatable
{
    use Notifiable;

    public const PRIORITY_STATUS_REGULAR = 'REGULAR';

    public const PRIORITY_STATUS_PRIORITY = 'PRIORITY';

    protected $primaryKey = 'employee_id';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'contact_number',
        'department',
        'position',
        'priority_status',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'priority_status' => self::PRIORITY_STATUS_REGULAR,
        'qr_token_version' => 1,
    ];

    public function isPriority(): bool
    {
        return $this->priority_status === self::PRIORITY_STATUS_PRIORITY;
    }

    /** @return HasMany<ShuttleReservation, $this> */
    public function shuttleReservations(): HasMany
    {
        return $this->hasMany(ShuttleReservation::class, 'employee_id', 'employee_id');
    }

    /** @return HasMany<ShuttleWaitlistEntry, $this> */
    public function shuttleWaitlistEntries(): HasMany
    {
        return $this->hasMany(ShuttleWaitlistEntry::class, 'employee_id', 'employee_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qr_token_version' => 'integer',
        ];
    }
}
