<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->string('template_key');
            $table->string('name');
            $table->enum('channel', ['in_app', 'email', 'sms', 'whatsapp']);
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->json('variables')->nullable();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['diocese_id', 'channel', 'template_key']);
            $table->index('diocese_id');
            $table->index('template_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
