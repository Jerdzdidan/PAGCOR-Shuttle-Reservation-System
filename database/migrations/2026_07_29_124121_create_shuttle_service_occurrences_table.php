<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shuttle_service_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shuttle_schedule_id')
                ->nullable()
                ->constrained('shuttle_schedules')
                ->nullOnDelete();
            $table->date('travel_date');
            $table->foreignId('route_id')
                ->nullable()
                ->constrained('shuttle_routes')
                ->nullOnDelete();
            $table->foreignId('vehicle_id')
                ->nullable()
                ->constrained('vehicles')
                ->nullOnDelete();
            $table->foreignId('driver_id')
                ->nullable()
                ->constrained('drivers')
                ->nullOnDelete();
            $table->string('route_name');
            $table->string('origin');
            $table->string('destination');
            $table->string('direction', 20);
            $table->string('plate_number', 30);
            $table->string('vehicle_type', 100)->nullable();
            $table->string('driver_name');
            $table->string('driver_employee_id', 100)->nullable();
            $table->time('departure_time');
            $table->dateTime('scheduled_departure_at');
            $table->unsignedSmallInteger('effective_capacity');
            $table->unsignedSmallInteger('available_capacity');
            $table->json('priority_seats')->nullable();
            $table->json('unavailable_seats')->nullable();
            $table->boolean('waitlist_enabled')->default(true);
            $table->unsignedSmallInteger('waitlist_capacity')->nullable();
            $table->string('status', 30)->default('SCHEDULED');
            $table->decimal('opening_odometer_km', 12, 1)->nullable();
            $table->decimal('closing_odometer_km', 12, 1)->nullable();
            $table->decimal('distance_km', 12, 1)->nullable();
            $table->dateTime('actual_departure_at')->nullable();
            $table->dateTime('actual_arrival_at')->nullable();
            $table->text('operational_notes')->nullable();
            $table->text('incident_notes')->nullable();
            $table->text('not_operated_reason')->nullable();
            $table->unsignedInteger('reservation_count')->default(0);
            $table->unsignedInteger('boarded_count')->default(0);
            $table->unsignedInteger('no_show_count')->default(0);
            $table->unsignedInteger('unserved_waitlist_count')->default(0);
            $table->dateTime('finalized_at')->nullable();
            $table->foreignId('finalized_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['shuttle_schedule_id', 'travel_date'],
                'service_occurrence_schedule_date_unique'
            );
            $table->index(
                ['status', 'scheduled_departure_at'],
                'service_occurrence_status_departure_index'
            );
            $table->index(
                ['vehicle_id', 'scheduled_departure_at'],
                'service_occurrence_vehicle_departure_index'
            );
            $table->index(
                ['route_id', 'travel_date'],
                'service_occurrence_route_date_index'
            );
            $table->index(
                ['driver_id', 'travel_date'],
                'service_occurrence_driver_date_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shuttle_service_occurrences');
    }
};
