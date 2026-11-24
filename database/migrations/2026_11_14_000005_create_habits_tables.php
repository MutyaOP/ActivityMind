<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('frequency', ['daily', 'weekly'])->default('daily');
            $table->unsignedTinyInteger('target_per_period')->default(1);
            $table->string('color', 7)->default('#06b6d4');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('habit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->boolean('is_done')->default(true);
            $table->timestamps();

            $table->unique(['habit_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_logs');
        Schema::dropIfExists('habits');
    }
};
