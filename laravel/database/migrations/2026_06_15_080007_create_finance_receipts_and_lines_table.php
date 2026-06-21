<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_header_id')->nullable()->constrained('finance_income_headers');
            $table->string('receipt_number')->unique();
            $table->date('receipt_date');
            $table->string('received_from');
            $table->foreignId('member_id')->nullable()->constrained('members');
            $table->string('payment_method');
            $table->decimal('total_amount', 15, 2);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('finance_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('finance_receipts')->onDelete('cascade');
            $table->foreignId('income_line_id')->nullable()->constrained('finance_income_lines')->onDelete('set null');
            $table->foreignId('income_head_id')->constrained('finance_income_heads');
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_receipt_lines');
        Schema::dropIfExists('finance_receipts');
    }
};
