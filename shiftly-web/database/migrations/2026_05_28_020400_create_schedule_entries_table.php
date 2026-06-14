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
        // Assignment shift final/draft per employee per tanggal.
        Schema::create('schedule_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_candidate_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('department_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->date('shift_date');
            $table->enum('shift', ['Pagi', 'Sore', 'Malam', 'Libur']);
            $table->unsignedTinyInteger('cluster_label')->nullable();
            $table->boolean('is_senior_snapshot')->default(false);
            $table->decimal('salary_snapshot', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['schedule_candidate_id', 'employee_id', 'shift_date'], 'sched_entries_candidate_employee_date_unique');
            $table->index(['employee_id', 'shift_date'], 'sched_entries_employee_date_index');
            $table->index(['department_id', 'shift_date', 'shift'], 'sched_entries_dept_date_shift_index');
            $table->index(['shift_date', 'shift'], 'sched_entries_date_shift_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_entries');
    }
};
