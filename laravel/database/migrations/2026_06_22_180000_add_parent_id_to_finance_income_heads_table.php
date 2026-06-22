<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_income_heads', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('chart_account_id')
                ->constrained('finance_income_heads')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_heads', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
