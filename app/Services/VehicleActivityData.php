<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

class VehicleActivityData extends ServiceRunActivityData
{
    /** @return array<string, mixed> */
    public function forVehicle(Vehicle $vehicle): array
    {
        return $this->build($vehicle);
    }

    protected function subjectColumn(): string
    {
        return 'vehicle_id';
    }

    /**
     * @param  Vehicle  $subject
     * @return array{id: int, label: string, sublabel: string, status: string}
     */
    protected function subjectPayload(Model $subject): array
    {
        return [
            'id' => (int) $subject->getKey(),
            'label' => (string) $subject->plate_number,
            'sublabel' => sprintf('%s · %d seats', $subject->vehicle_type, (int) $subject->capacity),
            'status' => (string) $subject->status,
        ];
    }
}
