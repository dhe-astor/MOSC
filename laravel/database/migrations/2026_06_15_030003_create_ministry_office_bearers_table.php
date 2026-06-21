<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ministry_office_bearers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministry_unit_id')->constrained('ministry_units');
            $table->foreignId('member_id')->nullable()->constrained('members');
            $table->foreignId('priest_id')->nullable()->constrained('priests');
            $table->string('external_name')->nullable();
            $table->string('role_title');
            $table->string('role_category'); // president, vice_president, secretary, joint_secretary, treasurer, coordinator, committee_member, advisor, auditor, other
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('active'); // active, ended, archived
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ministry_office_bearers');
    }
};
