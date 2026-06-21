<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses');
            $table->foreignId('church_id')->nullable()->constrained('churches');
            $table->string('receipt_number')->unique();
            $table->string('receipt_type'); // donation, income, course_fee, event_fee, manual
            $table->string('receiptable_type')->nullable(); // Polymorphic relation: Donation or IncomeRecord
            $table->unsignedBigInteger('receiptable_id')->nullable();
            $table->string('payer_name');
            $table->string('payer_email')->nullable();
            $table->string('payer_phone')->nullable();
            $table->foreignId('family_id')->nullable()->constrained('families');
            $table->foreignId('member_id')->nullable()->constrained('members');
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('EUR');
            $table->string('payment_method');
            $table->string('payment_reference')->nullable();
            $table->date('receipt_date');
            $table->text('description')->nullable();
            $table->string('pdf_path')->nullable();
            $table->foreignId('issued_by')->constrained('users');
            $table->string('status')->default('issued'); // issued, cancelled, replaced
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable(); // jsonb
            $table->timestamps();

            $table->index(['receiptable_type', 'receiptable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
