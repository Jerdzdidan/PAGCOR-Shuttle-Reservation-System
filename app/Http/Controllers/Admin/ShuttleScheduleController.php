<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrowseAdminSchedulesRequest;
use App\Http\Requests\StoreShuttleScheduleRequest;
use App\Http\Requests\UpdateBookingWindowRequest;
use App\Http\Requests\UpdateShuttleScheduleRequest;
use App\Models\Driver;
use App\Models\ShuttleRoute;
use App\Models\ShuttleSchedule;
use App\Models\ShuttleSetting;
use App\Models\Vehicle;
use App\Services\AdminScheduleData;
use App\Services\ShuttleSeatPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShuttleScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(
        BrowseAdminSchedulesRequest $request,
        AdminScheduleData $scheduleData,
        ShuttleSeatPolicy $seatPolicy,
    ): Response {
        $operatingTimezone = (string) config('shuttle.operating_timezone', 'Asia/Manila');
        $selectedDate = CarbonImmutable::parse(
            $request->validated('date')
                ?? CarbonImmutable::now($operatingTimezone)->toDateString(),
            $operatingTimezone
        )->startOfDay();

        return Inertia::render('admin/schedules', [
            'schedules' => fn () => ShuttleSchedule::query()
                ->with([
                    'route:id,name,status',
                    'vehicle:id,plate_number,capacity,status',
                    'driver:id,name,employee_id,status',
                ])
                ->latest()
                ->get(),
            'scheduleOccurrences' => fn (): array => $scheduleData->occurrences($selectedDate),
            'selectedDate' => $selectedDate->toDateString(),
            'defaultPrioritySeatCount' => $seatPolicy->defaultPrioritySeatCount(),
            'routes' => fn () => ShuttleRoute::query()
                ->orderBy('name')
                ->get(['id', 'name', 'status']),
            'vehicles' => fn () => Vehicle::query()
                ->orderBy('plate_number')
                ->get(['id', 'plate_number', 'capacity', 'status']),
            'drivers' => fn () => Driver::query()
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id', 'status']),
            'bookingWindow' => fn (): array => $this->bookingWindowSettings(),
            'operatingTimezone' => $operatingTimezone,
        ]);
    }

    /**
     * Update the hours during which employees may open the schedules page and book seats.
     */
    public function updateBookingWindow(UpdateBookingWindowRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $enabled = (bool) $validated['enabled'];

        ShuttleSetting::current()->update([
            'booking_window_enabled' => $enabled,
            'booking_window_opens_at' => $enabled ? $validated['opens_at'] : null,
            'booking_window_closes_at' => $enabled ? $validated['closes_at'] : null,
        ]);

        return back()->with(
            'success',
            $enabled
                ? 'Employee booking window updated successfully.'
                : 'Employee booking is now open at all hours.',
        );
    }

    /**
     * @return array{
     *     enabled: bool,
     *     opens_at: string,
     *     closes_at: string,
     *     defaults: array{opens_at: string, closes_at: string}
     * }
     */
    private function bookingWindowSettings(): array
    {
        $settings = ShuttleSetting::current();

        return [
            'enabled' => (bool) $settings->booking_window_enabled,
            'opens_at' => $this->clockTime($settings->booking_window_opens_at),
            'closes_at' => $this->clockTime($settings->booking_window_closes_at),
            'defaults' => [
                'opens_at' => $this->clockTime(ShuttleSetting::DEFAULT_BOOKING_WINDOW_OPENS_AT),
                'closes_at' => $this->clockTime(ShuttleSetting::DEFAULT_BOOKING_WINDOW_CLOSES_AT),
            ],
        ];
    }

    private function clockTime(?string $time): string
    {
        return blank($time) ? '' : mb_substr($time, 0, 5);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): never
    {
        abort(404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreShuttleScheduleRequest $request): RedirectResponse
    {
        ShuttleSchedule::create($request->validated());

        return to_route('admin.schedules.index')->with('success', 'Schedule created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ShuttleSchedule $schedule): never
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ShuttleSchedule $schedule): never
    {
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateShuttleScheduleRequest $request, ShuttleSchedule $schedule): RedirectResponse
    {
        $schedule->update($request->validated());

        return to_route('admin.schedules.index')->with('success', 'Schedule updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ShuttleSchedule $schedule): RedirectResponse
    {
        if ($schedule->reservations()->exists() || $schedule->waitlistEntries()->exists()) {
            throw ValidationException::withMessages([
                'schedule' => 'This schedule cannot be deleted while reservations or waitlist entries exist.',
            ]);
        }

        if ($schedule->serviceOccurrences()->exists()) {
            throw ValidationException::withMessages([
                'schedule' => 'This schedule has retained service history and cannot be deleted. Mark it inactive instead.',
            ]);
        }

        $schedule->delete();

        return to_route('admin.schedules.index')->with('success', 'Schedule deleted successfully.');
    }
}
