<?php

return [
    'operating_timezone' => env('SHUTTLE_OPERATING_TIMEZONE', 'Asia/Manila'),
    'booking_horizon_days' => 30,
    'priority_seat_count' => 8,

    /*
     * Grace period used by reporting to judge whether a service departed on time.
     */
    'on_time_threshold_minutes' => 5,
];
