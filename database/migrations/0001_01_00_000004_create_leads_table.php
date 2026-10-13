<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            $table->string('external_id')->index();
            $table->dateTime('registered_at')->nullable();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            $table->string('phone', 12)->index();
            $table->string('email')->nullable();

            $table->string('city')->nullable();
            $table->string('source')->nullable();
            $table->string('campaign')->nullable();
            $table->string('service')->nullable();

            $table->decimal('budget', 12, 2)->nullable();
            $table->string('status')->nullable();
            $table->string('manager')->nullable();
            $table->text('comment')->nullable();

            $table->dateTime('last_contact_at')->nullable();

            $table->foreignId('import_id')  
                ->nullable()
                ->constrained('imports')
                ->nullOnDelete();
                
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};