<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('rating_periods')->restrictOnDelete();
            $table->decimal('calculated_value', 8, 4);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(
                ['student_id', 'module_id', 'period_id'],
                'module_ratings_student_module_period_unique'
            );
            $table->index(['student_id', 'period_id']);
        });

        DB::statement('ALTER TABLE module_ratings ADD CONSTRAINT module_ratings_value_range_check CHECK (calculated_value BETWEEN 0 AND 90)');
    }

    public function down(): void
    {
        Schema::dropIfExists('module_ratings');
    }
};
