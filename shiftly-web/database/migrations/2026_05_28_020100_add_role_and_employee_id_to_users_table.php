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
        Schema::table('users', function (Blueprint $table) {
            // role menentukan arah dashboard; employee_id menghubungkan akun ke profil employee.
            $table->enum('role', ['manager', 'employee'])->default('employee')->after('password');
            $table->foreignId('employee_id')
                ->nullable()
                ->after('role')
                ->constrained()
                ->nullOnDelete();

            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'employee_id']);
        });
    }
};
