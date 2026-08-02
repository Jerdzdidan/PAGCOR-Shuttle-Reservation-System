<?php

namespace App\Enums;

enum AttendanceRecordingMethod: string
{
    case QrScan = 'QR_SCAN';
    case Manual = 'MANUAL';
    case Finalization = 'FINALIZATION';
}
