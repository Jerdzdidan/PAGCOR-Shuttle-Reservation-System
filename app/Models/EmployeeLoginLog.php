<?php

namespace App\Models;

use App\Enums\EmployeeLoginMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class EmployeeLoginLog extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'employee_id_snapshot',
        'employee_code_snapshot',
        'employee_name',
        'department',
        'priority_status',
        'login_method',
        'logged_in_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Employee login records are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Employee login records are immutable.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'employee_id_snapshot' => 'integer',
            'login_method' => EmployeeLoginMethod::class,
            'logged_in_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
