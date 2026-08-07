<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the employee roster.
 *
 * The first rows are hand-written personas with stable e-mail addresses so the
 * operations seeder can force them into a known state (booked today, waitlisted,
 * deactivated, and so on). Everything after that is generated bulk staff used to
 * fill shuttles, paginate tables, and give the reports something to aggregate.
 */
class EmployeeSeeder extends Seeder
{
    /** Rides almost every day; use for the employee activity / history screens. */
    public const PERSONA_HEAVY_HISTORY = 'jdgonzdayao@gmail.com';

    /** Holds a seat on a departure that has not left yet today. */
    public const PERSONA_TODAY_UPCOMING = 'maria.santos@pagcor.example';

    /** Already scanned onto a service that is awaiting closeout today. */
    public const PERSONA_TODAY_BOARDED = 'jose.reyes@pagcor.example';

    /** Queued on a full shuttle today. */
    public const PERSONA_TODAY_WAITLIST = 'teresa.morales@pagcor.example';

    /** Only holds reservations on future travel dates. */
    public const PERSONA_FUTURE_ONLY = 'ana.cruz@pagcor.example';

    /** Never books; a clean slate for walking through the booking flow. */
    public const PERSONA_NEVER_BOOKS = 'grace.villanueva@pagcor.example';

    /** Priority tier with no bookings; can claim priority-only seats. */
    public const PERSONA_PRIORITY_FREE = 'liza.garcia@pagcor.example';

    /** Deactivated with no history at all. */
    public const PERSONA_DEACTIVATED = 'elena.flores@pagcor.example';

    /** Deactivated after building up trip history. */
    public const PERSONA_DEACTIVATED_WITH_HISTORY = 'adrian.lopez@pagcor.example';

    /** Frequently misses the shuttle; skews the no-show reports. */
    public const PERSONA_FREQUENT_NO_SHOW = 'paolo.aquino@pagcor.example';

    private const ACTIVE_EMPLOYEE_COUNT = 780;

    private const INACTIVE_EMPLOYEE_COUNT = 40;

