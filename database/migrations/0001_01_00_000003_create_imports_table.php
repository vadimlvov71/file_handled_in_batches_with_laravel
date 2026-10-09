<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('file_path');
            $table->string('status')->default('pending');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('valid')->default(0);
            $table->unsignedInteger('invalid')->default(0);
            $table->string('error_file_path')->nullable();
            $table->text('error_message')->nullable();
            
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->unsignedInteger('duration_ms')->nullable();

            $table->unsignedInteger('read_duration_ms')->nullable();
            $table->unsignedInteger('validation_duration_ms')->nullable();
            $table->unsignedInteger('db_duration_ms')->nullable();
            $table->unsignedInteger('error_file_duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};