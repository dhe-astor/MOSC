<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FinanceFundClass;

class FinanceFundClassSeeder extends Seeder
{
    public function run(): void
    {
        $funds = [
            [
                'code' => 'GEN',
                'name' => 'General Fund',
                'description' => 'Unrestricted operating fund for day-to-day parish administration, liturgical costs, and common operations.',
                'is_active' => true,
            ],
            [
                'code' => 'BLD',
                'name' => 'Building & Development Fund',
                'description' => 'Restricted fund dedicated to church acquisition, building renovations, major repairs, and parish hall construction.',
                'is_active' => true,
            ],
            [
                'code' => 'CHR',
                'name' => 'Charity & Benevolence Fund',
                'description' => 'Restricted fund dedicated to helping the poor, medical aid, education grants, marriage aid, and emergency relief.',
                'is_active' => true,
            ],
            [
                'code' => 'PRI',
                'name' => 'Priest Welfare & Stipend Fund',
                'description' => 'Restricted fund dedicated to priest stipends, travel reimbursements, medical insurance, and clergy pension/welfare.',
                'is_active' => true,
            ],
            [
                'code' => 'PER',
                'name' => 'Perunnal / Parish Day Fund',
                'description' => 'Restricted fund for the annual parish festival (Perunnal), Parish Day, and related feasts and celebrations.',
                'is_active' => true,
            ],
            [
                'code' => 'MSY',
                'name' => 'Mission & Youth Association Fund',
                'description' => 'Restricted fund dedicated to mission work, outreach, Youth Association activities, and spiritual conferences.',
                'is_active' => true,
            ],
            [
                'code' => 'EDC',
                'name' => 'Education & Sunday School Fund',
                'description' => 'Restricted fund dedicated to Sunday School activities, textbooks, student camps, and youth educational development.',
                'is_active' => true,
            ],
            [
                'code' => 'OTH',
                'name' => 'Other Special Funds',
                'description' => 'Restricted fund for other specific diocese or parish-defined special projects and temporary restricted collections.',
                'is_active' => true,
            ],
        ];

        foreach ($funds as $fund) {
            FinanceFundClass::updateOrCreate(
                ['code' => $fund['code']],
                $fund
            );
        }
    }
}
