<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->nullable()->constrained('churches')->onDelete('cascade');
            $table->foreignId('family_id')->nullable()->constrained('families')->onDelete('set null');
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('set null');
            
            // External participants details
            $table->string('external_name')->nullable();
            $table->string('external_email')->nullable();
            $table->string('external_phone')->nullable();
            
            $table->string('registration_type')->default('member'); // member, family, external
            $table->integer('participant_count')->default(1);
            $table->string('payment_status')->default('not_required'); // not_required, pending, paid, waived, failed, refunded
            $table->string('payment_reference')->nullable();
            $table->string('registration_status')->default('pending'); // pending, confirmed, checked_in, attended, cancelled, rejected
            
            $table->string('qr_code')->unique()->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->foreignId('registered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            // Ensure unique record per member/family per event
            // Note: Only enforce if member_id or family_id is filled (we can enforce uniqueness in validation logic or conditional DB checks,
            // but for DB level simple uniqueness, we'll implement validation rules in the service).
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
