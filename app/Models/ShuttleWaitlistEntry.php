<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShuttleWaitlistEntry extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'shuttle_schedule_id',
        'travel_date',
        'queued_at',
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
            'queued_at' => 'datetime',
        ];
    }
}
