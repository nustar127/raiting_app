<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculties')->restrictOnDelete();
            $table->string('name', 150);
            $table->smallInteger('academic_year');           // 2025 = 2025/2026
            $table->smallInteger('semester');                // 1 или 2
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('closed'); // open|closed
            $table->timestamps();

            $table->unique(
                ['faculty_id', 'academic_year', 'semester'],
                'rating_periods_faculty_year_sem_unique'
            );
            $table->index(['faculty_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_periods');
    }
};
