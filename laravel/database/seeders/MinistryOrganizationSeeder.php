<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MinistryOrganization;
use App\Models\Diocese;
use App\Models\User;

class MinistryOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $diocese = Diocese::first();
        if (!$diocese) {
            return;
        }

        $admin = User::where('email', 'superadmin@msoc-europe.org')->first() ?? User::first();
        if (!$admin) {
            return;
        }

        MinistryOrganization::updateOrCreate(
            ['slug' => 'msoc-europe-youth-association'],
            [
                'diocese_id' => $diocese->id,
                'name' => 'MSOC Europe Youth Association',
                'organization_type' => 'youth_association',
                'description' => 'The official youth movement of MSOC Europe.',
                'eligibility_rules' => [
                    'min_age' => 15,
                    'max_age' => 35,
                    'gender' => null,
                ],
                'status' => 'active',
                'show_on_portal' => true,
                'created_by' => $admin->id,
            ]
        );

        MinistryOrganization::updateOrCreate(
            ['slug' => 'msoc-europe-marthamariyam-samajam'],
            [
                'diocese_id' => $diocese->id,
                'name' => 'MSOC Europe Marthamariyam Samajam',
                'organization_type' => 'marthamariyam_samajam',
                'description' => 'The official women\'s association of MSOC Europe.',
                'eligibility_rules' => [
                    'min_age' => 21,
                    'max_age' => null,
                    'gender' => 'F',
                ],
                'status' => 'active',
                'show_on_portal' => true,
                'created_by' => $admin->id,
            ]
        );
    }
}
