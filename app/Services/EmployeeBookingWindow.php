<?php

namespace App\Services;

use App\Models\ShuttleSetting;
use Carbon\CarbonImmutable;

/**
 * Resolves the administrator-configured window during which employees may browse
 * schedules and book seats. Outside the window the schedules page is locked.
 */
class EmployeeBookingWindow
{
    private ?ShuttleSetting $settings = null;

    public function isOpen(?CarbonImmutable $now = null): bool
    {
        $settings = $this->settings();

        if (! $settings->booking_window_enabled) {
            return true;
        }

        $opensAt = $this->minuteOfDay($settings->booking_window_opens_at);
        $closesAt = $this->minuteOfDay($settings->booking_window_closes_at);

        if ($opensAt === null || $closesAt === null || $opensAt === $closesAt) {
            return true;
        }

        $now ??= CarbonImmutable::now($this->operatingTimezone());
        $currentMinute = $now->hour * 60 + $now->minute;

        if ($opensAt < $closesAt) {
            return $currentMinute >= $opensAt && $currentMinute < $closesAt;
        }

        return $currentMinute >= $opensAt || $currentMinute < $closesAt;
    }

    /**
     * Window details for the employee UI.
     *
     * @return array{enabled: bool, opens_at: ?string, closes_at: ?string, is_open: bool, message: ?string}
     */
    public function state(): array
    {
        $settings = $this->settings();
        $opensAt = $this->displayTime($settings->booking_window_opens_at);
        $closesAt = $this->displayTime($settings->booking_window_closes_at);
        $enabled = (bool) $settings->booking_window_enabled && $opensAt !== null && $closesAt !== null;

        return [
            'enabled' => $enabled,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'is_open' => $this->isOpen(),
            'message' => $enabled ? $this->message() : null,
        ];
    }

    public function message(): string
    {
        $settings = $this->settings();
        $opensAt = $this->displayTime($settings->booking_window_opens_at);
        $closesAt = $this->displayTime($settings->booking_window_closes_at);

        if ($opensAt === null || $closesAt === null) {
            return 'Shuttle booking is currently unavailable.';
        }

        return sprintf(
            'Shuttle booking is only available between %s and %s (%s).',
            $opensAt,
            $closesAt,
            $this->operatingTimezone(),
        );
    }

    private function settings(): ShuttleSetting
    {
        return $this->settings ??= ShuttleSetting::current();
    }

    private function minuteOfDay(?string $time): ?int
    {
        if (blank($time)) {
            return null;
        }

        [$hour, $minute] = array_pad(explode(':', $time, 3), 2, '0');

        return ((int) $hour) * 60 + (int) $minute;
    }

    private function displayTime(?string $time): ?string
    {
        if (blank($time)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('H:i', mb_substr($time, 0, 5))->format('g:i A');
    }

    private function operatingTimezone(): string
    {
        return (string) config('shuttle.operating_timezone', 'Asia/Manila');
    }
}
