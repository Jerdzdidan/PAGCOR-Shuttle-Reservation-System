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
            $table->string('login_method', 20)
                ->nullable()
                ->after('priority_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_login_logs', function (Blueprint $table) {
            $table->dropColumn('login_method');
        });
    }
};
