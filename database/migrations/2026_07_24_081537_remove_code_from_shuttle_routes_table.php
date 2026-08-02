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
        Schema::table('shuttle_routes', function (Blueprint $table) {
            $table->dropUnique('shuttle_routes_code_unique');
            $table->dropColumn('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shuttle_routes', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->unique()->after('id');
        });
    }
};
