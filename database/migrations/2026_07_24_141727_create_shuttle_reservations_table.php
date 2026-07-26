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
        Schema::create('shuttle_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees', 'employee_id')->restrictOnDelete();
            $table->foreignId('shuttle_schedule_id')->constrained('shuttle_schedules')->restrictOnDelete();
            $table->date('travel_date');
            $table->unsignedSmallInteger('seat_number');
            $table->string('source', 20)->default('SELECTED');
            $table->timestamp('reserved_at');
            $table->timestamps();

            $table->unique(
                ['shuttle_schedule_id', 'travel_date', 'seat_number'],
                'reservation_unique_seat'
            );
            $table->unique(
                ['employee_id', 'shuttle_schedule_id', 'travel_date'],
                'reservation_unique_employee'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shuttle_reservations');
    }
};
