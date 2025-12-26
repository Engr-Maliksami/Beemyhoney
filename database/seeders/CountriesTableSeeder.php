<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CountriesTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        $jsonPUDO = '[
            {
                "name": "ESTONIA",
                "code": "EE",
                "value": "EE"
            },
            {
                "name": "LATVIA",
                "code": "LV",
                "value": "LV"
            },
            {
                "name": "LITHUANIA",
                "code": "LT",
                "value": "LT"
            },
            {
                "name": "AUSTRIA",
                "code": "AT",
                "value": "AT"
            },
            {
                "name": "BELGIUM",
                "code": "BE",
                "value": "BE"
            },
            {
                "name": "BULGARIA",
                "code": "BG",
                "value": "BG"
            },
            {
                "name": "CROATIA",
                "code": "HR",
                "value": "HR"
            },
            {
                "name": "CZECHIA",
                "code": "CZ",
                "value": "CZ"
            },
            {
                "name": "DENMARK",
                "code": "DK",
                "value": "DK"
            },
            {
                "name": "FINLAND",
                "code": "FI",
                "value": "FI"
            },
            {
                "name": "FRANCE",
                "code": "FR",
                "value": "FR"
            },
            {
                "name": "GERMANY",
                "code": "DE",
                "value": "DE"
            },
            {
                "name": "GREECE",
                "code": "GR",
                "value": "GR"
            },
            {
                "name": "HUNGARY",
                "code": "HU",
                "value": "HU"
            },
            {
                "name": "IRELAND",
                "code": "IE",
                "value": "IE"
            },
            {
                "name": "ITALY",
                "code": "IT",
                "value": "IT"
            },
            {
                "name": "LUXEMBOURG",
                "code": "LU",
                "value": "LU"
            },
            {
                "name": "NEDERLAND",
                "code": "NL",
                "value": "NL"
            },
            {
                "name": "POLAND",
                "code": "PL",
                "value": "PL"
            },
            {
                "name": "PORTUGAL",
                "code": "PT",
                "value": "PT"
            },
            {
                "name": "ROMANIA",
                "code": "RO",
                "value": "RO"
            },
            {
                "name": "SLOVAKIA",
                "code": "SK",
                "value": "SK"
            },
            {
                "name": "SLOVENIA",
                "code": "SI",
                "value": "SI"
            },
            {
                "name": "SPAIN",
                "code": "ES",
                "value": "ES"
            },
            {
                "name": "SWEDEN",
                "code": "SE",
                "value": "SE"
            },
            {
                "name": "SWITZERLAND",
                "code": "CH",
                "value": "CH"
            }
        ]';

        $countriesPUDO = json_decode($jsonPUDO, true);

        // Ensure database table is clean
        \DB::table('countries')->delete();

        // Insert updated countries
        \DB::table('countries')->insert($countriesPUDO);
    }
}