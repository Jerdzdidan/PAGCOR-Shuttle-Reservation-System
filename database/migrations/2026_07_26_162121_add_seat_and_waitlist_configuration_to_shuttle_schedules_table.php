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
        Schema::table('shuttle_schedules', function (Blueprint $table) {
            $table->json('priority_seats')->nullable()->after('capacity_override');
            $table->json('unavailable_seats')->nullable()->after('priority_seats');
            $table->boolean('waitlist_enabled')->default(true)->after('unavailable_seats');
            $table->unsignedSmallInteger('waitlist_capacity')->nullable()->after('waitlist_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shuttle_schedules', function (Blueprint $table) {
            $table->dropColumn([
                'priority_seats',
                'unavailable_seats',
                'waitlist_enabled',
                'waitlist_capacity',
            ]);
        });
    }
};
