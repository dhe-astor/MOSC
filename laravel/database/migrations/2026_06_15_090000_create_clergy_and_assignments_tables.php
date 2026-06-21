<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // 1. Drop foreign keys referencing priests table
        if (Schema::hasTable('sacraments')) {
            Schema::table('sacraments', function (Blueprint $table) use ($driver) {
                if ($driver !== 'sqlite') {
                    $table->dropForeign('sacraments_officiated_by_priest_id_foreign');
                }
            });
        }

        if (Schema::hasTable('ministry_units')) {
            Schema::table('ministry_units', function (Blueprint $table) use ($driver) {
                if ($driver !== 'sqlite') {
                    $table->dropForeign('ministry_units_president_priest_id_foreign');
                }
            });
        }

        if (Schema::hasTable('ministry_office_bearers')) {
            Schema::table('ministry_office_bearers', function (Blueprint $table) use ($driver) {
                if ($driver !== 'sqlite') {
                    $table->dropForeign('ministry_office_bearers_priest_id_foreign');
                }
            });
        }

        // Drop old priest assignments
        Schema::dropIfExists('priest_assignments');
        
        // Remove foreign key from finance_priest_payments
        if ($driver === 'sqlite') {
            Schema::dropIfExists('finance_priest_payments');
            Schema::create('finance_priest_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('church_id')->nullable()->constrained('churches');
                $table->foreignId('priest_profile_id')->constrained('priest_profiles')->onDelete('cascade');
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
        } else {
            if (Schema::hasTable('finance_priest_payments')) {
                Schema::table('finance_priest_payments', function (Blueprint $table) {
                    $table->dropForeign('finance_priest_payments_priest_id_foreign');
                    $table->dropColumn('priest_id');
                });
            }
        }

        // Drop priests table
        Schema::dropIfExists('priests');

        // 2. Make family_id nullable in members table
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedBigInteger('family_id')->nullable()->change();
        });

        // 3. Create website_import_sources table
        Schema::create('website_import_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->string('source_type'); // priests, parishes, administration, other
            $table->string('source_url');
            $table->string('status')->default('active'); // active, inactive
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // 4. Create website_import_runs table
        Schema::create('website_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('source_id')->constrained('website_import_sources')->onDelete('cascade');
            $table->string('run_type')->default('manual'); // manual, scheduled
            $table->string('status')->default('started'); // started, parsed, review_required, imported, failed, cancelled
            $table->integer('records_found')->default(0);
            $table->integer('records_created')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_skipped')->default(0);
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 5. Create website_import_records table
        Schema::create('website_import_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('website_import_runs')->onDelete('cascade');
            $table->string('record_type'); // priest, church, priest_assignment, administration_member
            $table->string('external_key')->nullable();
            $table->string('raw_name')->nullable();
            $table->string('normalized_name')->nullable();
            $table->json('raw_payload')->nullable();
            $table->unsignedBigInteger('matched_member_id')->nullable();
            $table->unsignedBigInteger('matched_priest_profile_id')->nullable();
            $table->unsignedBigInteger('matched_church_id')->nullable();
            $table->string('match_status')->default('unmatched'); // unmatched, matched, duplicate_possible, ignored, imported
            $table->text('review_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // 6. Create priest_profiles table
        Schema::create('priest_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('priest_code')->nullable();
            $table->string('ordination_name')->nullable();
            $table->string('display_name');
            $table->string('canonical_title')->nullable();
            $table->string('clergy_type')->default('priest'); // priest, corepiscopa, ramban, bishop, deacon, other
            $table->date('ordination_date')->nullable();
            $table->string('ordination_place')->nullable();
            $table->string('home_diocese')->nullable();
            $table->string('phone_public')->nullable();
            $table->string('email_public')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('bio')->nullable();
            $table->string('status')->default('active'); // active, inactive, retired, transferred_out, deceased
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 7. Re-link foreign keys to priest_profiles
        if ($driver === 'sqlite') {
            \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF;');
            Schema::dropIfExists('sacraments');
            Schema::dropIfExists('ministry_units');
            Schema::dropIfExists('ministry_office_bearers');
            
            // Re-create sacraments
            Schema::create('sacraments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
                $table->foreignId('church_id')->constrained('churches')->onDelete('cascade');
                $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
                $table->foreignId('family_id')->nullable()->constrained('families')->onDelete('set null');
                $table->enum('sacrament_type', ['baptism', 'holy_communion', 'confirmation', 'marriage', 'funeral', 'other']);
                $table->date('sacrament_date');
                $table->string('place');
                $table->foreignId('officiated_by_priest_id')->nullable()->constrained('priest_profiles')->onDelete('set null');
                $table->string('certificate_number')->nullable();
                $table->string('register_book_number')->nullable();
                $table->string('register_page_number')->nullable();
                $table->string('witness_1_name')->nullable();
                $table->string('witness_2_name')->nullable();
                $table->foreignId('spouse_member_id')->nullable()->constrained('members')->onDelete('set null');
                $table->string('spouse_name')->nullable();
                $table->text('remarks')->nullable();
                $table->string('document_path')->nullable();
                $table->enum('status', ['draft', 'submitted', 'verified', 'approved', 'rejected', 'archived'])->default('draft');
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('verified_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('approved_at')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });

            // Re-create ministry_units
            Schema::create('ministry_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('diocese_id')->constrained('dioceses');
                $table->foreignId('church_id')->nullable()->constrained('churches');
                $table->foreignId('ministry_organization_id')->constrained('ministry_organizations');
                $table->string('unit_name');
                $table->string('unit_level'); // diocese, parish
                $table->foreignId('president_priest_id')->nullable()->constrained('priest_profiles')->onDelete('set null');
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

            // Re-create ministry_office_bearers
            Schema::create('ministry_office_bearers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ministry_unit_id')->constrained('ministry_units');
                $table->foreignId('member_id')->nullable()->constrained('members');
                $table->foreignId('priest_id')->nullable()->constrained('priest_profiles')->onDelete('set null');
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
            \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            if (Schema::hasTable('sacraments')) {
                Schema::table('sacraments', function (Blueprint $table) {
                    $table->foreign('officiated_by_priest_id')->references('id')->on('priest_profiles')->onDelete('set null');
                });
            }

            if (Schema::hasTable('ministry_units')) {
                Schema::table('ministry_units', function (Blueprint $table) {
                    $table->foreign('president_priest_id')->references('id')->on('priest_profiles')->onDelete('set null');
                });
            }

            if (Schema::hasTable('ministry_office_bearers')) {
                Schema::table('ministry_office_bearers', function (Blueprint $table) {
                    $table->foreign('priest_id')->references('id')->on('priest_profiles')->onDelete('set null');
                });
            }

            if (Schema::hasTable('finance_priest_payments')) {
                Schema::table('finance_priest_payments', function (Blueprint $table) {
                    $table->foreignId('priest_profile_id')->constrained('priest_profiles')->onDelete('cascade');
                });
            }
        }

        // 8. Create priest_church_assignments table
        Schema::create('priest_church_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('priest_profile_id')->constrained('priest_profiles')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('church_id')->constrained('churches')->onDelete('cascade');
            $table->string('assignment_role'); // vicar, assistant_vicar, priest_in_charge, visiting_priest, chaplain, supply_priest, former_vicar, other
            $table->string('assignment_title')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('active'); // draft, active, scheduled_transfer, ended, cancelled
            $table->boolean('is_primary')->default(false);
            $table->string('appointed_by')->nullable();
            $table->string('appointment_reference')->nullable();
            $table->string('appointment_document_path')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('ended_by')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 9. Add active primary vicar constraint per church
        if ($driver === 'sqlite') {
            \Illuminate\Support\Facades\DB::statement("CREATE UNIQUE INDEX unique_active_primary_vicar_per_church ON priest_church_assignments (church_id) WHERE is_primary = 1 AND status = 'active' AND deleted_at IS NULL;");
        } else {
            \Illuminate\Support\Facades\DB::statement("CREATE UNIQUE INDEX unique_active_primary_vicar_per_church ON priest_church_assignments (church_id) WHERE is_primary = true AND status = 'active' AND deleted_at IS NULL;");
        }

        // 10. Create priest_transfer_requests table
        Schema::create('priest_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('priest_profile_id')->constrained('priest_profiles')->onDelete('cascade');
            $table->foreignId('from_church_id')->nullable()->constrained('churches')->onDelete('set null');
            $table->foreignId('to_church_id')->constrained('churches')->onDelete('cascade');
            $table->unsignedBigInteger('from_assignment_id')->nullable();
            $table->string('new_assignment_role');
            $table->date('effective_date');
            $table->string('transfer_type'); // new_assignment, transfer, additional_charge, temporary_charge, end_assignment
            $table->string('status')->default('draft'); // draft, approved, scheduled, completed, cancelled
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('appointment_reference')->nullable();
            $table->string('appointment_document_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 11. Create member_responsibility_assignments table
        Schema::create('member_responsibility_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->nullable()->constrained('churches')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('responsibility_type'); // parish_office, finance, committee, organization, sunday_school, cms, event, programme, pastoral_support, other
            $table->string('designation'); // secretary, joint_secretary, treasurer, joint_treasurer, trustee, committee_member, incharge, coordinator, president, vice_president, auditor, teacher, headmaster, pro, editor, convenor, volunteer, other
            $table->string('organization_type')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->foreignId('programme_account_id')->nullable()->constrained('finance_programme_accounts')->onDelete('set null');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('active'); // active, ended, suspended, cancelled
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('assigned_by');
            $table->string('assignment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // Drop foreign keys in reverse order
        if (Schema::hasTable('sacraments')) {
            Schema::table('sacraments', function (Blueprint $table) use ($driver) {
                if ($driver !== 'sqlite') {
                    $table->dropForeign(['officiated_by_priest_id']);
                }
            });
        }

        if (Schema::hasTable('ministry_units')) {
            Schema::table('ministry_units', function (Blueprint $table) use ($driver) {
                if ($driver !== 'sqlite') {
                    $table->dropForeign(['president_priest_id']);
                }
            });
        }

        if (Schema::hasTable('ministry_office_bearers')) {
            Schema::table('ministry_office_bearers', function (Blueprint $table) use ($driver) {
                if ($driver !== 'sqlite') {
                    $table->dropForeign(['priest_id']);
                }
            });
        }

        Schema::dropIfExists('member_responsibility_assignments');
        Schema::dropIfExists('priest_transfer_requests');
        Schema::dropIfExists('priest_church_assignments');
        
        if ($driver === 'sqlite') {
            Schema::dropIfExists('finance_priest_payments');
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
        } else {
            if (Schema::hasTable('finance_priest_payments')) {
                Schema::table('finance_priest_payments', function (Blueprint $table) {
                    $table->dropForeign(['priest_profile_id']);
                    $table->dropColumn('priest_profile_id');
                });
            }
        }

        Schema::dropIfExists('priest_profiles');
        Schema::dropIfExists('website_import_records');
        Schema::dropIfExists('website_import_runs');
        Schema::dropIfExists('website_import_sources');

        // Recreate legacy tables on rollback
        Schema::create('priests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('full_name');
            $table->string('primary_phone');
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('priest_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('priest_id')->constrained('priests')->onDelete('cascade');
            $table->foreignId('church_id')->constrained('churches')->onDelete('cascade');
            $table->string('role')->default('vicar');
            $table->date('assignment_start_date');
            $table->date('assignment_end_date')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        if ($driver !== 'sqlite') {
            if (Schema::hasTable('finance_priest_payments')) {
                Schema::table('finance_priest_payments', function (Blueprint $table) {
                    $table->foreignId('priest_id')->constrained('priests');
                });
            }
        }

        if (Schema::hasTable('sacraments')) {
            Schema::table('sacraments', function (Blueprint $table) {
                $table->foreign('officiated_by_priest_id')->references('id')->on('priests')->onDelete('set null');
            });
        }

        if (Schema::hasTable('ministry_units')) {
            Schema::table('ministry_units', function (Blueprint $table) {
                $table->foreign('president_priest_id')->references('id')->on('priests')->onDelete('set null');
            });
        }

        if (Schema::hasTable('ministry_office_bearers')) {
            Schema::table('ministry_office_bearers', function (Blueprint $table) {
                $table->foreign('priest_id')->references('id')->on('priests')->onDelete('set null');
            });
        }
    }
};
