<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('final_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('curriculum_id')->constrained('curricula')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('rating_periods')->restrictOnDelete();
            $table->decimal('calculated_value', 8, 4);
            $table->boolean('is_admitted')->default(true);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(
                ['student_id', 'curriculum_id', 'period_id'],
                'final_ratings_student_curriculum_period_unique'
            );
            $table->index(['student_id', 'period_id']);
            $table->index(['curriculum_id', 'period_id']);
        });

        DB::statement('ALTER TABLE final_ratings ADD CONSTRAINT final_ratings_value_range_check CHECK (calculated_value BETWEEN 0 AND 90)');
    }

    public function down(): void
    {
        Schema::dropIfExists('final_ratings');
    }
};
