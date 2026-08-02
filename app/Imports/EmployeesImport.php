<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class EmployeesImport implements SkipsEmptyRows, ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    /** @var array<string, string> */
    private array $departmentsByNormalizedName;

    public function __construct()
    {
        $this->departmentsByNormalizedName = Department::query()
            ->pluck('name')
            ->mapWithKeys(fn (string $name): array => [Str::lower($name) => $name])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function model(array $row): ?Model
    {
        return new Employee([
            'name' => trim((string) $row['name']),
            'email' => Str::lower(trim((string) $row['email'])),
            'contact_number' => filled($row['contact_number'] ?? null) ? trim((string) $row['contact_number']) : null,
            'department' => $this->departmentName($row['department'] ?? null),
            'position' => filled($row['position'] ?? null) ? trim((string) $row['position']) : null,
            'priority_status' => filled($row['priority_status'] ?? null) ? Str::upper(trim((string) $row['priority_status'])) : 'REGULAR',
            'status' => filled($row['status'] ?? null) ? Str::upper(trim((string) $row['status'])) : 'ACTIVE',
        ]);
    }

    /**
     * @return array<string, string|array<mixed>>
     */
    public function rules(): array
    {
        return [
            '*.name' => ['required', 'string', 'max:255'],
            '*.email' => ['required', 'email', 'max:255', 'distinct', 'unique:employees,email'],
            '*.contact_number' => ['nullable', 'max:30'],
            '*.department' => ['nullable', 'string', 'max:100', Rule::exists(Department::class, 'name')],
            '*.position' => ['nullable', 'string', 'max:100'],
            '*.priority_status' => ['nullable', 'string', 'in:REGULAR,PRIORITY'],
            '*.status' => ['nullable', 'string', 'in:ACTIVE,INACTIVE'],
        ];
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    private function departmentName(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $department = trim((string) $value);

        return $this->departmentsByNormalizedName[Str::lower($department)] ?? $department;
    }
}
