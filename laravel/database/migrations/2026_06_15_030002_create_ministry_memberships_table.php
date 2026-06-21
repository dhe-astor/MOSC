<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ministry_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses');
            $table->foreignId('church_id')->nullable()->constrained('churches');
            $table->foreignId('ministry_unit_id')->constrained('ministry_units');
            $table->foreignId('member_id')->constrained('members');
            $table->foreignId('family_id')->nullable()->constrained('families');
            $table->string('membership_type')->default('regular'); // regular, office_bearer, volunteer, advisor
            $table->date('joined_date');
            $table->string('status')->default('pending'); // pending, active, inactive, transferred, archived
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ministry_memberships');
    }
};
