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
        Schema::table('shuttle_service_occurrences', function (Blueprint $table) {
            $table->index(
                'travel_date',
                'service_occurrence_travel_date_index'
            );
        });
        Schema::table('shuttle_activity_events', function (Blueprint $table) {
            $table->index(
                'occurred_at',
                'activity_occurred_at_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shuttle_activity_events', function (Blueprint $table) {
            $table->dropIndex('activity_occurred_at_index');
        });
        Schema::table('shuttle_service_occurrences', function (Blueprint $table) {
            $table->dropIndex('service_occurrence_travel_date_index');
        });
    }
};
