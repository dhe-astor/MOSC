<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Diocese;

class DioceseSeeder extends Seeder
{
    public function run(): void
    {
        Diocese::updateOrCreate(
            ['canonical_name' => 'Malankara Syrian Orthodox Church Europe Diocese'],
            [
                'name' => 'MSOC Europe Diocese',
                'short_name' => 'MSOC Europe',
                'description' => 'Diocese of the Malankara Syrian Orthodox Church in Europe.',
                'address' => 'Vienna, Austria',
                'city' => 'Vienna',
                'country' => 'Austria',
                'phone' => '+43 1 2345678',
                'email' => 'diocese@msoc-europe.com',
                'website' => 'https://msoc-europe.com',
                'logo_path' => null,
                'seal_path' => null,
                'status' => 'active'
            ]
        );
    }
}
