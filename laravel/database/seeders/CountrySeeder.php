<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'Austria', 'iso2' => 'AT', 'iso3' => 'AUT', 'phone_code' => '43', 'currency' => 'EUR', 'timezone' => 'Europe/Vienna'],
            ['name' => 'Germany', 'iso2' => 'DE', 'iso3' => 'DEU', 'phone_code' => '49', 'currency' => 'EUR', 'timezone' => 'Europe/Berlin'],
            ['name' => 'Italy', 'iso2' => 'IT', 'iso3' => 'ITA', 'phone_code' => '39', 'currency' => 'EUR', 'timezone' => 'Europe/Rome'],
            ['name' => 'Denmark', 'iso2' => 'DK', 'iso3' => 'DNK', 'phone_code' => '45', 'currency' => 'DKK', 'timezone' => 'Europe/Copenhagen'],
            ['name' => 'Norway', 'iso2' => 'NO', 'iso3' => 'NOR', 'phone_code' => '47', 'currency' => 'NOK', 'timezone' => 'Europe/Oslo'],
            ['name' => 'Netherlands', 'iso2' => 'NL', 'iso3' => 'NLD', 'phone_code' => '31', 'currency' => 'EUR', 'timezone' => 'Europe/Amsterdam'],
            ['name' => 'Sweden', 'iso2' => 'SE', 'iso3' => 'SWE', 'phone_code' => '46', 'currency' => 'SEK', 'timezone' => 'Europe/Stockholm'],
            ['name' => 'Malta', 'iso2' => 'MT', 'iso3' => 'MLT', 'phone_code' => '356', 'currency' => 'EUR', 'timezone' => 'Europe/Malta'],
            ['name' => 'Bulgaria', 'iso2' => 'BG', 'iso3' => 'BGR', 'phone_code' => '359', 'currency' => 'BGN', 'timezone' => 'Europe/Sofia'],
            ['name' => 'Poland', 'iso2' => 'PL', 'iso3' => 'POL', 'phone_code' => '48', 'currency' => 'PLN', 'timezone' => 'Europe/Warsaw'],
            ['name' => 'Belgium', 'iso2' => 'BE', 'iso3' => 'BEL', 'phone_code' => '32', 'currency' => 'EUR', 'timezone' => 'Europe/Brussels'],
            ['name' => 'Switzerland', 'iso2' => 'CH', 'iso3' => 'CHE', 'phone_code' => '41', 'currency' => 'CHF', 'timezone' => 'Europe/Zurich'],
            ['name' => 'United Kingdom', 'iso2' => 'GB', 'iso3' => 'GBR', 'phone_code' => '44', 'currency' => 'GBP', 'timezone' => 'Europe/London'],
            ['name' => 'Ireland', 'iso2' => 'IE', 'iso3' => 'IRL', 'phone_code' => '353', 'currency' => 'EUR', 'timezone' => 'Europe/Dublin'],
            ['name' => 'France', 'iso2' => 'FR', 'iso3' => 'FRA', 'phone_code' => '33', 'currency' => 'EUR', 'timezone' => 'Europe/Paris'],
        ];

        foreach ($countries as $c) {
            Country::updateOrCreate(['iso2' => $c['iso2']], $c);
        }
    }
}
