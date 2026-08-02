<?php

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
        $employeeCodes = DB::table('employees')
            ->whereNotNull('employee_code')
            ->pluck('employee_code', 'employee_id');

        foreach ($this->snapshotTables() as $table) {
            DB::table($table)
                ->select(['id', 'employee_id_snapshot'])
                ->whereNull('employee_code_snapshot')
                ->orderBy('id')
                ->chunkById(500, function (Collection $records) use ($employeeCodes, $table): void {
                    foreach ($records as $record) {
                        $employeeCode = $employeeCodes->get($record->employee_id_snapshot);

                        if ($employeeCode !== null) {
                            DB::table($table)
                                ->where('id', $record->id)
                                ->update(['employee_code_snapshot' => $employeeCode]);
                        }
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->snapshotTables() as $table) {
            DB::table($table)->update(['employee_code_snapshot' => null]);
        }
    }

    /** @return list<string> */
    private function snapshotTables(): array
    {
        return [
            'employee_login_logs',
            'shuttle_service_attendances',
            'shuttle_activity_events',
        ];
    }
};
