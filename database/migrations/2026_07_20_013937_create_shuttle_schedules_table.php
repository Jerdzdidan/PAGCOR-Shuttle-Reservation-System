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
        Schema::create('shuttle_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('shuttle_routes')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->string('direction', 20);
            $table->time('departure_time');
            $table->json('operating_days');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->unsignedSmallInteger('capacity_override')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->index(['route_id', 'vehicle_id', 'direction', 'departure_time', 'status'], 'schedule_lookup_index');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shuttle_schedules');
    }
};
