<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculties')->restrictOnDelete();
            $table->string('name', 50);
            $table->smallInteger('enrollment_year');
            $table->timestamps();

            $table->unique(['faculty_id', 'name']);
            $table->index('enrollment_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
