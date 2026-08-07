<?php

namespace App\Enums;

enum EmployeeLoginMethod: string
{
    case QrScan = 'QR_SCAN';
    case EmployeeCode = 'EMPLOYEE_CODE';

    public function label(): string
    {
        return match ($this) {
            self::QrScan => 'QR code',
            self::EmployeeCode => 'Employee ID',
        };
    }
}
