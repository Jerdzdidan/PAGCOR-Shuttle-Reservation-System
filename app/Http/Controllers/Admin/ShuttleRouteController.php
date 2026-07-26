<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShuttleRouteRequest;
use App\Http\Requests\UpdateShuttleRouteRequest;
use App\Models\ShuttleRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShuttleRouteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('admin/routes', ['routes' => ShuttleRoute::query()->latest()->get()]);
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
    public function store(StoreShuttleRouteRequest $request): RedirectResponse
    {
        ShuttleRoute::create($request->validated());

        return to_route('admin.routes.index')->with('success', 'Route created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ShuttleRoute $route): never
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ShuttleRoute $route): never
    {
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateShuttleRouteRequest $request, ShuttleRoute $route): RedirectResponse
    {
        $route->update($request->validated());

        return to_route('admin.routes.index')->with('success', 'Route updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ShuttleRoute $route): RedirectResponse
    {
        if ($route->schedules()->exists()) {
            throw ValidationException::withMessages(['route' => 'This route cannot be deleted because it is referenced by a schedule.']);
        }

        $route->delete();

        return to_route('admin.routes.index')->with('success', 'Route deleted successfully.');
    }
}
