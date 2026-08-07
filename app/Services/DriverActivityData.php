<?php

namespace App\Services;

use App\Models\Driver;
use Illuminate\Database\Eloquent\Model;

class DriverActivityData extends ServiceRunActivityData
{
    /** @return array<string, mixed> */
    public function forDriver(Driver $driver): array
    {
        return $this->build($driver);
    }

    protected function subjectColumn(): string
    {
        return 'driver_id';
    }

    /**
     * @param  Driver  $subject
     * @return array{id: int, label: string, sublabel: string, status: string}
     */
    protected function subjectPayload(Model $subject): array
    {
        return [
            'id' => (int) $subject->getKey(),
            'label' => (string) $subject->name,
            'sublabel' => sprintf('%s · License %s', $subject->employee_id, $subject->license_number),
            'status' => (string) $subject->status,
        ];
    }
}
