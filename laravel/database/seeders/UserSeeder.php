<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Diocese;
use App\Models\Church;
use App\Models\PriestProfile;
use App\Models\PriestChurchAssignment;
use App\Models\Member;
use App\Models\UserChurchAccess;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $diocese = Diocese::first();
        if (!$diocese) {
            return;
        }

        $vienna = Church::where('short_name', 'Vienna')->first();
        $herne = Church::where('short_name', 'Herne')->first();

        // 1. Super Admin
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@msoc-europe.org'],
            [
                'name' => 'Super Admin',
                'phone' => '+43 664 1234567',
                'password' => Hash::make('Password123!'),
                'default_diocese_id' => $diocese->id,
                'default_church_id' => null,
                'active_church_id' => null,
                'preferred_language' => 'en',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->assignRole('Super Admin');

        UserChurchAccess::updateOrCreate(
            ['user_id' => $superAdmin->id, 'diocese_id' => $diocese->id, 'church_id' => null],
            [
                'access_scope' => 'diocese_all',
                'status' => 'active',
                'starts_at' => now(),
            ]
        );

        // 2. Diocese Admin
        $dioceseAdmin = User::updateOrCreate(
            ['email' => 'admin@msoc-europe.org'],
            [
                'name' => 'Diocese Admin',
                'phone' => '+43 664 7654321',
                'password' => Hash::make('Password123!'),
                'default_diocese_id' => $diocese->id,
                'default_church_id' => null,
                'active_church_id' => null,
                'preferred_language' => 'en',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $dioceseAdmin->assignRole('Diocese Admin');

        UserChurchAccess::updateOrCreate(
            ['user_id' => $dioceseAdmin->id, 'diocese_id' => $diocese->id, 'church_id' => null],
            [
                'access_scope' => 'diocese_all',
                'status' => 'active',
                'starts_at' => now(),
            ]
        );

        // 3. Priest User & Profile & Assignments
        $priestUser = User::updateOrCreate(
            ['email' => 'priest@msoc-europe.org'],
            [
                'name' => 'Rev. Fr. Jacob Mathew',
                'phone' => '+43 664 5556667',
                'password' => Hash::make('Password123!'),
                'default_diocese_id' => $diocese->id,
                'default_church_id' => $vienna?->id,
                'active_church_id' => $vienna?->id,
                'preferred_language' => 'ml',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $priestUser->assignRole('Priest / Vicar');

        $priestMember = Member::updateOrCreate(
            ['email' => 'priest@msoc-europe.org'],
            [
                'diocese_id' => $diocese->id,
                'church_id' => $vienna?->id,
                'family_id' => null,
                'user_id' => $priestUser->id,
                'first_name' => 'Jacob',
                'last_name' => 'Mathew',
                'full_name' => 'Jacob Mathew',
                'gender' => 'male',
                'date_of_birth' => Carbon::parse('1985-08-25'),
                'relationship_to_head' => 'other',
                'phone' => '+43 664 5556667',
                'whatsapp_phone' => '+43 664 5556667',
                'membership_status' => 'active',
                'created_by' => $superAdmin->id,
            ]
        );

        $priestProfile = PriestProfile::updateOrCreate(
            ['user_id' => $priestUser->id],
            [
                'diocese_id' => $diocese->id,
                'member_id' => $priestMember->id,
                'display_name' => 'Rev. Fr. Jacob Mathew',
                'ordination_name' => 'Fr. Jacob Mathew',
                'canonical_title' => 'Rev. Fr.',
                'clergy_type' => 'priest',
                'ordination_date' => Carbon::parse('2015-05-15'),
                'ordination_place' => 'Vienna',
                'home_diocese' => 'MSOC Europe',
                'phone_public' => '+43 664 5556667',
                'email_public' => 'priest@msoc-europe.org',
                'bio' => 'Serving the MSOC Europe diocese in Austria and Germany.',
                'status' => 'active',
            ]
        );

        if ($vienna) {
            PriestChurchAssignment::updateOrCreate(
                ['priest_profile_id' => $priestProfile->id, 'church_id' => $vienna->id],
                [
                    'diocese_id' => $diocese->id,
                    'member_id' => $priestMember->id,
                    'user_id' => $priestUser->id,
                    'assignment_role' => 'vicar',
                    'start_date' => Carbon::parse('2020-01-01'),
                    'is_primary' => true,
                    'status' => 'active',
                    'notes' => 'Assigned as primary Vicar of Vienna parish.',
                ]
            );
        }

        if ($herne) {
            PriestChurchAssignment::updateOrCreate(
                ['priest_profile_id' => $priestProfile->id, 'church_id' => $herne->id],
                [
                    'diocese_id' => $diocese->id,
                    'member_id' => $priestMember->id,
                    'user_id' => $priestUser->id,
                    'assignment_role' => 'assistant_vicar',
                    'start_date' => Carbon::parse('2022-06-01'),
                    'is_primary' => false,
                    'status' => 'active',
                    'notes' => 'Assigned as Assistant Vicar of Herne parish.',
                ]
            );
        }

        // 4. Vienna Parish Admin
        if ($vienna) {
            $viennaAdmin = User::updateOrCreate(
                ['email' => 'vienna.admin@msoc-europe.org'],
                [
                    'name' => 'Vienna Parish Admin',
                    'phone' => '+43 664 9998887',
                    'password' => Hash::make('Password123!'),
                    'default_diocese_id' => $diocese->id,
                    'default_church_id' => $vienna->id,
                    'active_church_id' => $vienna->id,
                    'preferred_language' => 'de',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            $viennaAdmin->assignRole('Parish Admin');

            UserChurchAccess::updateOrCreate(
                ['user_id' => $viennaAdmin->id, 'diocese_id' => $diocese->id, 'church_id' => $vienna->id],
                [
                    'access_scope' => 'church_specific',
                    'status' => 'active',
                    'starts_at' => now(),
                ]
            );
        }

        // 5. Herne Parish Admin (for isolation verification)
        if ($herne) {
            $herneAdmin = User::updateOrCreate(
                ['email' => 'herne.admin@msoc-europe.org'],
                [
                    'name' => 'Herne Parish Admin',
                    'phone' => '+49 176 1122334',
                    'password' => Hash::make('Password123!'),
                    'default_diocese_id' => $diocese->id,
                    'default_church_id' => $herne->id,
                    'active_church_id' => $herne->id,
                    'preferred_language' => 'de',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            $herneAdmin->assignRole('Parish Admin');

            UserChurchAccess::updateOrCreate(
                ['user_id' => $herneAdmin->id, 'diocese_id' => $diocese->id, 'church_id' => $herne->id],
                [
                    'access_scope' => 'church_specific',
                    'status' => 'active',
                    'starts_at' => now(),
                ]
            );
        }
    }
}

