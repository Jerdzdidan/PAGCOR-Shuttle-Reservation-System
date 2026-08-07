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
        Schema::create('user_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedBigInteger('user_id_snapshot')->nullable();
            $table->string('user_name');
            $table->string('user_email')->nullable();
            $table->string('action', 20);
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label');
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('occurred_at');

            $table->index(['occurred_at', 'action'], 'user_log_time_action_index');
            $table->index(['subject_type', 'subject_id'], 'user_log_subject_index');
            $table->index(['user_id_snapshot', 'occurred_at'], 'user_log_user_time_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_logs');
    }
};
