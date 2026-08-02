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
        Schema::create('shuttle_service_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shuttle_service_occurrence_id')
                ->constrained(
                    'shuttle_service_occurrences',
                    'id',
                    'service_attendance_occurrence_foreign'
                )
                ->cascadeOnDelete();
            $table->foreignId('shuttle_reservation_id')
                ->nullable()
                ->constrained('shuttle_reservations')
                ->nullOnDelete();
            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employees', 'employee_id')
                ->nullOnDelete();
            $table->unsignedBigInteger('employee_id_snapshot');
            $table->string('employee_name');
            $table->string('department', 100)->nullable();
            $table->string('priority_status', 20);
            $table->unsignedSmallInteger('seat_number');
            $table->string('status', 30);
            $table->string('recording_method', 20);
            $table->dateTime('boarded_at')->nullable();
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['shuttle_service_occurrence_id', 'employee_id_snapshot'],
                'service_attendance_occurrence_employee_unique'
            );
            $table->unique(
                ['shuttle_service_occurrence_id', 'shuttle_reservation_id'],
                'service_attendance_occurrence_reservation_unique'
            );
            $table->index(
                ['status', 'created_at'],
                'service_attendance_status_created_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shuttle_service_attendances');
    }
};
