<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ShuttleServiceCorrection extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'shuttle_service_occurrence_id',
        'corrected_by',
        'corrected_by_id_snapshot',
        'corrected_by_name',
        'action',
        'reason',
        'before_values',
        'after_values',
        'corrected_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Service correction records are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Service correction records are immutable.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
            'corrected_at' => 'datetime',
            'corrected_by_id_snapshot' => 'integer',
        ];
    }

    /** @return BelongsTo<ShuttleServiceOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ShuttleServiceOccurrence::class, 'shuttle_service_occurrence_id');
    }

    /** @return BelongsTo<User, $this> */
    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
