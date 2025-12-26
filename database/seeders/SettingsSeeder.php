<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('settings')->insert([
            [
                'key' => 'payment_info',
                'value' => '<ol>
                            <li>Paypal: <strong>aijaloce@hotmail.com</strong></li>
                            <li>Bank Transfer:
                                <ul>
                                    <li>Account holder: <strong>Bxxxxxy Ltd</strong></li>
                                    <li>BIC: <strong>xxxxxxxxxx</strong></li>
                                    <li>IBAN: <strong>xxxxxxx</strong></li>
                                </ul>
                            </li>
                        </ol>',
                'type' => 'textarea'
            ],
            [
                'key' => 'euro_rate',
                'value' => 1.17,
                'type' => 'number'
            ],
            [
                'key' => 'delivery_rate',
                'value' => 3.30,
                'type' => 'number'
            ],
        ]);
    }
}
