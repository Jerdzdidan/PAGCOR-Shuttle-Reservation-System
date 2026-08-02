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
        Schema::create('employee_login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employees', 'employee_id')
                ->nullOnDelete();
            $table->unsignedBigInteger('employee_id_snapshot');
            $table->string('employee_name');
            $table->string('department', 100)->nullable();
            $table->string('priority_status', 20);
            $table->dateTime('logged_in_at');

            $table->index(
                ['logged_in_at', 'employee_id'],
                'employee_login_time_employee_index'
            );
            $table->index(
                ['employee_id_snapshot', 'logged_in_at'],
                'employee_login_snapshot_time_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_login_logs');
    }
};
