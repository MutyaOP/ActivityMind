<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // SHA-256 от токена устройства; сам токен нигде не хранится
            $table->char('token_hash', 64)->nullable()->unique()->after('platform');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            // Повторная отправка той же пачки не создаёт дублей
            $table->unique(['device_id', 'started_at', 'app_name'], 'activity_logs_device_start_app_unique');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropUnique('activity_logs_device_start_app_unique');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
            $table->dropColumn('token_hash');
        });
    }
};
