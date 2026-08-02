<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LogicException;

class Employee extends Authenticatable
{
    use Notifiable;

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_INACTIVE = 'INACTIVE';

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
        'status',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'priority_status' => self::PRIORITY_STATUS_REGULAR,
        'status' => self::STATUS_ACTIVE,
    ];

    protected static function booted(): void
    {
        static::created(function (Employee $employee): void {
            $employee->ensureEmployeeCode();
        });
    }

    public function ensureEmployeeCode(): void
    {
        if (filled($this->employee_code)) {
            return;
        }

        $employeeId = (int) $this->getKey();

        if ($employeeId > 99999) {
            throw new LogicException('Employee IDs above 99999 cannot use the YY-00000 format.');
        }

        $year = $this->created_at?->format('y') ?? now()->format('y');

        $this->forceFill([
            'employee_code' => sprintf('%s-%05d', $year, $employeeId),
        ])->saveQuietly();
    }

    public function isPriority(): bool
    {
        return $this->priority_status === self::PRIORITY_STATUS_PRIORITY;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
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

    /** @return HasMany<ShuttleServiceAttendance, $this> */
    public function serviceAttendances(): HasMany
    {
        return $this->hasMany(ShuttleServiceAttendance::class, 'employee_id', 'employee_id');
    }

    /** @return HasMany<ShuttleActivityEvent, $this> */
    public function shuttleActivityEvents(): HasMany
    {
        return $this->hasMany(ShuttleActivityEvent::class, 'employee_id', 'employee_id');
    }

    /** @return HasMany<EmployeeLoginLog, $this> */
    public function loginLogs(): HasMany
    {
        return $this->hasMany(EmployeeLoginLog::class, 'employee_id', 'employee_id');
    }
}
