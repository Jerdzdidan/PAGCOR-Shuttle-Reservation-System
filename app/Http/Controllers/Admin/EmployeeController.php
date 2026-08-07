<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeactivateEmployeeRequest;
use App\Http\Requests\ImportEmployeesRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Imports\EmployeesImport;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ShuttleReservation;
use App\Models\ShuttleServiceOccurrence;
use App\Models\ShuttleWaitlistEntry;
use App\Services\DeactivateEmployeeService;
use App\Services\EmployeeActivityData;
use App\Services\EmployeeIdentifier;
use App\Services\EmployeeQrCredential;
use App\Services\ShuttleSeatPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;
use Throwable;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(EmployeeQrCredential $qrCredential): Response
    {
        $now = CarbonImmutable::now((string) config('shuttle.operating_timezone', 'Asia/Manila'));

        return Inertia::render('admin/employees', [
            'departments' => Department::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'employees' => Employee::query()
                ->select([
                    'employee_id',
                    'employee_code',
                    'name',
                    'email',
                    'contact_number',
                    'department',
                    'position',
                    'priority_status',
                    'status',
                    'created_at',
                ])
                ->withCount([
                    'shuttleReservations as future_reservations_count' => fn (Builder $query): Builder => $this
                        ->futureTravel($query, $now),
                    'shuttleWaitlistEntries as future_waitlist_count' => fn (Builder $query): Builder => $this
                        ->futureTravel($query, $now),
                ])
                ->latest('employee_id')
                ->get()
                ->map(fn (Employee $employee): array => [
                    ...$employee->only([
                        'employee_id',
                        'employee_code',
                        'name',
                        'email',
                        'contact_number',
                        'department',
                        'position',
                        'priority_status',
                        'status',
                        'created_at',
                        'future_reservations_count',
                        'future_waitlist_count',
                    ]),
                    'qr_login_url' => $qrCredential->loginUrl($employee),
                ]),
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
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        Employee::create($request->validated());

        return to_route('admin.employees.index')->with('success', 'Employee created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee): never
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee): never
    {
        abort(404);
    }

    /**
     * Return the employee's login, boarding, and current reservation trail.
     */
    public function activity(
        Employee $employee,
        EmployeeActivityData $activityData,
    ): JsonResponse {
        return response()->json($activityData->forEmployee($employee));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateEmployeeRequest $request,
        Employee $employee,
        ShuttleSeatPolicy $seatPolicy,
    ): RedirectResponse {
        $validated = $request->validated();

        DB::transaction(function () use ($employee, $validated, $seatPolicy): void {
            $lockedEmployee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($employee->getKey());

            if (
                $lockedEmployee->isActive()
                && $validated['status'] === Employee::STATUS_INACTIVE
                && $this->hasFutureTravel($lockedEmployee)
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Cancel this employee’s future reservations and waitlist entries before making the account inactive.',
                ]);
            }

            if (
                $lockedEmployee->isPriority()
                && $validated['priority_status'] === Employee::PRIORITY_STATUS_REGULAR
                && $this->hasFutureProtectedSeatReservation(
                    $lockedEmployee,
                    $seatPolicy,
                )
            ) {
                throw ValidationException::withMessages([
                    'priority_status' => 'Cancel this employee’s protected-seat reservations before changing them to regular status.',
                ]);
            }

            $lockedEmployee->update($validated);
        }, 5);

        return to_route('admin.employees.index')->with('success', 'Employee updated successfully.');
    }

    public function deactivate(
        DeactivateEmployeeRequest $request,
        Employee $employee,
        DeactivateEmployeeService $deactivateEmployee,
    ): RedirectResponse {
        $request->validated();
        $resolved = $deactivateEmployee->deactivate($employee);
        $resolvedCount = $resolved['reservations'] + $resolved['waitlist_entries'];

        $message = $resolvedCount === 0
            ? 'Employee access deactivated successfully.'
            : sprintf(
                'Employee access deactivated after resolving %d future %s.',
                $resolvedCount,
                $resolvedCount === 1 ? 'travel entry' : 'travel entries',
            );

        return to_route('admin.employees.index')->with('success', $message);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $hasActiveTravel = ShuttleReservation::query()
            ->where('employee_id', $employee->employee_id)
            ->exists()
            || ShuttleWaitlistEntry::query()
                ->where('employee_id', $employee->employee_id)
                ->exists();

        if ($hasActiveTravel) {
            throw ValidationException::withMessages([
                'employee' => 'Cancel this employee’s reservations and waitlist entries before deleting the record.',
            ]);
        }

        $employee->delete();

        return to_route('admin.employees.index')->with('success', 'Employee deleted successfully.');
    }

    public function import(
        ImportEmployeesRequest $request,
        EmployeeIdentifier $identifiers,
    ): RedirectResponse {
        try {
            Excel::import(new EmployeesImport, $request->file('file'));
            $identifiers->assignMissing();
        } catch (ExcelValidationException $exception) {
            $message = collect($exception->failures())
                ->take(5)
                ->map(fn (Failure $failure): string => 'Row '.$failure->row().': '.implode(' ', $failure->errors()))
                ->implode(' ');

            throw ValidationException::withMessages([
                'file' => $message ?: 'The employee file contains invalid rows.',
            ]);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => 'The employee file could not be imported. Check the file format and headings, then try again.',
            ]);
        }

        return to_route('admin.employees.index')->with('success', 'Employees imported successfully.');
    }

    private function hasFutureTravel(Employee $employee): bool
    {
        $now = CarbonImmutable::now((string) config('shuttle.operating_timezone', 'Asia/Manila'));

        return $this->futureTravel(
            ShuttleReservation::query()
                ->where('employee_id', $employee->getKey()),
            $now,
        )->exists()
            || $this->futureTravel(
                ShuttleWaitlistEntry::query()
                    ->where('employee_id', $employee->getKey()),
                $now,
            )->exists();
    }

    private function hasFutureProtectedSeatReservation(
        Employee $employee,
        ShuttleSeatPolicy $seatPolicy,
    ): bool {
        $timezone = (string) config('shuttle.operating_timezone', 'Asia/Manila');
        $now = CarbonImmutable::now($timezone);
        $reservations = ShuttleReservation::query()
            ->with([
                'schedule:id,vehicle_id,departure_time,capacity_override,priority_seats',
                'schedule.vehicle:id,capacity',
            ])
            ->where('employee_id', $employee->getKey())
            ->whereDate('travel_date', '>=', $now->toDateString())
            ->get([
                'id',
                'employee_id',
                'shuttle_schedule_id',
                'travel_date',
                'seat_number',
            ]);

        $occurrences = ShuttleServiceOccurrence::query()
            ->whereIn(
                'shuttle_schedule_id',
                $reservations->pluck('shuttle_schedule_id')->unique(),
            )
            ->whereIn(
                'travel_date',
                $reservations
                    ->pluck('travel_date')
                    ->map(fn ($date): string => $date->toDateString())
                    ->unique(),
            )
            ->get()
            ->keyBy(
                fn (ShuttleServiceOccurrence $occurrence): string => $occurrence->shuttle_schedule_id
                    .'|'.$occurrence->travel_date->toDateString(),
            );

        return $reservations->contains(function (ShuttleReservation $reservation) use (
            $now,
            $occurrences,
            $seatPolicy,
            $timezone,
        ): bool {
            $occurrence = $occurrences->get(
                $reservation->shuttle_schedule_id
                    .'|'.$reservation->travel_date->toDateString(),
            );
            $departureAt = $occurrence?->scheduled_departure_at?->toImmutable()
                ?? CarbonImmutable::parse(
                    $reservation->travel_date->toDateString()
                        .' '.(string) $reservation->schedule->departure_time,
                    $timezone,
                );

            return $departureAt->greaterThan($now)
                && $seatPolicy->isPrioritySeat(
                    $occurrence ?? $reservation->schedule,
                    $reservation->seat_number,
                );
        });
    }

    private function futureTravel(Builder $query, CarbonImmutable $now): Builder
    {
        $travelTable = $query->getModel()->getTable();
        $occurrenceTable = (new ShuttleServiceOccurrence)->getTable();

        return $query->where(function (Builder $futureQuery) use (
            $now,
            $occurrenceTable,
            $travelTable,
        ): void {
            $futureQuery
                ->whereDate($travelTable.'.travel_date', '>', $now->toDateString())
                ->orWhere(function (Builder $sameDayQuery) use (
                    $now,
                    $occurrenceTable,
                    $travelTable,
                ): void {
                    $sameDayQuery
                        ->whereDate($travelTable.'.travel_date', $now->toDateString())
                        ->where(function (Builder $departureQuery) use (
                            $now,
                            $occurrenceTable,
                            $travelTable,
                        ): void {
                            $departureQuery
                                ->whereExists(
                                    fn (QueryBuilder $query): QueryBuilder => $this
                                        ->matchingOccurrence($query, $occurrenceTable, $travelTable)
                                        ->where('scheduled_departure_at', '>', $now->toDateTimeString())
                                )
                                ->orWhere(function (Builder $fallbackQuery) use (
                                    $now,
                                    $occurrenceTable,
                                    $travelTable,
                                ): void {
                                    $fallbackQuery
                                        ->whereNotExists(
                                            fn (QueryBuilder $query): QueryBuilder => $this
                                                ->matchingOccurrence($query, $occurrenceTable, $travelTable)
                                        )
                                        ->whereHas(
                                            'schedule',
                                            fn (Builder $scheduleQuery): Builder => $scheduleQuery
                                                ->where('departure_time', '>', $now->format('H:i:s')),
                                        );
                                });
                        });
                });
        });
    }

    private function matchingOccurrence(
        QueryBuilder $query,
        string $occurrenceTable,
        string $travelTable,
    ): QueryBuilder {
        return $query
            ->selectRaw('1')
            ->from($occurrenceTable)
            ->whereColumn(
                $occurrenceTable.'.shuttle_schedule_id',
                $travelTable.'.shuttle_schedule_id',
            )
            ->whereColumn(
                $occurrenceTable.'.travel_date',
                $travelTable.'.travel_date',
            );
    }
}
