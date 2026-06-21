<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_journal_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->nullable()->constrained('dioceses');
            $table->foreignId('church_id')->nullable()->constrained('churches');
            $table->date('batch_date');
            $table->string('reference')->nullable();
            $table->string('source'); // income, expense, transfer, manual
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('finance_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_batch_id')->constrained('finance_journal_batches')->onDelete('cascade');
            $table->foreignId('chart_account_id')->constrained('finance_chart_accounts');
            $table->foreignId('fund_class_id')->nullable()->constrained('finance_fund_classes');
            $table->foreignId('programme_account_id')->nullable()->constrained('finance_programme_accounts');
            $table->date('entry_date');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_ledger_entries');
        Schema::dropIfExists('finance_journal_batches');
    }
};
