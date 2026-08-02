<?php

namespace App\Services;

use App\Enums\ServiceOccurrenceStatus;
use App\Models\ShuttleServiceOccurrence;
use Carbon\CarbonImmutable;

class AdminDashboardService
{
    /**
     * @return array{
     *     date: string,
     *     generated_at: string,
     *     timezone: string,
     *     trips: array{total: int, scheduled: int, awaiting_completion: int, completed: int, not_operated: int},
     *     employees: array{time_in: int},
     *     passengers: array{boarded: int, reserved: int, no_shows: int}
     * }
     */
    public function dailySummary(): array
    {
        $timezone = (string) config(
            'shuttle.operating_timezone',
            'Asia/Manila'
        );
        $now = CarbonImmutable::now($timezone);
        $date = $now->toDateString();

        $summary = ShuttleServiceOccurrence::query()
            ->whereDate('travel_date', $date)
            ->toBase()
            ->selectRaw('COUNT(*) as total_trips')
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as scheduled',
                [ServiceOccurrenceStatus::Scheduled->value]
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as awaiting_completion',
                [ServiceOccurrenceStatus::AwaitingCompletion->value]
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed',
                [ServiceOccurrenceStatus::Completed->value]
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as not_operated',
                [ServiceOccurrenceStatus::NotOperated->value]
            )
            ->selectRaw('COALESCE(SUM(boarded_count), 0) as boarded')
            ->selectRaw('COALESCE(SUM(reservation_count), 0) as reserved')
            ->selectRaw('COALESCE(SUM(no_show_count), 0) as no_shows')
            ->first();

        return [
            'date' => $date,
            'generated_at' => $now->toIso8601String(),
            'timezone' => $timezone,
            'trips' => [
                'total' => (int) ($summary?->total_trips ?? 0),
                'scheduled' => (int) ($summary?->scheduled ?? 0),
                'awaiting_completion' => (int) ($summary?->awaiting_completion ?? 0),
                'completed' => (int) ($summary?->completed ?? 0),
                'not_operated' => (int) ($summary?->not_operated ?? 0),
            ],
            'employees' => [
                'time_in' => (int) ($summary?->boarded ?? 0),
            ],
            'passengers' => [
                'boarded' => (int) ($summary?->boarded ?? 0),
                'reserved' => (int) ($summary?->reserved ?? 0),
                'no_shows' => (int) ($summary?->no_shows ?? 0),
            ],
        ];
    }
}
