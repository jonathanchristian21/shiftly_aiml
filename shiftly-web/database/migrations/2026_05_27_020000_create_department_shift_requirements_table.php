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
        // Constraint minimum staff/senior per department dan shift.
        Schema::create('department_shift_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->enum('shift', ['Pagi', 'Sore', 'Malam']);
            $table->unsignedSmallInteger('required_staff')->default(0);
            $table->unsignedSmallInteger('required_senior')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'shift']);
            $table->index(['shift', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_shift_requirements');
    }
};
