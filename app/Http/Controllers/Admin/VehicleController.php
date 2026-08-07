<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('admin/vehicles', [
            'vehicles' => Vehicle::query()
                ->select([
                    'id',
                    'plate_number',
                    'vehicle_type',
                    'capacity',
                    'status',
                    'notes',
                ])
                ->orderBy('plate_number')
                ->get(),
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
    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        Vehicle::create($request->validated());

        return to_route('admin.vehicles.index')->with('success', 'Vehicle created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Vehicle $vehicle): never
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vehicle $vehicle): never
    {
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update($request->validated());

        return to_route('admin.vehicles.index')->with('success', 'Vehicle updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        if (
            $vehicle->schedules()->exists()
            || $vehicle->serviceOccurrences()->exists()
        ) {
            throw ValidationException::withMessages(['vehicle' => 'This vehicle cannot be deleted because it is referenced by a schedule or retained service history.']);
        }

        $vehicle->delete();

        return to_route('admin.vehicles.index')->with('success', 'Vehicle deleted successfully.');
    }
}
