<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShuttleReservation extends Model
{
    public const SOURCE_SELECTED = 'SELECTED';

    public const SOURCE_AUTO_ASSIGNED = 'AUTO_ASSIGNED';

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'shuttle_schedule_id',
        'travel_date',
        'seat_number',
        'source',
        'reserved_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'source' => self::SOURCE_SELECTED,
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /** @return BelongsTo<ShuttleSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ShuttleSchedule::class, 'shuttle_schedule_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'travel_date' => 'date:Y-m-d',
            'seat_number' => 'integer',
            'reserved_at' => 'datetime',
        ];
    }
}
