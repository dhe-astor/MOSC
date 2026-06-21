<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('money_account_id')->constrained('finance_money_accounts');
            $table->date('import_date');
            $table->string('file_name');
            $table->foreignId('imported_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('finance_bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_import_id')->constrained('finance_bank_statement_imports')->onDelete('cascade');
            $table->date('booking_date');
            $table->date('value_date')->nullable();
            $table->string('partner_name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->boolean('is_matched')->default(false);
            $table->timestamps();
        });

        Schema::create('finance_bank_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_line_id')->constrained('finance_bank_statement_lines')->onDelete('cascade');
            $table->string('matchable_type');
            $table->unsignedBigInteger('matchable_id');
            $table->foreignId('matched_by')->constrained('users');
            $table->timestamps();

            $table->index(['matchable_type', 'matchable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_bank_matches');
        Schema::dropIfExists('finance_bank_statement_lines');
        Schema::dropIfExists('finance_bank_statement_imports');
    }
};
