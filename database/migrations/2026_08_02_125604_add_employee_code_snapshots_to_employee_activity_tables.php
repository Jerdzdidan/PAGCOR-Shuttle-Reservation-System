<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employee_login_logs', function (Blueprint $table) {
            $table->string('employee_code_snapshot', 8)
                ->nullable()
                ->after('employee_id_snapshot');
        });

        Schema::table('shuttle_service_attendances', function (Blueprint $table) {
            $table->string('employee_code_snapshot', 8)
                ->nullable()
                ->after('employee_id_snapshot');
        });

        Schema::table('shuttle_activity_events', function (Blueprint $table) {
            $table->string('employee_code_snapshot', 8)
                ->nullable()
                ->after('employee_id_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_login_logs', function (Blueprint $table) {
            $table->dropColumn('employee_code_snapshot');
        });

        Schema::table('shuttle_service_attendances', function (Blueprint $table) {
            $table->dropColumn('employee_code_snapshot');
        });

        Schema::table('shuttle_activity_events', function (Blueprint $table) {
            $table->dropColumn('employee_code_snapshot');
        });
    }
};
