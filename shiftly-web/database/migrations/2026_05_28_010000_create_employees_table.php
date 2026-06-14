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
        // Data employee dari CSV atau input manual, termasuk label cluster AI.
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 32)->unique();
            $table->string('name')->nullable();
            $table->unsignedTinyInteger('age');
            $table->foreignId('department_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('location')->nullable();
            $table->string('education', 16);
            $table->string('recruitment_type')->nullable();
            $table->unsignedTinyInteger('job_level');
            $table->unsignedTinyInteger('rating');
            $table->boolean('onsite')->default(false);
            $table->unsignedSmallInteger('awards')->default(0);
            $table->unsignedSmallInteger('certifications')->default(0);
            $table->decimal('salary', 12, 2);
            $table->boolean('satisfied')->nullable();
            $table->unsignedTinyInteger('cluster_label')->nullable();
            $table->timestamp('clustered_at')->nullable();
            // Cache senior dari education PG agar query constraint lebih cepat.
            $table->boolean('is_senior')->default(false);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['department_id', 'education', 'job_level']);
            $table->index('cluster_label');
            $table->index('is_senior');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
