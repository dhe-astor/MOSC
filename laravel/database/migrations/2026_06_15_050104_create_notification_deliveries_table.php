<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->nullable()->constrained('notifications')->onDelete('cascade');
            $table->foreignId('announcement_id')->nullable()->constrained('announcements')->onDelete('cascade');
            $table->enum('recipient_type', ['user', 'member', 'family', 'external']);
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->enum('channel', ['in_app', 'email', 'sms', 'whatsapp']);
            $table->enum('delivery_status', ['queued', 'sent', 'delivered', 'failed', 'skipped'])->default('queued');
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->timestamps();

            $table->index('notification_id');
            $table->index('announcement_id');
            $table->index('delivery_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
