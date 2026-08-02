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
        Schema::create('shuttle_activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employees', 'employee_id')
                ->nullOnDelete();
            $table->foreignId('shuttle_schedule_id')
                ->nullable()
                ->constrained('shuttle_schedules')
                ->nullOnDelete();
            $table->foreignId('shuttle_service_occurrence_id')
                ->nullable()
                ->constrained('shuttle_service_occurrences')
                ->nullOnDelete();
            $table->date('travel_date');
            $table->string('event_type', 40);
            $table->unsignedSmallInteger('seat_number')->nullable();
            $table->dateTime('occurred_at');
            $table->string('employee_name');
            $table->string('employee_priority_status', 20);
            $table->json('metadata')->nullable();

            $table->index(
                ['shuttle_schedule_id', 'travel_date', 'event_type'],
                'activity_schedule_date_type_index'
            );
            $table->index(
                ['employee_id', 'occurred_at'],
                'activity_employee_time_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shuttle_activity_events');
    }
};
