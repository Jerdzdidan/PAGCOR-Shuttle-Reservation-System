<?php

namespace Database\Seeders;

use App\Models\Driver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Drivers are tracked separately from employees: the `employee_id` column on the
 * drivers table is a free-text employee/contractor reference, not a foreign key.
 */
class DriverSeeder extends Seeder
{
    /** License already lapsed; surfaces the expiry warning in the driver list. */
    public const EXPIRED_LICENSE_DRIVER = 'Hernando Yabut';

    /** License lapses within the month. */
    public const EXPIRING_LICENSE_DRIVER = 'Isagani Laurel';

    /** Currently off the roster, so no active schedule may reference them. */
    public const INACTIVE_DRIVERS = ['Joaquin Panganiban', 'Leopoldo Tuazon'];

    /** Active but never rostered; shows as an idle driver in the utilization report. */
    public const UNASSIGNED_DRIVER = 'Nestor Cabral';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $today = CarbonImmutable::now(
            (string) config('shuttle.operating_timezone', 'Asia/Manila')
        )->startOfDay();

        foreach ($this->drivers() as $index => $driver) {
            $sequence = $index + 1;

            Driver::query()->updateOrCreate(
                ['license_number' => sprintf('N01-18-%06d', 100000 + $sequence)],
                [
                    'name' => $driver['name'],
                    'employee_id' => $driver['employee_id'],
                    'contact_number' => sprintf('0918%07d', 1230000 + $sequence),
                    'license_expires_at' => $today
                        ->addDays($driver['license_expires_in_days'])
                        ->toDateString(),
                    'status' => $driver['status'],
                    'notes' => $driver['notes'],
                ],
            );
        }
    }

    /**
     * @return list<array{
     *     name: string,
     *     employee_id: string,
     *     license_expires_in_days: int,
     *     status: string,
     *     notes: ?string
     * }>
     */
    private function drivers(): array
    {
        $names = [
            'Roberto Dela Cruz',
            'Eduardo Ramos',
            'Antonio Castillo',
            'Noel Fernandez',
            'Jaime Torres',
            'Danilo Mercado',
            'Manuel Salazar',
            'Renato Domingo',
            'Victor Soriano',
            'Alfredo Valdez',
            'Benjamin Lim',
            'Ricardo Pascual',
            'Andres Villareal',
            'Cesar Manalo',
            'Domingo Evangelista',
            'Ernesto Macapagal',
            'Felipe Tolentino',
            'Gregorio Abad',
            self::EXPIRED_LICENSE_DRIVER,
            self::EXPIRING_LICENSE_DRIVER,
            self::INACTIVE_DRIVERS[0],
            self::INACTIVE_DRIVERS[1],
            'Maximo Samonte',
            self::UNASSIGNED_DRIVER,
            'Perfecto Bandoy',
            'Rogelio Sarmiento',
            'Teofilo Aguinaldo',
            'Ulysses Bituin',
            'Valentin Carreon',
            'Wilfredo Doronila',
        ];
        $drivers = [];

        foreach ($names as $index => $name) {
            $sequence = $index + 1;
            $drivers[] = [
                'name' => $name,
                /* Contractors are identified with a CTR prefix, staff drivers with TS. */
                'employee_id' => $sequence % 8 === 0
                    ? sprintf('CTR-%04d', $sequence)
                    : sprintf('TS-%04d', $sequence),
                'license_expires_in_days' => match ($name) {
                    self::EXPIRED_LICENSE_DRIVER => -34,
                    self::EXPIRING_LICENSE_DRIVER => 12,
                    default => 240 + ($sequence * 37) % 900,
                },
                'status' => in_array($name, self::INACTIVE_DRIVERS, true)
                    ? 'INACTIVE'
                    : 'ACTIVE',
                'notes' => match (true) {
                    $name === self::EXPIRED_LICENSE_DRIVER => 'License renewal pending at the LTO.',
                    $name === self::EXPIRING_LICENSE_DRIVER => 'Reminded to renew before the expiry date.',
                    $name === self::INACTIVE_DRIVERS[0] => 'On extended medical leave.',
                    $name === self::INACTIVE_DRIVERS[1] => 'Resigned; retained for historical records.',
                    $name === self::UNASSIGNED_DRIVER => 'Relief driver, not yet rostered.',
                    $sequence % 5 === 0 => null,
                    default => 'Assigned to PAGCOR employee shuttle service.',
                },
            ];
        }

        return $drivers;
    }
}
