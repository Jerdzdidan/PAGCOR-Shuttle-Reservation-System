<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ServiceOccurrenceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrowseFinishedServicesRequest;
use App\Models\Driver;
use App\Models\ShuttleRoute;
use App\Models\ShuttleServiceOccurrence;
use App\Models\Vehicle;
use App\Services\ServiceOccurrenceData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class FinishedServiceController extends Controller
{
    public function index(
        BrowseFinishedServicesRequest $request,
        ServiceOccurrenceData $occurrenceData,
    ): Response {
        $filters = $request->validated();
        $view = (string) ($filters['view'] ?? 'needs_completion');
        $outcome = $view === 'history'
            ? ($filters['outcome'] ?? null)
            : null;

        $occurrences = ShuttleServiceOccurrence::query()
            ->with('finalizer:id,name')
            ->when($view === 'needs_completion', fn (Builder $query): Builder => $query->where(
                'status',
                ServiceOccurrenceStatus::AwaitingCompletion
            ))
            ->when($view === 'history', fn (Builder $query): Builder => $query->whereIn('status', [
                ServiceOccurrenceStatus::Completed,
                ServiceOccurrenceStatus::NotOperated,
            ]))
            ->when(
                $outcome !== null,
                fn (Builder $query): Builder => $query->where('status', $outcome)
            )
            ->when(
                isset($filters['date_from']),
                fn (Builder $query): Builder => $query->whereDate(
                    'travel_date',
                    '>=',
                    $filters['date_from']
                )
            )
            ->when(
                isset($filters['date_to']),
                fn (Builder $query): Builder => $query->whereDate(
                    'travel_date',
                    '<=',
                    $filters['date_to']
                )
            )
            ->when(
                isset($filters['route_id']),
                fn (Builder $query): Builder => $query->where('route_id', $filters['route_id'])
            )
            ->when(
                isset($filters['vehicle_id']),
                fn (Builder $query): Builder => $query->where('vehicle_id', $filters['vehicle_id'])
            )
            ->when(
                isset($filters['driver_id']),
                fn (Builder $query): Builder => $query->where('driver_id', $filters['driver_id'])
            )
            ->latest('scheduled_departure_at')
            ->paginate(15)
            ->withQueryString()
            ->through(
                fn (ShuttleServiceOccurrence $occurrence): array => $occurrenceData->summary($occurrence)
            );

        $selectedOccurrence = isset($filters['occurrence'])
            ? ShuttleServiceOccurrence::query()->find($filters['occurrence'])
            : null;

        return Inertia::render('admin/finished-services', [
            'occurrences' => $occurrences,
            'filters' => [
                'view' => $view,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'route_id' => isset($filters['route_id'])
                    ? (int) $filters['route_id']
                    : null,
                'vehicle_id' => isset($filters['vehicle_id'])
                    ? (int) $filters['vehicle_id']
                    : null,
                'driver_id' => isset($filters['driver_id'])
                    ? (int) $filters['driver_id']
                    : null,
                'outcome' => $outcome,
            ],
            'options' => [
                'routes' => fn () => ShuttleRoute::query()
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'vehicles' => fn () => Vehicle::query()
                    ->orderBy('plate_number')
                    ->get(['id', 'plate_number']),
                'drivers' => fn () => Driver::query()
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'outcomes' => [
                    [
                        'value' => ServiceOccurrenceStatus::Completed->value,
                        'label' => 'Completed',
                    ],
                    [
                        'value' => ServiceOccurrenceStatus::NotOperated->value,
                        'label' => 'Not operated',
                    ],
                ],
            ],
            'summary' => fn (): array => [
                'pending' => ShuttleServiceOccurrence::query()
                    ->where('status', ServiceOccurrenceStatus::AwaitingCompletion)
                    ->count(),
                'total' => ShuttleServiceOccurrence::query()->count(),
                'completed' => ShuttleServiceOccurrence::query()
                    ->where('status', ServiceOccurrenceStatus::Completed)
                    ->count(),
                'not_operated' => ShuttleServiceOccurrence::query()
                    ->where('status', ServiceOccurrenceStatus::NotOperated)
                    ->count(),
                'closed' => ShuttleServiceOccurrence::query()
                    ->whereIn('status', [
                        ServiceOccurrenceStatus::Completed,
                        ServiceOccurrenceStatus::NotOperated,
                    ])
                    ->count(),
            ],
            'selectedOccurrenceId' => $selectedOccurrence?->getKey(),
            'selectedOccurrence' => $selectedOccurrence === null
                ? null
                : $occurrenceData->detail($selectedOccurrence),
        ]);
    }

    public function show(
        ShuttleServiceOccurrence $serviceOccurrence,
        ServiceOccurrenceData $occurrenceData,
    ): JsonResponse {
        return response()->json([
            'occurrence' => $occurrenceData->detail($serviceOccurrence),
        ]);
    }
}
