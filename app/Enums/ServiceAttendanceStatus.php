<?php

namespace App\Enums;

enum ServiceAttendanceStatus: string
{
    case Boarded = 'BOARDED';
    case NoShow = 'NO_SHOW';
    case ServiceNotOperated = 'SERVICE_NOT_OPERATED';
}