    /**
     * Personas whose current-day and future state is scripted by the operations
     * seeder rather than drawn at random.
     *
     * @return list<string>
     */
    public static function scriptedPersonaEmails(): array
    {
        return [
            self::PERSONA_TODAY_UPCOMING,
            self::PERSONA_TODAY_BOARDED,
            self::PERSONA_TODAY_WAITLIST,
            self::PERSONA_FUTURE_ONLY,
            self::PERSONA_NEVER_BOOKS,
            self::PERSONA_PRIORITY_FREE,
            self::PERSONA_HEAVY_HISTORY,
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = collect($this->personas());
        $personaCount = $employees->count();

        for (
            $sequence = $personaCount + 1;
            $sequence <= self::ACTIVE_EMPLOYEE_COUNT + self::INACTIVE_EMPLOYEE_COUNT;
            $sequence++
        ) {
            $employees->push($this->generatedEmployee(
                $sequence,
                isActive: $sequence <= self::ACTIVE_EMPLOYEE_COUNT,
            ));
        }

        DB::transaction(function () use ($employees): void {
            foreach ($employees as $employee) {
                Employee::query()->updateOrCreate(
                    ['email' => $employee['email']],
                    $employee,
                );
            }
        });
    }

    /**
     * Hand-written employees covering the states that are awkward to hit at random.
     *
     * @return list<array{
     *     name: string,
     *     email: string,
     *     contact_number: ?string,
     *     department: ?string,
     *     position: ?string,
     *     priority_status: string,
     *     status: string
     * }>
     */
    private function personas(): array
    {
        return [
            $this->persona(
                'Jerdan Gondayao',
                self::PERSONA_HEAVY_HISTORY,
                '09171230001',
                'Information Technology',
                'Application Developer',
                Employee::PRIORITY_STATUS_PRIORITY,
            ),
            $this->persona(
                'Maria Santos',
                self::PERSONA_TODAY_UPCOMING,
                '09171230002',
                'Human Resource and Development',
                'HR Manager',
                Employee::PRIORITY_STATUS_PRIORITY,
            ),
            $this->persona(
                'Jose Reyes',
                self::PERSONA_TODAY_BOARDED,
                '09171230003',
                'Information Technology',
                'IT Manager',
            ),
            $this->persona(
                'Teresa Morales',
                self::PERSONA_TODAY_WAITLIST,
                '09171230004',
                'Gaming Licensing and Development',
                'Licensing Specialist',
            ),
            $this->persona(
                'Ana Cruz',
                self::PERSONA_FUTURE_ONLY,
                '09171230005',
                'Finance and Treasury',
                'Finance Analyst',
            ),
            $this->persona(
                'Grace Villanueva',
                self::PERSONA_NEVER_BOOKS,
                '09171230006',
                'Finance and Treasury',
                'Accountant',
            ),
            $this->persona(
                'Liza Garcia',
                self::PERSONA_PRIORITY_FREE,
                '09171230007',
                'Legal Services',
                'Legal Officer',
                Employee::PRIORITY_STATUS_PRIORITY,
            ),
            $this->persona(
                'Elena Flores',
                self::PERSONA_DEACTIVATED,
                '09171230008',
                'Corporate Services',
                'Administrative Officer',
                status: Employee::STATUS_INACTIVE,
            ),
            $this->persona(
                'Adrian Lopez',
                self::PERSONA_DEACTIVATED_WITH_HISTORY,
                '09171230009',
                'Corporate Services',
                'Records Officer',
                status: Employee::STATUS_INACTIVE,
            ),
            $this->persona(
                'Paolo Aquino',
                self::PERSONA_FREQUENT_NO_SHOW,
                '09171230010',
                'Procurement Services',
                'Procurement Officer',
            ),
            $this->persona(
                'Carlo Mendoza',
                'carlo.mendoza@pagcor.example',
                '09171230011',
                'Security and Surveillance',
                'Security Supervisor',
            ),
            $this->persona(
                'Ramon Bautista',
                'ramon.bautista@pagcor.example',
                '09171230012',
                'Transportation Services',
                'Transport Coordinator',
            ),
            $this->persona(
                'Cecilia Dimaano',
                'cecilia.dimaano@pagcor.example',
                '09171230013',
                'Transportation Services',
                'Shuttle Dispatcher',
                Employee::PRIORITY_STATUS_PRIORITY,
            ),
            /* Nullable columns left empty on purpose. */
            $this->persona(
                'Miguel Navarro',
                'miguel.navarro@pagcor.example',
                null,
                'Information Technology',
                null,
            ),
            $this->persona(
                'Bernadette Ocampo',
                'bernadette.ocampo@pagcor.example',
                '09171230015',
                null,
                'Consultant',
            ),
            /* Accented and unusually long names for table layout and CSV export. */
            $this->persona(
                'María Peña-Villaruel',
                'maria.pena@pagcor.example',
                '09171230016',
                'Corporate Communications',
                'Communications Officer',
            ),
            $this->persona(
                'Ma. Rosario Concepcion Dimaculangan-Bumatay',
                'rosario.dimaculangan@pagcor.example',
                '09171230017',
                'Internal Audit',
                'Senior Internal Audit Specialist for Gaming Operations',
                Employee::PRIORITY_STATUS_PRIORITY,
            ),
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     email: string,
     *     contact_number: ?string,
     *     department: ?string,
     *     position: ?string,
     *     priority_status: string,
     *     status: string
     * }
     */
    private function persona(
        string $name,
        string $email,
        ?string $contactNumber,
        ?string $department,
        ?string $position,
        string $priorityStatus = Employee::PRIORITY_STATUS_REGULAR,
        string $status = Employee::STATUS_ACTIVE,
    ): array {
        return [
            'name' => $name,
            'email' => $email,
            'contact_number' => $contactNumber,
            'department' => $department,
            'position' => $position,
            'priority_status' => $priorityStatus,
            'status' => $status,
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     email: string,
     *     contact_number: string,
     *     department: string,
     *     position: string,
     *     priority_status: string,
     *     status: string
     * }
     */
    private function generatedEmployee(int $sequence, bool $isActive): array
    {
        $firstNames = [
            'Adrian', 'Althea', 'Amado', 'Beatriz', 'Carmela', 'Dante',
            'Divina', 'Emilio', 'Esperanza', 'Felix', 'Gloria', 'Isabel',
            'Joaquin', 'Katrina', 'Leandro', 'Ligaya', 'Marcelo', 'Marites',
            'Nicanor', 'Nina', 'Orlando', 'Precious', 'Rafael', 'Rosario',
            'Salvador', 'Teodoro', 'Ursula', 'Vicente', 'Wilfredo', 'Yolanda',
        ];
        $lastNames = [
            'Abad', 'Agbayani', 'Alonzo', 'Balagtas', 'Cabral', 'Dimaculangan',
            'Evangelista', 'Gatchalian', 'Hilario', 'Lacson', 'Macapagal',
            'Manalo', 'Panganiban', 'Samonte', 'Tolentino', 'Tuazon',
            'Villareal', 'Yabut', 'Zamora', 'Buenaventura', 'Escalante',
            'Fajardo', 'Ignacio', 'Javier', 'Katigbak', 'Lumbera', 'Mabini',
        ];
        $assignments = [
            ['department' => 'Human Resource and Development', 'position' => 'HR Specialist'],
            ['department' => 'Information Technology', 'position' => 'Systems Analyst'],
            ['department' => 'Finance and Treasury', 'position' => 'Accounting Specialist'],
            ['department' => 'Security and Surveillance', 'position' => 'Security Officer'],
            ['department' => 'Legal Services', 'position' => 'Legal Assistant'],
            ['department' => 'Corporate Services', 'position' => 'Administrative Assistant'],
            ['department' => 'Gaming Licensing and Development', 'position' => 'Licensing Associate'],
            ['department' => 'Internal Audit', 'position' => 'Audit Associate'],
            ['department' => 'Procurement Services', 'position' => 'Procurement Associate'],
            ['department' => 'Corporate Communications', 'position' => 'Communications Associate'],
            ['department' => 'Responsible Gaming', 'position' => 'Program Officer'],
            ['department' => 'Regulatory Compliance', 'position' => 'Compliance Analyst'],
            ['department' => 'Transportation Services', 'position' => 'Fleet Assistant'],
        ];
        $zeroBasedSequence = $sequence - 1;
        $assignment = $assignments[$zeroBasedSequence % count($assignments)];

        return [
            'name' => $firstNames[$zeroBasedSequence % count($firstNames)]
                .' '
                .$lastNames[intdiv($zeroBasedSequence, count($firstNames)) % count($lastNames)],
            'email' => sprintf('employee.%04d@pagcor.example', $sequence),
            'contact_number' => sprintf('0921%07d', $sequence),
            'department' => $assignment['department'],
            'position' => $assignment['position'],
            'priority_status' => $this->isPriorityEmployee($sequence)
                ? Employee::PRIORITY_STATUS_PRIORITY
                : Employee::PRIORITY_STATUS_REGULAR,
            'status' => $isActive
                ? Employee::STATUS_ACTIVE
                : Employee::STATUS_INACTIVE,
        ];
    }

    /**
     * Roughly one in seven employees belongs to the priority tier, which is enough
     * to keep priority seats contested without swamping the regular allocation.
     */
    private function isPriorityEmployee(int $sequence): bool
    {
        return in_array($sequence % 21, [1, 6, 13], true);
    }
}
