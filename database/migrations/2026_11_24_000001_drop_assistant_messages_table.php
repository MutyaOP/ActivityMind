<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * LLM-ассистент удалён из проекта: таблица переписки больше не нужна.
 * dropIfExists — безопасно и для новой установки, и для уже развёрнутой базы.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('assistant_messages');
    }

    public function down(): void
    {
        // Ассистент не возвращается — восстанавливать таблицу не нужно.
    }
};
