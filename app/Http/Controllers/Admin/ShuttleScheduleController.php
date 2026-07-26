<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShuttleScheduleRequest;
use App\Http\Requests\UpdateShuttleScheduleRequest;
use App\Models\Driver;
use App\Models\ShuttleRoute;
use App\Models\ShuttleSchedule;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ShuttleScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('admin/schedules', [
            'schedules' => ShuttleSchedule::query()->with(['route:id,name', 'vehicle:id,plate_number', 'driver:id,name'])->latest()->get(),
            'routes' => ShuttleRoute::query()->orderBy('name')->get(['id', 'name', 'status']),
            'vehicles' => Vehicle::query()->orderBy('plate_number')->get(['id', 'plate_number', 'capacity', 'status']),
            'drivers' => Driver::query()->orderBy('name')->get(['id', 'name', 'employee_id', 'status']),
            'operatingTimezone' => config('shuttle.operating_timezone'),
        ]);
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
        $schedule->delete();

        return to_route('admin.schedules.index')->with('success', 'Schedule deleted successfully.');
    }
}
