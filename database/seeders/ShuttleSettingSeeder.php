<?php

namespace Database\Seeders;

use App\Models\ShuttleSetting;
use Illuminate\Database\Seeder;

/**
 * The booking window is seeded open (disabled) so the employee portal can be
 * explored at any hour. Enable it from Admin → Schedules to exercise the locked
 * state of the employee schedules page.
 */
class ShuttleSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ShuttleSetting::current()->forceFill([
            'booking_window_enabled' => false,
            'booking_window_opens_at' => '06:00:00',
            'booking_window_closes_at' => '20:00:00',
        ])->save();
    }
}
