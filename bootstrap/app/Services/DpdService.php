<?php

namespace App\Services;

use App\Models\ShippingMethod;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DpdService
{
    /**
     * The base URL for the API.
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * The username.
     *
     * @var string
     */
    protected $username;

    /**
     * The password.
     *
     * @var string
     */
    protected $password;

    /**
     * The API token.
     *
     * @var string|null
     */
    protected $apiToken;

    /**
     * The shipping method.
     *
     * @var ShippingMethod
     */
    protected $shippingMethod;

    /**
     * DpdService constructor.
     * 
     * @param int $shippingMethodId
     */
    public function __construct($shippingMethodId)
    {
        // Find the shipping method by ID
        $this->shippingMethod = ShippingMethod::find($shippingMethodId);

        // If the shipping method doesn't exist, throw an exception
        if (!$this->shippingMethod) {
            throw new \Exception('Shipping method not found.');
        }

        // Set properties from the database values
        $this->apiUrl = $this->shippingMethod->api_url;
        $this->username = $this->shippingMethod->username;
        $this->password = $this->shippingMethod->password;
        $this->apiToken = $this->shippingMethod->api_token; // Optional, if used
    }

    /**
     * Fetch API token or create a new one if invalid or missing.
     *
     * @return void
     */
    public function fetchOrCreateApiToken()
    {
        if (!$this->apiToken || !$this->isValidApiToken($this->apiToken)) {
            // If API token is invalid or missing, attempt to fetch a new one
            $this->apiToken = $this->getValidApiToken();

            // Update the shipping method with the new token
            $this->shippingMethod->api_token = $this->apiToken;
            $this->shippingMethod->save();
        }
    }

    /**
     * Validate the current API token.
     *
     * @param string $apiToken
     * @return bool
     */
    private function isValidApiToken($apiToken)
    {
        // Logic to validate API token, e.g., make an API request to check if the token works
        $response = Http::withToken($apiToken)->get($this->apiUrl . '/auth/me');

        return $response->successful();
    }

    /**
     * Get a valid API token from the list of available tokens or create a new one.
     *
     * @return string
     */
    private function getValidApiToken()
    {
        // Call the API to fetch available tokens and select the valid one
        $response = Http::withBasicAuth($this->username, $this->password)
                        ->get($this->apiUrl . '/auth/token-secrets');

        if ($response->successful()) {
            $tokens = $response->json();

            // Find the token with name 'CRM' or handle the case when none are valid
            foreach ($tokens as $token) {
                if ($token['name'] === 'CRM' && $this->isValidApiToken($token['token'])) {
                    return $token['token'];
                }
            }
        }

        // If no valid token found, create a new one
        return $this->createNewApiToken();
    }

    /**
     * Create a new API token.
     *
     * @return string
     */
    private function createNewApiToken()
    {
        // Prepare the payload with the required fields
        $payload = [
            'name' => 'CRM',
            'ttl'  => 99999999999,
        ];

        // Request to create a new API token using the provided username and password
        $response = Http::withBasicAuth($this->username, $this->password)
                        ->post($this->apiUrl . '/auth/tokens', $payload);

        // Check if the response is successful and return the token
        if ($response->successful()) {
            // Extract token from the response
            $apiToken = $response->json()['token'];

            $api_secret = $response->json()['secretId'];

            // Save the token in the database
            $shippingMethod = ShippingMethod::where('username', $this->username)->first();
            
            if ($shippingMethod) {
                // Update the existing shipping method with the new API token
                $shippingMethod->update([
                    'api_token'  => $apiToken,
                    'api_secret' => $api_secret,
                    'validUntil' => now()->addSeconds(99999999999)
                ]);
            }

            return $apiToken;  // Return the token after saving it to the DB
        }

        // Throw an exception if the API token creation fails
        throw new \Exception('Failed to generate a new API token.');
    }

     /**
     * Fetch shipments based on query parameters.
     *
     * @param array $queryParams
     * @return \Illuminate\Http\Response
     */
    public function getShipments(array $queryParams)
    {
        $this->fetchOrCreateApiToken();

        $queryString = http_build_query($queryParams);
        
        $response = Http::withToken($this->apiToken)->get($this->apiUrl . '/shipments?'.$queryString);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to fetch shipments.');
    }

    /**
     * Create new shipments.
     *
     * @param array $shipmentData
     * @return \Illuminate\Http\Response
     */
    public function createShipments(array $shipmentData)
    {
        $this->fetchOrCreateApiToken();

        $response = Http::withToken($this->apiToken)
                        ->post($this->apiUrl . '/shipments', $shipmentData);

        if ($response->successful()) {
            $data = $response->json();
            return is_array($data) && isset($data[0]) ? $data[0] : $data;
        }

        $error = $response->json();
        if (is_array($error) && isset($error[0]['title'])) {
            $title = $error[0]['title'];
            $detail = $this->formatErrorDetails($error[0]['detail'] ?? []);
            throw new \Exception("$title - $detail");
        } elseif (isset($error['title'])) {
            $title = $error['title'];
            $detail = $this->formatErrorDetails($error['detail'] ?? []);
            throw new \Exception("$title - $detail");
        }
    }

    
    private function formatErrorDetails(array $details): string
    {
        if (empty($details)) {
            return 'No additional details available.';
        }

        $formattedDetails = [];
        foreach ($details as $key => $message) {
            // Clean up keys for readability
            $formattedKey = str_replace(['0.', '_'], ['', ' '], $key);
            $formattedDetails[] = ucfirst($formattedKey) . ": $message";
        }

        return implode(', ', $formattedDetails);
    }

    public function getLockers($countryCode)
    {
        $this->fetchOrCreateApiToken();

        $response = Http::withToken($this->apiToken)
                        ->get($this->apiUrl . '/lockers?countryCode=' . $countryCode);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to load Lockers.');
    }

    /**
     * Delete shipments based on IDs.
     *
     * @param array $ids
     * @return \Illuminate\Http\Response
     */
    public function deleteShipments($id)
    {
        // Fetch or create the API token if not already set
        $this->fetchOrCreateApiToken();

        // Send the DELETE request
        $response = Http::withToken($this->apiToken)->delete($this->apiUrl . '/shipments?ids=' . $id);

        // Check if the response was successful
        if ($response->successful()) {
            // If the response has no content (204 No Content), return an empty array or a success message
            if ($response->status() == 204) {
                return ['success' => true, 'message' => 'Shipment deleted successfully'];
            }

            // Otherwise, return the response as JSON
            return $response->json();
        }

        // If the request was not successful, throw an exception with the error message
        throw new \Exception('Failed to delete shipments.');
    }


     /**
     * Create a shipment label.
     *
     * @param array $labelData
     * @return mixed
     * @throws \Exception
     */
    public function createShipmentLabel(array $labelData)
    {
        $this->fetchOrCreateApiToken();

        // Send POST request to create shipment label
        $response = Http::withToken($this->apiToken)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])
            ->post($this->apiUrl . '/shipments/labels', $labelData);
        if ($response->successful()) {
            return $response->json();
        }
        // Handle error responses
        $this->handleErrorResponse($response);
    }

    /**
     * Get invoice by UUID.
     *
     * @param string $uuid
     * @return mixed
     * @throws \Exception
     */
    public function getInvoiceByUuid($uuid)
    {
        $this->fetchOrCreateApiToken();

        // Send GET request to fetch invoice
        $response = Http::withToken($this->apiToken)
                        ->get($this->apiUrl . '/invoices/' . $uuid);

        if ($response->successful()) {
            return $response->json(); // Returns InvoiceResponseDTO
        }

        // Handle error responses
        $this->handleErrorResponse($response);
    }

    /**
     * Handle API error responses.
     *
     * @param \Illuminate\Http\Client\Response $response
     * @throws \Exception
     */
    private function handleErrorResponse($response)
    {
        $statusCode = $response->status();
        $errorMessage = $response->json()['message'] ?? 'Unexpected error';

        // More specific handling based on status code
        switch ($statusCode) {
            case 400:
                $errorMessage = 'Bad request. Please check the parameters.';
                break;
            case 401:
                $errorMessage = 'Unauthorized. Check the API credentials.';
                break;
            case 403:
                $errorMessage = 'Forbidden. Check your permissions.';
                break;
            case 404:
                $errorMessage = 'Resource not found.';
                break;
            case 500:
                $errorMessage = 'Internal server error. Please try again later.';
                break;
        }

        Log::error('DPD API Error', ['status' => $statusCode, 'message' => $errorMessage]);

        throw new \Exception($errorMessage);
    }
}