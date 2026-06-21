<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_income_headers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->nullable()->constrained('churches');
            $table->date('income_date');
            $table->foreignId('money_account_id')->constrained('finance_money_accounts');
            $table->string('reference_no')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('finance_income_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_header_id')->constrained('finance_income_headers')->onDelete('cascade');
            $table->foreignId('income_head_id')->constrained('finance_income_heads');
            $table->foreignId('fund_class_id')->constrained('finance_fund_classes');
            $table->foreignId('programme_account_id')->nullable()->constrained('finance_programme_accounts');
            $table->foreignId('member_id')->nullable()->constrained('members');
            $table->string('donor_name')->nullable();
            $table->decimal('amount', 15, 2);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_income_lines');
        Schema::dropIfExists('finance_income_headers');
    }
};
