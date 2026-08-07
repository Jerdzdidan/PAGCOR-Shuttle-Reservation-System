<?php

use App\Models\ShuttleSetting;
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
        Schema::table('shuttle_settings', function (Blueprint $table) {
            $table->boolean('booking_window_enabled')
                ->default(ShuttleSetting::DEFAULT_BOOKING_WINDOW_ENABLED)
                ->change();
            $table->time('booking_window_opens_at')
                ->nullable()
                ->default(ShuttleSetting::DEFAULT_BOOKING_WINDOW_OPENS_AT)
                ->change();
            $table->time('booking_window_closes_at')
                ->nullable()
                ->default(ShuttleSetting::DEFAULT_BOOKING_WINDOW_CLOSES_AT)
                ->change();
        });

        ShuttleSetting::query()
            ->where('booking_window_enabled', false)
            ->whereNull('booking_window_opens_at')
            ->whereNull('booking_window_closes_at')
            ->update([
                'booking_window_enabled' => ShuttleSetting::DEFAULT_BOOKING_WINDOW_ENABLED,
                'booking_window_opens_at' => ShuttleSetting::DEFAULT_BOOKING_WINDOW_OPENS_AT,
                'booking_window_closes_at' => ShuttleSetting::DEFAULT_BOOKING_WINDOW_CLOSES_AT,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shuttle_settings', function (Blueprint $table) {
            $table->boolean('booking_window_enabled')->default(false)->change();
            $table->time('booking_window_opens_at')->nullable()->default(null)->change();
            $table->time('booking_window_closes_at')->nullable()->default(null)->change();
        });
    }
};
