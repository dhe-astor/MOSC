<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->string('avatar_path')->nullable();
            $table->foreignId('default_diocese_id')->nullable()->constrained('dioceses')->nullOnDelete();
            $table->unsignedBigInteger('default_church_id')->nullable();
            $table->unsignedBigInteger('active_church_id')->nullable();
            $table->string('preferred_language', 5)->default('en'); // en, ml, de
            $table->timestamp('last_login_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('phone_verified_at')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_diocese_id']);
            $table->dropColumn([
                'phone',
                'avatar_path',
                'default_diocese_id',
                'default_church_id',
                'active_church_id',
                'preferred_language',
                'last_login_at',
                'is_active',
                'phone_verified_at',
                'two_factor_enabled'
            ]);
        });
    }
};
