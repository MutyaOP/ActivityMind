<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('stat_date');
            $table->unsignedInteger('focus_sec')->default(0);
            $table->unsignedInteger('tracked_sec')->default(0);
            $table->unsignedSmallInteger('tasks_done')->default(0);
            $table->unsignedSmallInteger('habits_done')->default(0);
            $table->unsignedSmallInteger('habits_total')->default(0);
            $table->unsignedTinyInteger('productivity_score')->default(0); // 0-100
            $table->timestamps();

            $table->unique(['user_id', 'stat_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stats');
    }
};
