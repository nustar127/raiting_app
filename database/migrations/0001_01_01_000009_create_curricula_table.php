<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curricula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_id')->constrained('disciplines')->restrictOnDelete();
            $table->foreignId('group_id')->constrained('groups')->restrictOnDelete();
            $table->smallInteger('course_number');
            $table->smallInteger('semester_number');
            $table->timestamps();

            $table->unique(
                ['discipline_id', 'group_id', 'semester_number'],
                'curricula_disc_group_sem_unique'
            );
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curricula');
    }
};
