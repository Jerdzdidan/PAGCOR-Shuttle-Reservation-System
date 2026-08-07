<?php

namespace App\Enums;

enum ShuttleActivityEventType: string
{
    case ReservationCreated = 'RESERVATION_CREATED';
    case ReservationCancelled = 'RESERVATION_CANCELLED';

    case ReservationSeatChanged = 'RESERVATION_SEAT_CHANGED';
    case WaitlistJoined = 'WAITLIST_JOINED';
    case WaitlistWithdrawn = 'WAITLIST_WITHDRAWN';
    case WaitlistPromoted = 'WAITLIST_PROMOTED';
    case WaitlistUnserved = 'WAITLIST_UNSERVED';
}
