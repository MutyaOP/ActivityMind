<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendations', function (Blueprint $table) {
            // Идентификатор правила — чтобы не создавать один и тот же совет повторно
            $table->string('rule_key', 80)->nullable()->after('source');
            $table->index(['user_id', 'rule_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('recommendations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'rule_key', 'created_at']);
            $table->dropColumn('rule_key');
        });
    }
};
