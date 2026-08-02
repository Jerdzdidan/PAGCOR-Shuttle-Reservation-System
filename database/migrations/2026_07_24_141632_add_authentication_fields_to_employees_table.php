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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('priority_status', 20)
                ->default('REGULAR')
                ->index()
                ->after('position');
            $table->unsignedInteger('qr_token_version')
                ->default(1)
                ->after('priority_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->dropIndex('employees_priority_status_index');
            $table->dropColumn(['priority_status', 'qr_token_version']);
        });
    }
};
