<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_priest_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->nullable()->constrained('churches');
            $table->foreignId('priest_id')->constrained('priests');
            $table->foreignId('expense_header_id')->nullable()->constrained('finance_expense_headers')->onDelete('set null');
            $table->date('payment_date');
            $table->string('type'); // stipend, allowance, travel
            $table->decimal('amount', 15, 2);
            $table->decimal('travel_distance_km', 10, 2)->nullable();
            $table->decimal('travel_rate_per_km', 15, 4)->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_priest_payments');
    }
};
