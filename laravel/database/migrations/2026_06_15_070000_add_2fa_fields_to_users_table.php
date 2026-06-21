<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->string('two_factor_otp_hash')->nullable();
            $table->timestamp('two_factor_otp_expires_at')->nullable();
            $table->timestamp('two_factor_last_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_confirmed_at',
                'two_factor_otp_hash',
                'two_factor_otp_expires_at',
                'two_factor_last_verified_at'
            ]);
        });
    }
};
