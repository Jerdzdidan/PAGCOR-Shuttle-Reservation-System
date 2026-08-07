<?php

namespace App\Services;

use App\Models\ShuttleRoute;
use Illuminate\Database\Eloquent\Model;

class RouteActivityData extends ServiceRunActivityData
{
    /** @return array<string, mixed> */
    public function forRoute(ShuttleRoute $route): array
    {
        return $this->build($route);
    }

    protected function subjectColumn(): string
    {
        return 'route_id';
    }

    /**
     * @param  ShuttleRoute  $subject
     * @return array{id: int, label: string, sublabel: string, status: string}
     */
    protected function subjectPayload(Model $subject): array
    {
        return [
            'id' => (int) $subject->getKey(),
            'label' => (string) $subject->name,
            'sublabel' => sprintf('%s → %s', $subject->origin, $subject->destination),
            'status' => (string) $subject->status,
        ];
    }
}
