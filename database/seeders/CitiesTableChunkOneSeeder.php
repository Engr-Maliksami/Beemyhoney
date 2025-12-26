<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CitiesTableChunkOneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Read cities.json file from storage
        $citiesJson = file_get_contents(storage_path('app/cities.json'));

        // Check if the file was read correctly
        if (!$citiesJson) {
            $this->command->error('Failed to read cities.json file.');
            return;
        }

        // Decode JSON data
        $citiesArray = json_decode($citiesJson, true);

        // Check if decoding was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('Invalid JSON data. Error: ' . json_last_error_msg());
            return;
        }

        // Prepare cities for insertion
        $citiesToInsert = [];
        foreach ($citiesArray as $city) {
            // Fetch country_id based on countryCode
            $country = DB::table('countries')
                ->where('code', $city['countryCode'])
                ->first();

            if ($country) {
                $citiesToInsert[] = [
                    'name' => $city['cityName'],
                    'country_id' => $country->id,
                ];
            } else {
                $this->command->warn('No country found for code: ' . $city['countryCode']);
            }
        }

        // Insert the data into the database in chunks
        if (!empty($citiesToInsert)) {
            $chunkSize = 1000; // Adjust based on the number of cities
            foreach (array_chunk($citiesToInsert, $chunkSize) as $chunk) {
                DB::table('cities')->insert($chunk);
            }

            $this->command->info(count($citiesToInsert) . ' cities have been seeded successfully.');
        } else {
            $this->command->warn('No cities were inserted. Please check the JSON file or country codes.');
        }
    }
}