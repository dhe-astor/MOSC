<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses');
            $table->foreignId('church_id')->nullable()->constrained('churches');
            $table->foreignId('finance_category_id')->constrained('finance_categories');
            $table->string('source_type')->nullable(); // course_registration, event_registration, manual, ministry_activity, other
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('family_id')->nullable()->constrained('families');
            $table->foreignId('member_id')->nullable()->constrained('members');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('EUR');
            $table->string('payment_method'); // cash, bank_transfer, card, paypal, sepa, other
            $table->string('payment_reference')->nullable();
            $table->date('income_date');
            $table->string('status')->default('draft'); // draft, submitted, approved, rejected, received, cancelled
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('receipt_id')->nullable(); // will be linked to receipts table
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_records');
    }
};
