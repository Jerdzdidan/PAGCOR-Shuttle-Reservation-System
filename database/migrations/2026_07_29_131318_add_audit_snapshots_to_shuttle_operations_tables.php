<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shuttle_service_occurrences', function (Blueprint $table) {
            $table->unsignedBigInteger('finalized_by_id_snapshot')
                ->nullable()
                ->after('finalized_by');
            $table->string('finalized_by_name')
                ->nullable()
                ->after('finalized_by_id_snapshot');
        });
        Schema::table('shuttle_service_attendances', function (Blueprint $table) {
            $table->unsignedBigInteger('recorded_by_id_snapshot')
                ->nullable()
                ->after('recorded_by');
            $table->string('recorded_by_name')
                ->nullable()
                ->after('recorded_by_id_snapshot');
        });
        Schema::table('shuttle_service_corrections', function (Blueprint $table) {
            $table->unsignedBigInteger('corrected_by_id_snapshot')
                ->nullable()
                ->after('corrected_by');
            $table->string('corrected_by_name')
                ->nullable()
                ->after('corrected_by_id_snapshot');
        });
        Schema::table('shuttle_activity_events', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id_snapshot')
                ->nullable()
                ->after('employee_id');
            $table->unsignedBigInteger('shuttle_schedule_id_snapshot')
                ->nullable()
                ->after('shuttle_schedule_id');
            $table->unsignedBigInteger('route_id_snapshot')
                ->nullable()
                ->after('shuttle_service_occurrence_id');
            $table->string('route_name')
                ->nullable()
                ->after('route_id_snapshot');
            $table->string('direction', 20)
                ->nullable()
                ->after('route_name');
            $table->time('departure_time')
                ->nullable()
                ->after('direction');
            $table->unsignedBigInteger('vehicle_id_snapshot')
                ->nullable()
                ->after('departure_time');
            $table->string('plate_number', 30)
                ->nullable()
                ->after('vehicle_id_snapshot');
            $table->unsignedBigInteger('driver_id_snapshot')
                ->nullable()
                ->after('plate_number');
            $table->string('driver_name')
                ->nullable()
                ->after('driver_id_snapshot');
            $table->string('driver_employee_id', 100)
                ->nullable()
                ->after('driver_name');

            $table->index(
                [
                    'shuttle_schedule_id_snapshot',
                    'travel_date',
                    'event_type',
                ],
                'activity_schedule_snapshot_date_type_index'
            );
            $table->index(
                ['employee_id_snapshot', 'occurred_at'],
                'activity_employee_snapshot_time_index'
            );
            $table->index(
                ['route_id_snapshot', 'travel_date'],
                'activity_route_snapshot_date_index'
            );
            $table->index(
                ['vehicle_id_snapshot', 'travel_date'],
                'activity_vehicle_snapshot_date_index'
            );
            $table->index(
                ['driver_id_snapshot', 'travel_date'],
                'activity_driver_snapshot_date_index'
            );
        });

        $this->backfillAdministratorNames();
        $this->backfillActivitySnapshots();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shuttle_activity_events', function (Blueprint $table) {
            $table->dropIndex('activity_schedule_snapshot_date_type_index');
            $table->dropIndex('activity_employee_snapshot_time_index');
            $table->dropIndex('activity_route_snapshot_date_index');
            $table->dropIndex('activity_vehicle_snapshot_date_index');
            $table->dropIndex('activity_driver_snapshot_date_index');
            $table->dropColumn([
                'employee_id_snapshot',
                'shuttle_schedule_id_snapshot',
                'route_id_snapshot',
                'route_name',
                'direction',
                'departure_time',
                'vehicle_id_snapshot',
                'plate_number',
                'driver_id_snapshot',
                'driver_name',
                'driver_employee_id',
            ]);
        });
        Schema::table('shuttle_service_corrections', function (Blueprint $table) {
            $table->dropColumn([
                'corrected_by_id_snapshot',
                'corrected_by_name',
            ]);
        });
        Schema::table('shuttle_service_attendances', function (Blueprint $table) {
            $table->dropColumn([
                'recorded_by_id_snapshot',
                'recorded_by_name',
            ]);
        });
        Schema::table('shuttle_service_occurrences', function (Blueprint $table) {
            $table->dropColumn([
                'finalized_by_id_snapshot',
                'finalized_by_name',
            ]);
        });
    }

    private function backfillAdministratorNames(): void
    {
        $this->backfillAdministratorName(
            table: 'shuttle_service_occurrences',
            administratorIdColumn: 'finalized_by',
            administratorIdSnapshotColumn: 'finalized_by_id_snapshot',
            administratorNameSnapshotColumn: 'finalized_by_name',
        );
        $this->backfillAdministratorName(
            table: 'shuttle_service_attendances',
            administratorIdColumn: 'recorded_by',
            administratorIdSnapshotColumn: 'recorded_by_id_snapshot',
            administratorNameSnapshotColumn: 'recorded_by_name',
        );
        $this->backfillAdministratorName(
            table: 'shuttle_service_corrections',
            administratorIdColumn: 'corrected_by',
            administratorIdSnapshotColumn: 'corrected_by_id_snapshot',
            administratorNameSnapshotColumn: 'corrected_by_name',
        );
    }

    private function backfillAdministratorName(
        string $table,
        string $administratorIdColumn,
        string $administratorIdSnapshotColumn,
        string $administratorNameSnapshotColumn,
    ): void {
        DB::table($table)
            ->whereNotNull($administratorIdColumn)
            ->orderBy('id')
            ->chunkById(200, function (Collection $rows) use (
                $table,
                $administratorIdColumn,
                $administratorIdSnapshotColumn,
                $administratorNameSnapshotColumn,
            ): void {
                $names = DB::table('users')
                    ->whereIn(
                        'id',
                        $rows->pluck($administratorIdColumn)->filter()->unique()
                    )
                    ->pluck('name', 'id');

                foreach ($rows as $row) {
                    $administratorId = $row->{$administratorIdColumn};
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([
                            $administratorIdSnapshotColumn => $administratorId,
                            $administratorNameSnapshotColumn => $names->get(
                                $administratorId
                            ),
                        ]);
                }
            });
    }

    private function backfillActivitySnapshots(): void
    {
        DB::table('shuttle_activity_events')
            ->orderBy('id')
            ->chunkById(200, function (Collection $events): void {
                $schedules = DB::table('shuttle_schedules as schedules')
                    ->leftJoin('shuttle_routes as routes', 'routes.id', '=', 'schedules.route_id')
                    ->leftJoin('vehicles', 'vehicles.id', '=', 'schedules.vehicle_id')
                    ->leftJoin('drivers', 'drivers.id', '=', 'schedules.driver_id')
                    ->whereIn(
                        'schedules.id',
                        $events->pluck('shuttle_schedule_id')->filter()->unique()
                    )
                    ->get([
                        'schedules.id',
                        'schedules.route_id',
                        'schedules.vehicle_id',
                        'schedules.driver_id',
                        'schedules.direction',
                        'schedules.departure_time',
                        'routes.name as route_name',
                        'vehicles.plate_number',
                        'drivers.name as driver_name',
                        'drivers.employee_id as driver_employee_id',
                    ])
                    ->keyBy('id');
                $occurrences = DB::table('shuttle_service_occurrences')
                    ->whereIn(
                        'id',
                        $events
                            ->pluck('shuttle_service_occurrence_id')
                            ->filter()
                            ->unique()
                    )
                    ->get([
                        'id',
                        'shuttle_schedule_id',
                        'route_id',
                        'vehicle_id',
                        'driver_id',
                        'route_name',
                        'direction',
                        'departure_time',
                        'plate_number',
                        'driver_name',
                        'driver_employee_id',
                    ])
                    ->keyBy('id');

                foreach ($events as $event) {
                    $schedule = $schedules->get($event->shuttle_schedule_id);
                    $occurrence = $occurrences->get(
                        $event->shuttle_service_occurrence_id
                    );

                    DB::table('shuttle_activity_events')
                        ->where('id', $event->id)
                        ->update([
                            'employee_id_snapshot' => $event->employee_id,
                            'shuttle_schedule_id_snapshot' => $occurrence?->shuttle_schedule_id
                                ?? $event->shuttle_schedule_id,
                            'route_id_snapshot' => $occurrence?->route_id
                                ?? $schedule?->route_id,
                            'route_name' => $occurrence?->route_name
                                ?? $schedule?->route_name,
                            'direction' => $occurrence?->direction
                                ?? $schedule?->direction,
                            'departure_time' => $occurrence?->departure_time
                                ?? $schedule?->departure_time,
                            'vehicle_id_snapshot' => $occurrence?->vehicle_id
                                ?? $schedule?->vehicle_id,
                            'plate_number' => $occurrence?->plate_number
                                ?? $schedule?->plate_number,
                            'driver_id_snapshot' => $occurrence?->driver_id
                                ?? $schedule?->driver_id,
                            'driver_name' => $occurrence?->driver_name
                                ?? $schedule?->driver_name,
                            'driver_employee_id' => $occurrence?->driver_employee_id
                                ?? $schedule?->driver_employee_id,
                        ]);
                }
            });
    }
};
