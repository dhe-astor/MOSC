<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses');
            $table->foreignId('church_id')->nullable()->constrained('churches');
            $table->foreignId('family_id')->nullable()->constrained('families');
            $table->foreignId('member_id')->nullable()->constrained('members');
            $table->foreignId('finance_category_id')->nullable()->constrained('finance_categories');
            $table->string('donor_name');
            $table->string('donor_email')->nullable();
            $table->string('donor_phone')->nullable();
            $table->string('donation_type'); // general, church, diocese, charity, event, course, ministry, thanksgiving, memorial, other
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('EUR');
            $table->string('payment_method'); // cash, bank_transfer, card, paypal, sepa, other
            $table->string('payment_reference')->nullable();
            $table->date('received_date');
            $table->string('status')->default('pending'); // pending, received, failed, refunded, cancelled
            $table->unsignedBigInteger('receipt_id')->nullable(); // will be linked to receipts table
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
