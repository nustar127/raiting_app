<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('work_type_id')->constrained('work_types')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->foreignId('period_id')->constrained('rating_periods')->restrictOnDelete();
            $table->smallInteger('value');
            $table->date('date_assigned');
            $table->smallInteger('weeks_late')->default(0);
            $table->timestamps();

            $table->unique(
                ['student_id', 'module_id', 'work_type_id', 'period_id'],
                'grades_student_module_work_period_unique'
            );
            $table->index(['student_id', 'period_id']);
            $table->index(['module_id']);
            $table->index(['teacher_id']);
        });

        DB::statement('ALTER TABLE grades ADD CONSTRAINT grades_value_range_check CHECK (value BETWEEN 0 AND 10)');
        DB::statement('ALTER TABLE grades ADD CONSTRAINT grades_weeks_late_check CHECK (weeks_late >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
