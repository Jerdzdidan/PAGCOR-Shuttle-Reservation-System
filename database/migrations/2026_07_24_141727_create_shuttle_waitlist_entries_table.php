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
        Schema::create('shuttle_waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees', 'employee_id')->restrictOnDelete();
            $table->foreignId('shuttle_schedule_id')->constrained('shuttle_schedules')->restrictOnDelete();
            $table->date('travel_date');
            $table->timestamp('queued_at');
            $table->timestamps();

            $table->unique(
                ['employee_id', 'shuttle_schedule_id', 'travel_date'],
                'waitlist_unique_employee'
            );
            $table->index(
                ['shuttle_schedule_id', 'travel_date', 'queued_at'],
                'waitlist_occurrence_order_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shuttle_waitlist_entries');
    }
};
