<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weight_coefficients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('rating_periods')->restrictOnDelete();
            $table->foreignId('curriculum_id')->constrained('curricula')->cascadeOnDelete();
            $table->string('level', 20);
            $table->foreignId('module_id')->nullable()->constrained('modules')->cascadeOnDelete();
            $table->foreignId('work_type_id')->nullable()->constrained('work_types')->restrictOnDelete();
            $table->decimal('weight_value', 5, 4);
            $table->date('deadline_date')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        DB::statement("
        ALTER TABLE weight_coefficients
        ADD CONSTRAINT wc_level_check
        CHECK (level IN ('discipline','module','work'))
    ");

        DB::statement("CREATE UNIQUE INDEX wc_discipline_unique ON weight_coefficients (period_id, curriculum_id) WHERE level = 'discipline'");
        DB::statement("CREATE UNIQUE INDEX wc_module_unique ON weight_coefficients (period_id, curriculum_id, module_id) WHERE level = 'module'");
        DB::statement("CREATE UNIQUE INDEX wc_work_unique ON weight_coefficients (period_id, curriculum_id, module_id, work_type_id) WHERE level = 'work'");

        DB::statement("
        ALTER TABLE weight_coefficients
        ADD CONSTRAINT wc_level_module_check
        CHECK (
            (level <> 'module')
            OR (level = 'module' AND module_id IS NOT NULL AND work_type_id IS NULL)
        )
    ");

        DB::statement("
        ALTER TABLE weight_coefficients
        ADD CONSTRAINT wc_level_work_check
        CHECK (
            (level <> 'work')
            OR (level = 'work' AND module_id IS NOT NULL AND work_type_id IS NOT NULL)
        )
    ");

        DB::statement("
        ALTER TABLE weight_coefficients
        ADD CONSTRAINT wc_level_discipline_check
        CHECK (
            (level <> 'discipline')
            OR (level = 'discipline' AND module_id IS NULL AND work_type_id IS NULL)
        )
    ");

        DB::statement("
        ALTER TABLE weight_coefficients
        ADD CONSTRAINT wc_value_range_check
        CHECK (weight_value > 0 AND weight_value <= 1)
    ");
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_coefficients');
    }
};
