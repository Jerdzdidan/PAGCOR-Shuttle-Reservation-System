<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('employees')
            ->select(['employee_id', 'created_at'])
            ->whereNull('employee_code')
            ->orderBy('employee_id')
            ->chunkById(500, function (Collection $employees): void {
                foreach ($employees as $employee) {
                    $year = CarbonImmutable::parse($employee->created_at ?? now())->format('y');

                    DB::table('employees')
                        ->where('employee_id', $employee->employee_id)
                        ->update([
                            'employee_code' => sprintf('%s-%05d', $year, $employee->employee_id),
                        ]);
                }
            }, 'employee_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('employees')->update(['employee_code' => null]);
    }
};
