<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Diocese;
use App\Models\Country;
use App\Models\Church;
use Illuminate\Support\Str;

class ChurchSeeder extends Seeder
{
    public function run(): void
    {
        $diocese = Diocese::first();
        if (!$diocese) {
            return;
        }

        $rawChurches = [
            [
                'raw_name' => 'St. Mary’s Malankara Syriac Orthodox Church Vienna',
                'name' => 'St. Mary\'s Malankara Syriac Orthodox Church Vienna',
                'short' => 'Vienna',
                'city' => 'Vienna',
                'country_code' => 'AT',
                'prefix' => 'VIE',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. Mary’s Malankara Syriac Orthodox Church Switzerland',
                'name' => 'St. Mary\'s Malankara Syriac Orthodox Church Switzerland',
                'short' => 'Switzerland',
                'city' => 'Zürich',
                'country_code' => 'CH',
                'prefix' => 'SUI',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. Mary’s Malankara Syriac Orthodox Church Herne, Germany',
                'name' => 'St. Mary\'s Malankara Syriac Orthodox Church Herne',
                'short' => 'Herne',
                'city' => 'Herne',
                'country_code' => 'DE',
                'prefix' => 'HER',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. Peter’s & St. Paul’s Malankara Syriac Orthodox Church Rome',
                'name' => 'St. Peter\'s & St. Paul\'s Malankara Syriac Orthodox Church Rome',
                'short' => 'Rome',
                'city' => 'Rome',
                'country_code' => 'IT',
                'prefix' => 'ROM',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. Mary’s Malankara Syriac Orthodox Church Denmark',
                'name' => 'St. Mary\'s Malankara Syriac Orthodox Church Denmark',
                'short' => 'Denmark',
                'city' => 'Copenhagen',
                'country_code' => 'DK',
                'prefix' => 'DEN',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. Mary’s Malankara Syriac Orthodox Church Norway',
                'name' => 'St. Mary\'s Malankara Syriac Orthodox Church Norway',
                'short' => 'Norway',
                'city' => 'Oslo',
                'country_code' => 'NO',
                'prefix' => 'NOR',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. Mary’s Malankara Syriac Orthodox Church Munich, Germany',
                'name' => 'St. Mary\'s Malankara Syriac Orthodox Church Munich',
                'short' => 'Munich',
                'city' => 'Munich',
                'country_code' => 'DE',
                'prefix' => 'MUN',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. Thomas Malankara Syriac Orthodox Church Amsterdam',
                'name' => 'St. Thomas Malankara Syriac Orthodox Church Amsterdam',
                'short' => 'Amsterdam',
                'city' => 'Amsterdam',
                'country_code' => 'NL',
                'prefix' => 'AMS',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. Mary’s Malankara Syriac Orthodox Church Sweden',
                'name' => 'St. Mary\'s Malankara Syriac Orthodox Church Sweden',
                'short' => 'Sweden',
                'city' => 'Stockholm',
                'country_code' => 'SE',
                'prefix' => 'SWE',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'Malankara Syriac Orthodox Church Gothenburg, Sweden',
                'name' => 'Malankara Syriac Orthodox Church Gothenburg',
                'short' => 'Gothenburg',
                'city' => 'Gothenburg',
                'country_code' => 'SE',
                'prefix' => 'GOT',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. Elias Malankara Syriac Orthodox Church Berlin, Germany',
                'name' => 'St. Elias Malankara Syriac Orthodox Church Berlin',
                'short' => 'Berlin',
                'city' => 'Berlin',
                'country_code' => 'DE',
                'prefix' => 'BER',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. Mary’s Malankara Syriac Orthodox Church Malta',
                'name' => 'St. Mary\'s Malankara Syriac Orthodox Church Malta',
                'short' => 'Malta',
                'city' => 'Valletta',
                'country_code' => 'MT',
                'prefix' => 'MLT',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. George Malankara Syriac Orthodox Church Frankfurt, Germany',
                'name' => 'St. George Malankara Syriac Orthodox Church Frankfurt',
                'short' => 'Frankfurt',
                'city' => 'Frankfurt',
                'country_code' => 'DE',
                'prefix' => 'FRA',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. Mary’s Malankara Syriac Orthodox Service Centre Varna, Bulgaria',
                'name' => 'St. Mary\'s Malankara Syriac Orthodox Service Centre Varna',
                'short' => 'Varna',
                'city' => 'Varna',
                'country_code' => 'BG',
                'prefix' => 'VAR',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. Peter’s Malankara Syriac Orthodox Church Hannover, Germany',
                'name' => 'St. Peter\'s Malankara Syriac Orthodox Church Hannover',
                'short' => 'Hannover',
                'city' => 'Hannover',
                'country_code' => 'DE',
                'prefix' => 'HAN',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'Malankara Syriac Orthodox Congregation Regensburg, Germany',
                'name' => 'Malankara Syriac Orthodox Congregation Regensburg',
                'short' => 'Regensburg',
                'city' => 'Regensburg',
                'country_code' => 'DE',
                'prefix' => 'REG',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'Malankara Syriac Orthodox Service Centre Erfurt, Germany',
                'name' => 'Malankara Syriac Orthodox Service Centre Erfurt',
                'short' => 'Erfurt',
                'city' => 'Erfurt',
                'country_code' => 'DE',
                'prefix' => 'ERF',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. Elias Malankara Syriac Orthodox Congregation Stockholm, Sweden',
                'name' => 'St. Elias Malankara Syriac Orthodox Congregation Stockholm',
                'short' => 'Stockholm Elias',
                'city' => 'Stockholm',
                'country_code' => 'SE',
                'prefix' => 'STH',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'Malankara Syriac Orthodox Service Centre Napoli, Italy',
                'name' => 'Malankara Syriac Orthodox Service Centre Napoli',
                'short' => 'Napoli',
                'city' => 'Naples',
                'country_code' => 'IT',
                'prefix' => 'NAP',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'Malankara Syriac Orthodox Service Centre Sicily, Italy',
                'name' => 'Malankara Syriac Orthodox Service Centre Sicily',
                'short' => 'Sicily',
                'city' => 'Palermo',
                'country_code' => 'IT',
                'prefix' => 'SIC',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'Malankara Syriac Orthodox Service Centre Warsaw, Poland',
                'name' => 'Malankara Syriac Orthodox Service Centre Warsaw',
                'short' => 'Warsaw',
                'city' => 'Warsaw',
                'country_code' => 'PL',
                'prefix' => 'WAR',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. Mary’s Malankara Syriac Orthodox Congregation Krakow, Poland',
                'name' => 'St. Mary\'s Malankara Syriac Orthodox Congregation Krakow',
                'short' => 'Krakow',
                'city' => 'Kraków',
                'country_code' => 'PL',
                'prefix' => 'KRA',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'St. Basil Malankara Syriac Orthodox Congregation Hamburg, Germany',
                'name' => 'St. Basil Malankara Syriac Orthodox Congregation Hamburg',
                'short' => 'Hamburg',
                'city' => 'Hamburg',
                'country_code' => 'DE',
                'prefix' => 'HAM',
                'status' => 'active',
                'show' => true,
            ],
            [
                'raw_name' => 'Malankara Syriac Orthodox Service Centre / Congregation Belgium',
                'name' => 'Malankara Syriac Orthodox Service Centre / Congregation Belgium',
                'short' => 'Belgium',
                'city' => 'Brussels',
                'country_code' => 'BE',
                'prefix' => 'BEL',
                'status' => 'active',
                'show' => true,
            ],
            // Stuttgart - Seeded as upcoming/unverified (show_on_website = false)
            [
                'raw_name' => 'St. Ignatius Malankara Syriac Orthodox Congregation Stuttgart, Germany',
                'name' => 'St. Ignatius Malankara Syriac Orthodox Congregation Stuttgart',
                'short' => 'Stuttgart',
                'city' => 'Stuttgart',
                'country_code' => 'DE',
                'prefix' => 'STU',
                'status' => 'upcoming', // Seeded as upcoming/unverified per rules
                'show' => false, // show_on_website = false
                'notes' => 'Seeded as upcoming/unverified. Source data from Stuttgart menu sections.',
            ],
        ];

        foreach ($rawChurches as $rc) {
            $country = Country::where('iso2', $rc['country_code'])->first();
            if (!$country) {
                continue;
            }

            // Determine church_type
            $type = 'church';
            if (Str::contains($rc['name'], 'Service Centre')) {
                $type = 'service_centre';
            } elseif (Str::contains($rc['name'], 'Congregation')) {
                $type = 'congregation';
            } elseif (Str::contains($rc['name'], 'Parish')) {
                $type = 'parish';
            }

            Church::updateOrCreate(
                ['public_page_slug' => Str::slug($rc['short'])],
                [
                    'diocese_id' => $diocese->id,
                    'country_id' => $country->id,
                    'slug' => Str::slug($rc['short']),
                    'name' => $rc['name'],
                    'short_name' => $rc['short'],
                    'church_type' => $type,
                    'patron_saint' => Str::contains($rc['name'], 'Mary') ? 'St. Mary' : (Str::contains($rc['name'], 'Peter') ? 'St. Peter & St. Paul' : (Str::contains($rc['name'], 'Thomas') ? 'St. Thomas' : (Str::contains($rc['name'], 'George') ? 'St. George' : (Str::contains($rc['name'], 'Elias') ? 'St. Elias' : (Str::contains($rc['name'], 'Ignatius') ? 'St. Ignatius' : 'St. Mary'))))),
                    'city' => $rc['city'],
                    'state_region' => null,
                    'country' => $country->name,
                    'canonical_status' => $rc['status'],
                    'membership_code_prefix' => $rc['prefix'],
                    'show_on_website' => $rc['show'],
                    'source_url' => 'https://msoc-europe.com/parishes.php',
                    'source_raw_name' => $rc['raw_name'],
                    'source_verified_at' => now(),
                    'source_notes' => $rc['notes'] ?? 'Seeded from parishes list.',
                ]
            );
        }
    }
}
