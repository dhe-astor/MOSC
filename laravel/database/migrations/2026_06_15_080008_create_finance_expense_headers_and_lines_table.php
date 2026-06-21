<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_expense_headers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->nullable()->constrained('churches');
            $table->date('expense_date');
            $table->foreignId('money_account_id')->constrained('finance_money_accounts');
            $table->string('voucher_number')->nullable()->unique();
            $table->string('reference_no')->nullable();
            $table->string('payee_name');
            $table->text('remarks')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('finance_expense_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_header_id')->constrained('finance_expense_headers')->onDelete('cascade');
            $table->foreignId('expense_head_id')->constrained('finance_expense_heads');
            $table->foreignId('fund_class_id')->constrained('finance_fund_classes');
            $table->foreignId('programme_account_id')->nullable()->constrained('finance_programme_accounts');
            $table->decimal('amount', 15, 2);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_expense_lines');
        Schema::dropIfExists('finance_expense_headers');
    }
};
