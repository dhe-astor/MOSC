<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_portal_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->constrained('churches')->onDelete('cascade');
            $table->foreignId('family_id')->nullable()->constrained('families')->onDelete('cascade');
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('access_type', ['family_head', 'member', 'parent_guardian']);
            $table->enum('status', ['invited', 'active', 'suspended', 'revoked']);
            $table->unsignedBigInteger('invited_by')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->unsignedBigInteger('suspended_by')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['user_id', 'family_id', 'member_id', 'access_type'], 'mpa_user_identity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_portal_access');
    }
};
