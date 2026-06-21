<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ministry_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses');
            $table->foreignId('church_id')->nullable()->constrained('churches');
            $table->foreignId('ministry_organization_id')->constrained('ministry_organizations');
            $table->string('unit_name');
            $table->string('unit_level'); // diocese, parish
            $table->foreignId('president_priest_id')->nullable()->constrained('priests');
            $table->foreignId('coordinator_member_id')->nullable()->constrained('members');
            $table->foreignId('secretary_member_id')->nullable()->constrained('members');
            $table->foreignId('treasurer_member_id')->nullable()->constrained('members');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active'); // active, inactive, archived
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ministry_units');
    }
};
