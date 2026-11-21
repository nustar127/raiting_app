<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_id')->constrained('curricula')->cascadeOnDelete();
            $table->string('name', 100);
            $table->smallInteger('order_number');
            $table->timestamps();

            $table->unique(['curriculum_id', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
