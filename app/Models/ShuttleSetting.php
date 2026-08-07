<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShuttleSetting extends Model
{
    public const DEFAULT_BOOKING_WINDOW_ENABLED = true;

    public const DEFAULT_BOOKING_WINDOW_OPENS_AT = '09:00:00';

    public const DEFAULT_BOOKING_WINDOW_CLOSES_AT = '16:00:00';

    /** @var list<string> */
    protected $fillable = [
        'booking_window_enabled',
        'booking_window_opens_at',
        'booking_window_closes_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'booking_window_enabled' => self::DEFAULT_BOOKING_WINDOW_ENABLED,
        'booking_window_opens_at' => self::DEFAULT_BOOKING_WINDOW_OPENS_AT,
        'booking_window_closes_at' => self::DEFAULT_BOOKING_WINDOW_CLOSES_AT,
    ];

    /**
     * The application keeps a single settings row.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'booking_window_enabled' => 'boolean',
        ];
    }
}
