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
        Schema::create('shuttle_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('booking_window_enabled')->default(false);
            $table->time('booking_window_opens_at')->nullable();
            $table->time('booking_window_closes_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shuttle_settings');
    }
};
