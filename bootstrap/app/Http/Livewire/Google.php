<?php

namespace App\Http\Livewire;

use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_Spreadsheet;
use Google_Service_Sheets_Request;
use Google_Service_Sheets_BatchUpdateSpreadsheetRequest;
use Google_Service_Sheets_ValueRange;
use Google_Service_Drive;
use Google_Service_Drive_Permission;
use Livewire\Component;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Models\UserGoogleAccount;


class Google extends Component
{
    public $userGoogleAccounts;

    protected $listeners = [
        'AccountDeleted',
        'confirmDeleteAccount'
    ];

    public function authenticate()
    {
        $credentialsPath = base_path('google-credentials.json');
        $client = new Google_Client();
        $client->setAuthConfig($credentialsPath);
        $client->setRedirectUri(route('google.callback'));
        $client->setAccessType("offline");
        $client->setPrompt('consent');
        $client->setApprovalPrompt("consent");
        $client->setIncludeGrantedScopes(true);
        $client->addScope([
            Google_Service_Sheets::SPREADSHEETS,
            Google_Service_Drive::DRIVE,
            'openid',
            'email',
            'profile',
        ]);
        return redirect()->to($client->createAuthUrl());
    }

    public function handleCallback(Request $request)
    {
        if (!$request->has('code') || $request->input('error') === 'access_denied') {
            return redirect()->route('google accounts')->with('error', 'Google Login Canceled by User.');
        }

        $credentialsPath = base_path('google-credentials.json');
        $client = new Google_Client();
        $client->setAuthConfig($credentialsPath);
        $client->setRedirectUri(route('google.callback'));
        $client->addScope([
            Google_Service_Sheets::SPREADSHEETS,
            Google_Service_Drive::DRIVE,
            'openid',
            'email',
            'profile',
        ]);
        $accessToken = $client->fetchAccessTokenWithAuthCode($request->get('code'));
        $accessTokenValue = $accessToken['access_token'];
        $refreshToken = isset($accessToken['refresh_token']) ? $accessToken['refresh_token'] : null;
        $tokenType = $accessToken['token_type'];
        $expiresIn = $accessToken['expires_in'];

        $userInfo = $client->verifyIdToken($accessToken['id_token']);
        $userId = $userInfo['sub'];
        $userEmail = $userInfo['email'];
        $userName = $userInfo['name'];

        $existingUser = UserGoogleAccount::where('email', $userEmail)->first();

        if ($existingUser) {
            $existingUser->update([
                'google_user_id' => $userId,
                'name' => $userName,
                'access_token' => $accessTokenValue,
                'refresh_token' => $refreshToken,
                'token_type' => $tokenType,
                'expires_in' => $expiresIn,
            ]);
        } else {
            $newUser = UserGoogleAccount::create([
                'email' => $userEmail,
                'google_user_id' => $userId,
                'name' => $userName,
                'access_token' => $accessTokenValue,
                'refresh_token' => $refreshToken,
                'token_type' => $tokenType,
                'expires_in' => $expiresIn,
            ]);
        }

        return redirect()->route('google accounts')->with('success', 'Google Account Added Successfully.');
    }

    public function getGoogleClient()
    {
        $UserGoogleAccount = UserGoogleAccount::first();
        $accessTokenValue = ($UserGoogleAccount) ? $UserGoogleAccount->access_token : null;
        $refreshTokenValue = ($UserGoogleAccount) ? $UserGoogleAccount->refresh_token : null;

        if ($accessTokenValue)
        {
            $credentialsPath = base_path('google-credentials.json');
            $client = new Google_Client();
            $client->setAuthConfig($credentialsPath);
            $client->addScope([
                Google_Service_Sheets::SPREADSHEETS,
                'openid',
                'email',
                'profile',
            ]);

            $client->setAccessToken($accessTokenValue);

            if ($client->isAccessTokenExpired()) {
                try {
                    $newAccessToken = $client->fetchAccessTokenWithRefreshToken($refreshTokenValue);
                    $expiresIn = $newAccessToken['expires_in'];
                    $accessTokenValue = $newAccessToken['access_token'];
                    $UserGoogleAccount->update([
                        'access_token' => $accessTokenValue,
                        'expires_in' => $expiresIn,
                    ]);
                    $client->setAccessToken($accessTokenValue);
                } catch (\Exception $e) {
                    echo('Failed to refresh access token: ' . $e->getMessage());
                }
            }
            return $client;
        }
        return null;
    }

