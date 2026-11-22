<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cumulative_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('rating_periods')->restrictOnDelete();
            $table->decimal('calculated_value', 8, 4);
            $table->smallInteger('disciplines_count');
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(
                ['student_id', 'period_id'],
                'cumulative_ratings_student_period_unique'
            );
            $table->index(['period_id', 'calculated_value']);
        });

        DB::statement('ALTER TABLE cumulative_ratings ADD CONSTRAINT cumulative_ratings_value_range_check CHECK (calculated_value BETWEEN 0 AND 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cumulative_ratings');
    }
};
