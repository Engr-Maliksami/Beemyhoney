<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;


class ShippingMethodsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('shipping_methods')->insert(
            [
                'name' => 'DPD Latvia',
                'username' => 'aijaloce@hotmail.com',
                'password' => 'Aijabono123&',
                'api_key' => '',
                'api_secret' => '',
                'api_token' => '',
                'api_url' => 'https://eserviss.dpd.lv/api/v1',
                'additional_info' => 'DPD Latvia API configuration',
                'validUntil' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pickup',
                'username' => '',
                'password' => '',
                'api_key' => '',
                'api_secret' => '',
                'api_token' => '',
                'api_url' => '',
                'additional_info' => '',
                'validUntil' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
