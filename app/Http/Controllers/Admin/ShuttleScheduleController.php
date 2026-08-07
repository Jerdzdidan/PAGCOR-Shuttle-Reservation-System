<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ServiceOccurrenceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrowseAdminSchedulesRequest;
use App\Http\Requests\StoreShuttleScheduleRequest;
use App\Http\Requests\UpdateBookingWindowRequest;
use App\Http\Requests\UpdateShuttleScheduleRequest;
use App\Models\Driver;
use App\Models\ShuttleRoute;
use App\Models\ShuttleSchedule;
use App\Models\ShuttleServiceOccurrence;
use App\Models\ShuttleSetting;
use App\Models\Vehicle;
use App\Services\AdminScheduleData;
use App\Services\ShuttleSeatPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
    /**
     * Deletes a schedule template. Finalized services keep their own route, vehicle and
     * driver snapshots, so past history survives the schedule that produced it.
     */
    public function destroy(ShuttleSchedule $schedule): RedirectResponse
    {
        if ($schedule->reservations()->exists()) {
            throw ValidationException::withMessages([
                'schedule' => 'This schedule still has seat reservations. Cancel them before deleting it.',
            ]);
        }

        if ($schedule->waitlistEntries()->exists()) {
            throw ValidationException::withMessages([
                'schedule' => 'This schedule still has waitlist entries. Clear the queue before deleting it.',
            ]);
        }

        $retainedServices = DB::transaction(function () use ($schedule): int {
            // Runs that never recorded anything are just placeholders for a date that has
            // not happened yet; remove them so they do not linger without a schedule.
            ShuttleServiceOccurrence::query()
                ->where('shuttle_schedule_id', $schedule->getKey())
                ->whereIn('status', [
                    ServiceOccurrenceStatus::Scheduled,
                    ServiceOccurrenceStatus::AwaitingCompletion,
                ])
                ->whereDoesntHave('attendances')
                ->delete();

            $retained = ShuttleServiceOccurrence::query()
                ->where('shuttle_schedule_id', $schedule->getKey())
                ->count();

            $schedule->delete();

            return $retained;
        }, 5);

        return to_route('admin.schedules.index')->with(
            'success',
            $retainedServices === 0
                ? 'Schedule deleted successfully.'
                : sprintf(
                    'Schedule deleted. %d past service %s kept for reporting.',
                    $retainedServices,
                    $retainedServices === 1 ? 'record was' : 'records were',
                ),
        );
    }
}
