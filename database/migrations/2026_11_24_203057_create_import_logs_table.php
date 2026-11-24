<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('import_type', 30); // users|grades
            $table->string('file_name', 255);
            $table->string('file_path', 500)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('status', 20); // success|partial|failed
            $table->integer('saved_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->jsonb('error_details')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'import_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