    public function createNewSpreadsheet($clientName)
    {
        $client = $this->getGoogleClient();
        if ($client)
        {
            $service = new Google_Service_Sheets($client);
            $spreadsheet = new Google_Service_Sheets_Spreadsheet([
                'properties' => [
                    'title' => $clientName.'\'s Lead Sheet',
                ],
            ]);
            $spreadsheet = $service->spreadsheets->create($spreadsheet);
            $spreadsheetId = $spreadsheet->spreadsheetId;

            // Set the permissions to make the spreadsheet public
            $driveService = new Google_Service_Drive($client);
            $driveService->permissions->create(
                $spreadsheetId,
                new Google_Service_Drive_Permission([
                    'type' => 'anyone',
                    'role' => 'reader',
                ])
            );

            return $spreadsheetId;
        }
    }

    public function renameSheet($spreadsheetId, $sheetId, $newSheetName)
    {
        $client = $this->getGoogleClient();
        if ($client)
        {
            $service = new Google_Service_Sheets($client);

            $requests = [
                new Google_Service_Sheets_Request([
                    'updateSheetProperties' => [
                        'properties' => [
                            'sheetId' => $sheetId,
                            'title' => $newSheetName,
                        ],
                        'fields' => 'title',
                    ],
                ]),
            ];

            $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                'requests' => $requests,
            ]);

            $response = $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
            return $sheetId;
        }
    }

    public function deleteSheet($spreadsheetId, $sheetId)
    {
        $client = $this->getGoogleClient();
        if ($client)
        {
            $service = new Google_Service_Sheets($client);

            $requests = [
                new Google_Service_Sheets_Request([
                    'deleteSheet' => [
                        'sheetId' => $sheetId,
                    ],
                ]),
            ];

            $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                'requests' => $requests,
            ]);

            $response = $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
            return $response->getSpreadsheetId();
        }
    }


    public function addNewSheet($spreadsheetId, $newSheetName)
    {
        $client = $this->getGoogleClient();
        if ($client)
        {
            $service = new Google_Service_Sheets($client);

            $requests = [
                new Google_Service_Sheets_Request([
                    'addSheet' => [
                        'properties' => [
                            'title' => $newSheetName,
                        ],
                    ],
                ]),
            ];

            $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                'requests' => $requests,
            ]);

            $response = $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
            $newSheetId = $response->replies[0]->addSheet->properties->sheetId;
            return $newSheetId;
        }
    }

    public function appendDataToSheet($spreadsheetId, $range, $header, $data)
    {
        Log::info('Into Function appendDataToSheet');
        $client = $this->getGoogleClient();
        if ($client) {
            Log::info('Client Condition');
            $service = new Google_Service_Sheets($client);

            $response = $service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues();

            if (empty($values)) {
                $values[] = $header;
            }
            $values[] = $data;
            $dataBody = new Google_Service_Sheets_ValueRange([
                'values' => $values,
            ]);
            $dataParams = [
                'valueInputOption' => 'USER_ENTERED',
            ];
            Log::info('Update Data:', [
                'spreadsheetId' => $spreadsheetId,
                'range' => $range,
                'body' => $dataBody,
                'params' => $dataParams,
            ]);
            $service->spreadsheets_values->update($spreadsheetId, $range, $dataBody, $dataParams);
        }
    }

    public function confirmDeleteAccount($googleId)
    {
        $userGoogleAccounts = UserGoogleAccount::where('google_user_id', $googleId)->first();
        if ($userGoogleAccounts) {
            $userGoogleAccounts->delete();
        }
        $this->emit('AccountDeleted');
    }


    public function AccountDeleted()
    {
        return redirect()->route('google accounts')->with('success', 'Google Account deleted successfully');
    }

    public function render()
    {
        $this->userGoogleAccounts = UserGoogleAccount::all();
        return view('livewire.google-list');
    }

}