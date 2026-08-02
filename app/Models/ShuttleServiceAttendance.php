<?php

namespace App\Models;

use App\Enums\AttendanceRecordingMethod;
use App\Enums\ServiceAttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShuttleServiceAttendance extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'shuttle_service_occurrence_id',
        'shuttle_reservation_id',
        'employee_id',
        'employee_id_snapshot',
        'employee_code_snapshot',
        'employee_name',
        'department',
        'priority_status',
        'seat_number',
        'status',
        'recording_method',
        'boarded_at',
        'recorded_by',
        'recorded_by_id_snapshot',
        'recorded_by_name',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'employee_id_snapshot' => 'integer',
            'seat_number' => 'integer',
            'status' => ServiceAttendanceStatus::class,
            'recording_method' => AttendanceRecordingMethod::class,
            'boarded_at' => 'datetime',
            'recorded_by_id_snapshot' => 'integer',
        ];
    }

    /** @return BelongsTo<ShuttleServiceOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ShuttleServiceOccurrence::class, 'shuttle_service_occurrence_id');
    }

    /** @return BelongsTo<ShuttleReservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ShuttleReservation::class, 'shuttle_reservation_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
