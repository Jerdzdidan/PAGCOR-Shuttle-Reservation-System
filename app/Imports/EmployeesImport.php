<?php

namespace App\Imports;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class EmployeesImport implements SkipsEmptyRows, ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function model(array $row): ?Model
    {
        return new Employee([
            'name' => trim((string) $row['name']),
            'email' => Str::lower(trim((string) $row['email'])),
            'contact_number' => filled($row['contact_number'] ?? null) ? trim((string) $row['contact_number']) : null,
            'department' => filled($row['department'] ?? null) ? trim((string) $row['department']) : null,
            'position' => filled($row['position'] ?? null) ? trim((string) $row['position']) : null,
            'priority_status' => filled($row['priority_status'] ?? null) ? Str::upper(trim((string) $row['priority_status'])) : 'REGULAR',
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
            '*.department' => ['nullable', 'string', 'max:100'],
            '*.position' => ['nullable', 'string', 'max:100'],
            '*.priority_status' => ['nullable', 'string', 'in:REGULAR,PRIORITY'],
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
}
