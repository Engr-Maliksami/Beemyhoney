<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class APIController extends Controller
{
    public function getContact($id)
    {
        $filteredContacts = []; // To store filtered contact data
        $page = 1; // Start from the first page
        $totalPages = 1; // Initialize total pages to 1 to enter the loop

        // Loop through all pages
        while ($page <= $totalPages) {
            $response = Http::withHeaders([
                'Authorization' => 'Token token=' . env('AUTHORIZE_TOKEN')
            ])->get("https://istitutoformativoaladia.myfreshworks.com/crm/sales/api/contacts/view/{$id}?per_page=100&page={$page}");

            // Check if the response is successful
            if ($response->successful()) {
                // Get the data from the response
                $data = $response->json();

                // Loop through contacts and extract desired keys
                foreach ($data['contacts'] as $contact) {
                    if (!isset($contact['custom_field']['cf_richiesta_data'], $contact['custom_field']['cf_inserzione'], $contact['custom_field']['cf_nome_campagna'])) {
                        continue;
                    }
                    $filteredContacts[] = [
                        'cf_totale' => $contact['custom_field']['cf_totale'] ?? null,
                        'cf_prodotto' => $contact['custom_field']['cf_prodotto'] ?? null,
                        'cf_inserzione' => $contact['custom_field']['cf_inserzione'] ?? null,
                        'cf_nome_campagna' => $contact['custom_field']['cf_nome_campagna'] ?? null,
                        'date' => Carbon::parse($contact['custom_field']['cf_richiesta_data'])->timezone('Europe/Paris')->toIso8601String()
                    ];
                }

                // Get the total number of pages from the response metadata
                $totalPages = $data['meta']['total_pages'];

                // Increment the page number to fetch the next page
                $page++;
            } else {
                return response()->json(['error' => 'Unable to fetch contacts'], 500);
            }
        }

        // Return the filtered contacts
        return response()->json(["data" => $filteredContacts]);
    }

    public function formatToISO8601($date)
    {
        try {
            $dateTime = new \DateTime($date);
            return $dateTime->format('Y-m-d\TH:i:s.vP'); // ISO 8601 format
        } catch (\Exception $e) {
            return null; // Return null if the date cannot be parsed
        }
    }
}
