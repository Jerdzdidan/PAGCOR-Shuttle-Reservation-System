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
        Schema::create('shuttle_service_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shuttle_service_occurrence_id')
                ->constrained(
                    'shuttle_service_occurrences',
                    'id',
                    'service_correction_occurrence_foreign'
                )
                ->cascadeOnDelete();
            $table->foreignId('corrected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('action', 20)->default('CORRECTION');
            $table->text('reason');
            $table->json('before_values');
            $table->json('after_values');
            $table->dateTime('corrected_at');
            $table->timestamps();

            $table->index(
                ['shuttle_service_occurrence_id', 'corrected_at'],
                'service_correction_occurrence_time_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shuttle_service_corrections');
    }
};
