<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses');
            $table->foreignId('church_id')->nullable()->constrained('churches');
            $table->string('approvable_type'); // Polymorphic relation: Donation, IncomeRecord, ExpenseRecord, Receipt
            $table->unsignedBigInteger('approvable_id');
            $table->string('approval_type'); // income_approval, expense_approval, refund_approval, receipt_cancellation
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->text('remarks')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_approvals');
    }
};
