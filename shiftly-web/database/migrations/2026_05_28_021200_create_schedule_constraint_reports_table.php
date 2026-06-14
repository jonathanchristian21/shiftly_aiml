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
        // Ringkasan required vs actual untuk validasi constraint department-shift.
        Schema::create('schedule_constraint_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_candidate_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('department_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->date('shift_date');
            $table->enum('shift', ['Pagi', 'Sore', 'Malam']);
            $table->unsignedSmallInteger('required_staff')->default(0);
            $table->unsignedSmallInteger('actual_staff')->default(0);
            $table->unsignedSmallInteger('required_senior')->default(0);
            $table->unsignedSmallInteger('actual_senior')->default(0);
            $table->boolean('has_hard_violation')->default(false);
            $table->timestamps();

            $table->unique(
                ['schedule_candidate_id', 'department_id', 'shift_date', 'shift'],
                'sched_constraint_candidate_dept_date_shift_unique'
            );
            $table->index(['department_id', 'shift_date', 'shift'], 'sched_constraint_dept_date_shift_index');
            $table->index('has_hard_violation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_constraint_reports');
    }
};
