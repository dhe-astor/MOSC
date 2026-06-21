<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_batch_id')->constrained('course_batches')->onDelete('cascade');
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
            $table->string('registration_status')->default('pending'); // pending, confirmed, attended, completed, cancelled, rejected
            
            $table->string('qr_code')->unique()->nullable();
            $table->boolean('feedback_completed')->default(false);
            $table->boolean('certificate_issued')->default(false);
            $table->foreignId('certificate_id')->nullable()->constrained('certificates')->onDelete('set null');
            
            $table->foreignId('registered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_registrations');
    }
};
