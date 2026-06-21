<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->nullable()->constrained('churches')->onDelete('cascade');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->string('title');
            $table->text('body');
            $table->enum('notification_type', ['announcement', 'reminder', 'approval', 'certificate', 'finance', 'cms', 'course', 'event', 'sunday_school', 'ministry', 'system']);
            $table->enum('channel', ['in_app', 'email', 'sms', 'whatsapp']);
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed', 'read', 'archived'])->default('queued');
            $table->dateTime('read_at')->nullable();
            $table->string('action_url')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('diocese_id');
            $table->index('church_id');
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
