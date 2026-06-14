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
        // Kandidat jadwal hasil GA; RF menambahkan profit score untuk perbandingan manager.
        Schema::create('schedule_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_run_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('candidate_code', 16);
            $table->decimal('ga_fitness', 12, 4);
            $table->decimal('rf_profit_score', 8, 4)->nullable();
            $table->decimal('total_salary', 14, 2);
            $table->unsignedSmallInteger('active_employees');
            $table->unsignedSmallInteger('total_assignments');
            $table->decimal('cluster_balance', 8, 4)->nullable();
            $table->unsignedSmallInteger('hard_violation_count')->default(0);
            $table->unsignedSmallInteger('soft_violation_count')->default(0);
            $table->unsignedSmallInteger('consecutive_shift_violations')->default(0);
            $table->unsignedSmallInteger('one_shift_per_day_violations')->default(0);
            $table->unsignedSmallInteger('weekly_day_off_violations')->default(0);
            $table->json('shift_counts')->nullable();
            $table->enum('status', ['candidate', 'selected', 'discarded'])->default('candidate');
            $table->timestamps();

            $table->unique(['schedule_run_id', 'candidate_code']);
            $table->index(['schedule_run_id', 'status']);
            $table->index('rf_profit_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_candidates');
    }
};
