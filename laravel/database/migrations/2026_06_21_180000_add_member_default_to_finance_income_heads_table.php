<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_income_heads', function (Blueprint $table) {
            $table->boolean('member_default')->default(true)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_heads', function (Blueprint $table) {
            $table->dropColumn('member_default');
        });
    }
};
