<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(CountriesTableSeeder::class);
        $this->call(CitiesTableChunkOneSeeder::class);
        $this->call(ShippingMethodsTableSeeder::class);
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@drebjunoliktava.com',
            'phone' => '1234567890',
            'password' => ('Admin786**'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
