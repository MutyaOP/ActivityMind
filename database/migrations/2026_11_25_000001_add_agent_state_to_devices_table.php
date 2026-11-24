<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Состояние агента для страницы «Активность»: на паузе ли учёт и какая программа открыта сейчас. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('is_paused')->default(false)->after('last_sync_at');
            $table->string('current_app')->nullable()->after('is_paused');
            $table->timestamp('current_since')->nullable()->after('current_app');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['is_paused', 'current_app', 'current_since']);
        });
    }
};
