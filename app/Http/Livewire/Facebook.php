<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Models\UserFacebookAccount;
use App\Models\UserFacebookAdaccount;
use App\Models\UserFacebookPage;
use App\Models\UserFacebookPageForm;
use App\Models\FacebookWebhookCall;
use App\Models\UserZap;
use App\Models\UserZapLinks;
use App\Models\UserLeads;
use App\Models\UserLeadDetails;
use App\Models\UserClients;
use App\Models\UserFacebookBusiness;
use App\Models\UserFacebookAdaccountBusiness;
use App\Models\UserCustomers;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Http\Livewire\Google;
use App\Models\UserFacebookPageBusiness;
use Illuminate\Support\Facades\DB;
use App\Models\UserFacebookPageAccess;
use App\Models\LeadSchedule;
use Illuminate\Support\Str;


class Facebook extends Component
{
    public $userFacebookAccountsList;
    public $userFacebookAccountsListId;
    public $userFacebookAdAccountsList;
    public $userFacebookBusinessList;
    public $userFacebookPagesList;
    public $userFacebookPageFormsList;
    public $refreshFacebookData = false;

    protected $listeners = [
        'AccountDeleted',
        'confirmDeleteAccount'
    ];

    public function redirectToFacebook()
    {
        $permissions = [
            'email',
            'public_profile',
            'pages_manage_posts',
            'pages_manage_engagement',
            'pages_manage_metadata',
            'pages_messaging',
            'pages_read_engagement',
            'pages_show_list',
            'read_insights',
            'pages_manage_ads',
            'business_management',
            'ads_management',
            'ads_read'
        ];
        return Socialite::driver('facebook')
            ->scopes($permissions)
            ->stateless()
            ->redirect();
    }

    public function handleFacebookCallback(Request $request)
    {
        if (!$request->has('code') || $request->has('denied')) {
            return redirect()->route('facebook accounts')->with('error', 'Facebook Login Canceled by User.');
        }

        try {
            $socialiteUser = Socialite::driver('facebook')->stateless()->user();
        } catch (InvalidStateException $e) {
            Log::error('Facebook OAuth error: ' . $e->getMessage());
            return redirect()->route('facebook accounts')->with('error', 'Invalid state, please try logging in again.');
        }
        
        $UserFBAccount = UserFacebookAccount::withTrashed()
            ->where('facebook_id', $socialiteUser->id)
            ->orWhere('email', $socialiteUser->email)
            ->first();
        if (!$UserFBAccount) {
            $UserFBAccount = new UserFacebookAccount();
            $UserFBAccount->facebook_id = $socialiteUser->id;
            $UserFBAccount->name = $socialiteUser->name;
            $UserFBAccount->email = ($socialiteUser->email) ? ($socialiteUser->email) : "";
            $UserFBAccount->access_token = $socialiteUser->token;
            $UserFBAccount->created_at = Carbon::now();
            $UserFBAccount->updated_at = Carbon::now();
            $UserFBAccount->save();
        } elseif ($UserFBAccount->trashed()) {
            $UserFBAccount->restore();

            $UserFBAccount->update([
                'access_token' => $socialiteUser->token,
                'facebook_id' => $socialiteUser->id,
                'name' => $socialiteUser->name,
                'email' => $socialiteUser->email,
                'updated_at' => Carbon::now()
            ]);
        }
        else{
            $UserFBAccount->update([
                'access_token' => $socialiteUser->token,
                'facebook_id' => $socialiteUser->id,
                'name' => $socialiteUser->name,
                'email' => $socialiteUser->email,
                'updated_at' => Carbon::now()
            ]);
        }

        $this->getBusinessAccounts($socialiteUser->id,$socialiteUser->name,$socialiteUser->token);

        $this->getAdAccounts($socialiteUser->id,$socialiteUser->name,$socialiteUser->token);

        $this->getPages($socialiteUser->id,$socialiteUser->token);

        $this->getPromoteAdAccounts($socialiteUser->id,$socialiteUser->name,$socialiteUser->token);

        return redirect()->route('facebook accounts')->with('success', 'Facebook Account Added Successfully.');
    }

    public function refreshData($userFacebookID)
    {
        $FBAccounts = UserFacebookAccount::where("facebook_id",$userFacebookID)
            ->first();

        if (!empty($FBAccounts))
        {
            $this->getBusinessAccounts($userFacebookID,$FBAccounts->name,$FBAccounts->access_token);
        
            $this->getAdAccounts($userFacebookID,$FBAccounts->name,$FBAccounts->access_token);
        
            $this->getPages($userFacebookID,$FBAccounts->access_token,true);

            $this->getPromoteAdAccounts($userFacebookID,$FBAccounts->name,$FBAccounts->access_token);
        
            return redirect()->route('facebook accounts')->with('success', 'Facebook Account Data Updated Successfully.');
        } else{
            return redirect()->route('facebook accounts')->with('error', 'Facebook Account Data Unsuccessfully.');
        }
    }

    public function getPages($userFacebookID, $accessToken, $refresh = false)
    {
        try {
            $response = $this->FB_Call('me/accounts?fields=cover,emails,picture,id,name,url,username,access_token,business&&limit=9999999', $accessToken);
            $pages = isset($response['data']) ? $response['data'] : array();
            foreach ($pages as $page) {
                if ($refresh)
                {
                    $UserFBPage =  UserFacebookPage::where('facebook_id', $userFacebookID)
                        ->where('page_id', $page['id'])
                        ->first();
                    $addData = ($UserFBPage) ? false : true;
                }
                else
                {
                    UserFacebookPageForm::where('page_id', $page['id'])
                        ->delete();
                    $UserFBPage =  UserFacebookPage::where('facebook_id', $userFacebookID)
                        ->where('page_id', $page['id'])
                        ->delete();
                    $addData = true;
                }
                if ($addData)
                {
                    UserFacebookPage::create([
                        'facebook_id' => $userFacebookID,
                        'page_id' => $page['id'],
                        'name' => isset($page['name']) ? $page['name'] : null,
                        'cover_url' => isset($page['cover']['source']) ? $page['cover']['source'] : null,
                        'email' => isset($page['emails'][0]) ? $page['emails'][0] : null,
                        'username' => isset($page['username']) ? $page['username'] : null,
                        'page_access_token' => isset($page['access_token']) ? $page['access_token'] : null,
                    ]);
                    
                }

                if (isset($page['business'])){
                    $HaveBusiness = UserFacebookBusiness::where('facebook_id',$userFacebookID)
                        ->where('business_id',$page['business']['id'])
                        ->first();
                    if (!$HaveBusiness)
                    {
                        // Create Business If I dont' have Access 
                        $userFacebookBusiness = new UserFacebookBusiness();
                        $userFacebookBusiness->facebook_id = $userFacebookID;
                        $userFacebookBusiness->business_id = $page['business']['id'];
                        $userFacebookBusiness->business_name = $page['business']['name'];
                        $userFacebookBusiness->save();
                    }
                    $ExistBusiness = UserFacebookPageBusiness::where('business_id', $page['business']['id'])
                        ->where('page_id', $page['id'])
                        ->first();
                    if (!$ExistBusiness)
                    {
                        UserFacebookPageBusiness::create([
                            'page_id' => $page['id'],
                            'facebook_id' => $userFacebookID,
                            'business_id' => $page['business']['id'],
                        ]);
                    }
                } else{
                    $ExistBusiness = UserFacebookPageBusiness::where('business_id', $userFacebookID)
                        ->where('page_id', $page['id'])
                        ->first();
                    if (!$ExistBusiness)
                    {
                        UserFacebookPageBusiness::create([
                            'page_id' => $page['id'],
                            'facebook_id' => $userFacebookID,
                            'business_id' => $userFacebookID,
                        ]);
                    }
                }

                $this->SubscribePagetoWebhook($page['id'],$page['access_token']);
                $this->getLeadAccessStatus($page['id'],$userFacebookID,$page['access_token']);
                $this->getLeadForms($page['id'],$page['access_token']);
            }

            if ($refresh)
            {
                return redirect()->route('facebook accounts')->with('success', 'Facebook Account Data Updated Successfully.');
            }
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            return redirect()->route('facebook accounts')->with('error', 'An error occurred while Adding Facebook Pages'. $e);
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            return redirect()->route('facebook accounts')->with('error', 'An error occurred while Adding Facebook Pages'. $e);
        }
    }

    public function getBusinessAccounts($user_facebook_id, $user_facebook_name ,$accessToken)
    {
        try {
            $response = $this->FB_Call('me/businesses?fields=name,owned_businesses&limit=9999999', $accessToken);
            $BusinessAccounts = isset($response['data']) ? $response['data'] : array();
            if ($BusinessAccounts)
            {
                UserFacebookBusiness::where('facebook_id',$user_facebook_id)
                        ->delete();

                // Create Personal
                $userFacebookBusiness = new UserFacebookBusiness();
                $userFacebookBusiness->facebook_id = $user_facebook_id;
                $userFacebookBusiness->business_id = $user_facebook_id;
                $userFacebookBusiness->business_name = $user_facebook_name;
                $userFacebookBusiness->save();

                foreach ($BusinessAccounts as $Business) {
                    $userFacebookBusiness = new UserFacebookBusiness();
                    $userFacebookBusiness->facebook_id = $user_facebook_id;
                    $userFacebookBusiness->business_id = $Business['id'];
                    $userFacebookBusiness->business_name =  $Business['name'];
                    $userFacebookBusiness->save();
                }
            }
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            return redirect()->route('facebook accounts')->with('error', 'An error occurred while Adding Facebook Business Accounts'. $e);
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            return redirect()->route('facebook accounts')->with('error', 'An error occurred while Adding Facebook Business Accounts'. $e);
        }
    }

    public function getAdAccounts($user_facebook_id, $user_facebook_name ,$accessToken)
    {
        try {
            $response = $this->FB_Call('me/adaccounts?fields=account_id,name,business&limit=9999999', $accessToken);
            $AdAccounts = isset($response['data']) ? $response['data'] : array();
            if ($AdAccounts)
            {
                UserFacebookAdaccount::where('facebook_id',$user_facebook_id)
                        ->delete();
                foreach ($AdAccounts as $adAccount) {
                    $userFacebookAdaccount = new UserFacebookAdaccount();
                    $userFacebookAdaccount->facebook_id = $user_facebook_id;
                    $userFacebookAdaccount->account_id = $adAccount['account_id'];
                    $userFacebookAdaccount->account_name = $adAccount['name'];
                    $userFacebookAdaccount->save();

                    if (isset($adAccount['business']) && !empty($adAccount['business'])) {
                             $HaveBusiness = UserFacebookBusiness::where('facebook_id',$user_facebook_id)
                                ->where('business_id',$adAccount['business']['id'])
                                ->first();
                            if (!$HaveBusiness)
                            {
                                // Create Business If I dont' have Access 
                                $userFacebookBusiness = new UserFacebookBusiness();
                                $userFacebookBusiness->facebook_id = $user_facebook_id;
                                $userFacebookBusiness->business_id = $adAccount['business']['id'];
                                $userFacebookBusiness->business_name = $adAccount['business']['name'];
                                $userFacebookBusiness->save();
                            }

                            $ExistAdAccount = UserFacebookAdaccountBusiness::where('adaccount_id', $adAccount['account_id'])
                            ->where('business_id',$adAccount['business']['id'])
                            ->first();

                            if (!$ExistAdAccount)
                            {
                                UserFacebookAdaccountBusiness::create([
                                    'facebook_id' => $user_facebook_id,
                                    'business_id' => $adAccount['business']['id'],
                                    'adaccount_id' => $adAccount['account_id'],
                                ]);

                            }
                    }
                    else{
                        $ExistAdAccount = UserFacebookAdaccountBusiness::where('adaccount_id', $adAccount['account_id'])
                            ->where('business_id',$user_facebook_id)
                            ->first();

                        if (!$ExistAdAccount)
                        {
                            UserFacebookAdaccountBusiness::create([
                                'facebook_id' => $user_facebook_id,
                                'business_id' => $user_facebook_id,
                                'adaccount_id' => $adAccount['account_id'],
                            ]);
                        }
                    }
                }
            }
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            return redirect()->route('facebook accounts')->with('error', 'An error occurred while Adding Facebook Ad Account'. $e);
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            return redirect()->route('facebook accounts')->with('error', 'An error occurred while Adding Facebook Ad Account'. $e);
        }
    }

    public function getPromoteAdAccounts($user_facebook_id, $user_facebook_name ,$accessToken)
    {
        try {
            $response = $this->FB_Call('me/adaccounts?fields=account_id,name,business,promote_pages{cover,emails,picture{url},id,name,url,username,access_token}&limit=9999999', $accessToken);
            $AdAccounts = isset($response['data']) ? $response['data'] : array();
            if ($AdAccounts)
            {
                foreach ($AdAccounts as $adAccount) {

                    $ExistAdAccount = UserFacebookAdaccount::where('account_id', $adAccount['account_id'])
                        ->first();
                    if (!$ExistAdAccount)
                    {
                        $userFacebookAdaccount = new UserFacebookAdaccount();
                        $userFacebookAdaccount->facebook_id = $user_facebook_id;
                        $userFacebookAdaccount->account_id = $adAccount['account_id'];
                        $userFacebookAdaccount->account_name = $adAccount['name'];
                        $userFacebookAdaccount->save();
                    }

                    if (isset($adAccount['business']) && !empty($adAccount['business'])) {
                             $HaveBusiness = UserFacebookBusiness::where('facebook_id',$user_facebook_id)
                                ->where('business_id',$adAccount['business']['id'])
                                ->first();
                            if (!$HaveBusiness)
                            {
                                // Create Business If I dont' have Access 
                                $userFacebookBusiness = new UserFacebookBusiness();
                                $userFacebookBusiness->facebook_id = $user_facebook_id;
                                $userFacebookBusiness->business_id = $adAccount['business']['id'];
                                $userFacebookBusiness->business_name = $adAccount['business']['name'];
                                $userFacebookBusiness->save();
                            }

                            $ExistAdAccountBusiness = UserFacebookAdaccountBusiness::where('adaccount_id', $adAccount['account_id'])
                                ->where('business_id',$adAccount['business']['id'])
                                ->first();

                            if (!$ExistAdAccountBusiness)
                            {
                                UserFacebookAdaccountBusiness::create([
                                    'facebook_id' => $user_facebook_id,
                                    'business_id' => $adAccount['business']['id'],
                                    'adaccount_id' => $adAccount['account_id'],
                                ]);

                            }
                    }
                    else{
                        $ExistAdAccountBusiness = UserFacebookAdaccountBusiness::where('adaccount_id', $adAccount['account_id'])
                            ->where('business_id',$user_facebook_id)
                            ->first();

                        if (!$ExistAdAccountBusiness)
                        {
                            UserFacebookAdaccountBusiness::create([
                                'facebook_id' => $user_facebook_id,
                                'business_id' => $user_facebook_id,
                                'adaccount_id' => $adAccount['account_id'],
                            ]);
                        }
                    }

                    if (isset($adAccount['promote_pages']['data']) && !empty($adAccount['promote_pages']['data'])) {
                        foreach ($adAccount['promote_pages']['data'] as $page) {
                            $existingPage = UserFacebookPage::where('facebook_id', $user_facebook_id)
                                ->where('page_id', $page['id'])
                                ->first();
                            if (!$existingPage) {
                                UserFacebookPage::create([
                                    'facebook_id' => $user_facebook_id,
                                    'page_id' => $page['id'],
                                    'name' => isset($page['name']) ? $page['name'] : null,
                                    'cover_url' => isset($page['cover']['source']) ? $page['cover']['source'] : null,
                                    'email' => isset($page['emails'][0]) ? $page['emails'][0] : null,
                                    'username' => isset($page['username']) ? $page['username'] : null,
                                    'page_access_token' => isset($page['access_token']) ? $page['access_token'] : null,
                                ]);
                                $this->SubscribePagetoWebhook($page['id'],$page['access_token']);
                                $this->getLeadAccessStatus($page['id'],$user_facebook_id,$page['access_token']);
                                $this->getLeadForms($page['id'],$page['access_token']);
                            }

                            if (isset($adAccount['business']) && !empty($adAccount['business'])) {
                                $HaveBusiness = UserFacebookBusiness::where('facebook_id',$user_facebook_id)
                                    ->where('business_id',$adAccount['business']['id'])
                                    ->first();
                                if (!$HaveBusiness)
                                {
                                    // Create Business If I dont' have Access 
                                    $userFacebookBusiness = new UserFacebookBusiness();
                                    $userFacebookBusiness->facebook_id = $user_facebook_id;
                                    $userFacebookBusiness->business_id = $adAccount['business']['id'];
                                    $userFacebookBusiness->business_name = $adAccount['business']['name'];
                                    $userFacebookBusiness->save();
                                }
                                $ExistBusiness = UserFacebookPageBusiness::where('business_id', $adAccount['business']['id'])
                                    ->where('page_id', $page['id'])
                                    ->first();
                                if (!$ExistBusiness)
                                {
                                    UserFacebookPageBusiness::create([
                                        'page_id' => $page['id'],
                                        'facebook_id' => $user_facebook_id,
                                        'business_id' => $adAccount['business']['id'],
                                    ]);
                                }
                            } else{
                                $ExistBusiness = UserFacebookPageBusiness::where('business_id', $user_facebook_id)
                                    ->where('page_id', $page['id'])
                                    ->first();
                                if (!$ExistBusiness)
                                {
                                    UserFacebookPageBusiness::create([
                                        'page_id' => $page['id'],
                                        'facebook_id' => $user_facebook_id,
                                        'business_id' => $user_facebook_id,
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            return redirect()->route('facebook accounts')->with('error', 'An error occurred while Adding Facebook Ad Account'. $e);
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            return redirect()->route('facebook accounts')->with('error', 'An error occurred while Adding Facebook Ad Account'. $e);
        }
    }

    public function getLeadForms($PageID, $pageAccessToken, $refresh = false)
    {
        try {
            $response = $this->FB_Call($PageID.'/leadgen_forms?limit=99999', $pageAccessToken);
            $Forms = isset($response['data']) ? $response['data'] : array();

            if ($refresh == false)
            {
                UserFacebookPageForm::where('page_id', $PageID)
                    ->delete();
            }
            foreach($Forms as $form)
            {
                if($refresh)
                {
                    $UserFBForm =  UserFacebookPageForm::where('page_id', $PageID)
                        ->where('form_id', $form['id'])
                        ->first();
                    $addData = ($UserFBForm) ? false : true;
                }
                else {
                    $addData = true;
                }
                if ($addData)
                {
                    UserFacebookPageForm::create([
                        'name' => $form['name'],
                        'form_id' => $form['id'],
                        'page_id' => $PageID,
                    ]);
                }
            }
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
        }
    }

    public function getPagesDB(Request $request)
    {
        $facebook_id = $request->input('facebook_id');
        $pages = UserFacebookPage::where('facebook_id', $facebook_id)->get();
        return response()->json($pages);
    }

    public function getFormsDB(Request $request)
    {
        $page_id = $request->input('page_id');
        $forms = UserFacebookPageForm::where('page_id', $page_id)->get();
        return response()->json($forms);
    }

    public function getAccountsDB()
    {
        $FBAccounts = UserFacebookAccount::all();
        return view('livewire.facebook-list', compact('FBAccounts'));
    }

    public function getPagesforShow($id)
    {
        $userFacebookPages = UserFacebookPage::where('facebook_id', $id)
            ->get();
       // return view('facebook.view', ['pages' => $userFacebookPages]);
    }

    public function getAdIntrests()
    {
        return view('facebook.adsearch');
    }

    public function getAdIntrestsAPI(Request $request)
    {
        $q = $request->input('q');
        $UserFBAccount = UserFacebookAccount::first();
        if (!$UserFBAccount) {
            return response()->json(['error' => 'User Facebook Account not found, Please Add Facebook Account for Access Token'], 404);
        }
        try {
            $response = $this->FB_Call('search?type=adinterest&q={$q}&limit=150000&1ocale=en_US', $UserFBAccount->access_token);
            $AdIntrests = isset($response['data']) ? $response['data'] : array();
            return response()->json($AdIntrests);
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            return response()->json(['error' => 'An error occurred while adding Facebook Ad Intrests: ' . $e], 500);
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            return response()->json(['error' => 'An error occurred while adding Facebook Ad Intrests: ' . $e], 500);
        }
    }

    function FB_Call($URL, $accessToken, $method = 'GET', $data = [], $jsonEncode = true) {
        $ch = curl_init();
        $urlWithAccessToken = "https://graph.facebook.com/v22.0/". $URL . "&access_token=" . urlencode($accessToken);
        curl_setopt($ch, CURLOPT_URL, $urlWithAccessToken);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            if (!empty($data)) {
                if ($jsonEncode)
                {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                else{
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                }
            }
        }
        elseif (strtoupper($method) === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'cURL Error: ' . curl_error($ch);
        }
        curl_close($ch);

        $dataArray = json_decode($response, true);

        return $dataArray;
    }

    public function setBotStatus($pageId, $fbId, $status, $accessToken)
    {
        try {
            if($status) {
                $subscribed_fields = ["messages", "messaging_optins", "messaging_postbacks", "messaging_referrals", "feed"];
            } else {
                $subscribed_fields = [];
            }
            $response = $this->FB_Call(
                $pageId.'/subscribed_apps?1',
                $accessToken,
                $status ? 'POST' : 'DELETE',
                ['subscribed_fields' => $subscribed_fields]
            );

            if (isset($response['error'])) {
                return redirect()->route('facebook accounts')->with('error', 'Failed to update bot status.');
            }

            $page = UserFacebookPage::where('page_id', $pageId)
                ->where('facebook_id',$fbId)->first();
            if ($page) {
                $page->bot_enabled = $status;
                $page->save();
            }

            return redirect()->route('facebook accounts')->with('success', 'Bot status updated successfully.');
            
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            return redirect()->route('facebook accounts')->with('error', 'An error occurred: ' . $e->getMessage());
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            return redirect()->route('facebook accounts')->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    function handleWebhookCallback(Request $request)
    {
        Log::info('Incoming Webhook Request:', [
            'request_data' => $request->all(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent')
        ]);

        // Save raw JSON data first
        $webhookCall = new FacebookWebhookCall();
        $webhookCall->raw_json = $request->all();
        $webhookCall->headers = $request->headers->all();
        $webhookCall->ip = $request->ip();
        $webhookCall->user_agent = $request->header('User-Agent');
        $webhookCall->save();

        // Verify subscription
        $mode = $request->input('hub_mode');
        $challenge = $request->input('hub_challenge');
        $token = $request->input('hub_verify_token');

        if ($mode === 'subscribe' && $token === '03116534263') {
            return response($challenge, 200);
        }

        // Process webhook data
        $entry = $request->input('entry')[0] ?? null;
        if (empty($entry)) {
            Log::warning('Empty entry in the incoming request');
            return response('Invalid request format', 400);
        }

        $changes = $entry['changes'][0] ?? null;
        if (empty($changes)) {
            Log::warning('Empty changes in the incoming request');
            return response('Invalid request format', 400);
        }

        if ($changes['field'] === 'feed') {
            $value = $changes['value'] ?? [];
            $from = $value['from'] ?? [];
            $pageId = $entry['id'] ?? null;
            $postId = $value['parent_id'] ?? null;
            $commentId = $value['comment_id'] ?? null;
            $message = $value['message'] ?? null;
            $cusFbId = $from['id'] ?? null;
            $cusFbName = $from['name'] ?? null;
            $itemType = $value['item'] ?? null;
            $field = $changes['field'] ?? null;

            // Update the saved raw data with extracted fields
            $webhookCall->fill([
                'page_id' => $pageId,
                'post_id' => $postId,
                'comment_id' => $commentId,
                'message' => $message,
                'cus_fb_id' => $cusFbId,
                'cus_fb_name' => $cusFbName,
                'item_type' => $itemType,
                'field' => $field,
                'from' => $from,
                'post' => $value['post'] ?? [],
                'value' => $value,
            ]);

            $webhookCall->save();
            Log::info('Webhook data saved successfully.');
            if ($itemType == 'comment')
            {
                $FBPages = UserFacebookPage::where('page_id', $pageId)
                    ->where('bot_enabled' , 1)
                    ->get();
                Log::info('Facebook Pages with Bot Enabled:', $FBPages->toArray());
                foreach ($FBPages as $FBPage) {
                    if ($FBPage) {
                        $C_Response    = $this->createCustomer($cusFbId, $cusFbName);
                        $customer_id   = $C_Response['customer_id'];
                        $isNewCustomer = $C_Response['is_new'];
                        $parts = explode('=', $message);
                        if (count($parts) === 2) {
                            $sku = trim($parts[0]);
                            $quantity = (int) trim($parts[1]);
                        } else {
                            $sku = trim($parts[0]);
                            $quantity = 1;
                        }
                        if ($customer_id) {

                            if ($isNewCustomer)
                            {
                                $t_customer = str_replace('[Customer Name]', $cusFbName, $FBPage->t_customer);
                                $this->sendAutoReplyMessage($commentId , $t_customer, $FBPage->page_access_token);
                            }

                            $product = Product::where('sku', $sku)
                                ->where('status','active')
                                ->first();                        
                            if ($product) {
                                if ($product->stock_quantity > 0)
                                {
                                    $t_comment = str_replace('[Customer Name]', $cusFbName, $FBPage->t_comment);
                                    $this->autoReplyComment($commentId , $t_comment, $FBPage->page_access_token);
                                    
                                    $orderDetail = OrderDetail::join('orders', 'orders.id', '=', 'order_details.order_id')
                                        ->where('orders.user_customer_id', $customer_id)
                                        ->where('order_details.product_id', $product->id)
                                        ->where('orders.status', 'pending')
                                        ->whereNull('orders.invoice_id')
                                        ->select('order_details.*')
                                        ->first();
                                    
                                    $allocatedQuantity = min($quantity, $product->stock_quantity);

                                    if ($orderDetail) {
                                        $order = Order::where('id', $orderDetail->order_id)->first();
                                        $orderDetail->quantity += $allocatedQuantity;
                                        $orderDetail->total_price = $orderDetail->unit_price * $orderDetail->quantity;
                                        $orderDetail->save();
                                    } else {
                                        $order = Order::create([
                                            'user_customer_id' => $customer_id,
                                            'status' => 'pending',
                                            'subtotal' => 0,
                                            'total_amount' => 0,
                                            'source' => 'auto',
                                            'order_number' => Str::uuid()->toString(),
                                        ]);
                                        
                                        OrderDetail::create([
                                            'order_id' => $order->id,
                                            'product_id' => $product->id,
                                            'product_name' => $product->name,
                                            'unit_price' => $product->price,
                                            'quantity' => $allocatedQuantity,
                                            'total_price' => $product->price * $quantity,
                                        ]);
                                    }
                                    $order->subtotal += $product->price * $quantity;
                                    $order->total_amount = $order->subtotal;
                                    $order->save();
                                    $product->decrement('stock_quantity', $allocatedQuantity);
                                    if ($webhookCall)
                                    {
                                        $webhookCall->update(['order_id' => $order->id]);
                                    }
                                }
                                else{
                                    $t_out_stock_comment = str_replace('[Customer Name]', $cusFbName, $FBPage->t_shipped);
                                    $this->autoReplyComment($commentId , $t_out_stock_comment, $FBPage->page_access_token);
                                }
                            }
                        }
                    } else {
                        echo "No associated Facebook account for page ID: " . $FBPage->page_id . "\n";
                    }
                }
            }
        }

        return response('Received', 200);
    }

    public function createCustomer($userFacebookID, $Name)
    {
        $customer = UserCustomers::where('facebook_id', $userFacebookID)
            ->first();
        if (!$customer) {
            $customer = UserCustomers::create([
                'facebook_id' => $userFacebookID,
                'name'        => $Name,
                'source'      => 'auto'
            ]);
            return ['customer_id' => $customer->id, 'is_new' => true];
        }
        return ['customer_id' => $customer->id, 'is_new' => false];
    }

    public function autoLikeComment($commentId, $page_access_token)
    {
        try {
            $response = $this->FB_Call(
                $commentId . '/likes?1', $page_access_token, 'POST', []
            );
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            \Log::error('Facebook Response Exception occurred.', [
                'commentId' => $commentId,
                'error' => $e->getMessage(),
            ]);
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            \Log::error('Facebook SDK Exception occurred.', [
                'commentId' => $commentId,
                'error' => $e->getMessage(),
            ]);
        }
    }


    public function autoReplyComment($commentId , $message, $page_access_token)
	{
        try {
            $response = $this->FB_Call(
                $commentId . '/comments?1', $page_access_token, 'POST', ['message' => $message]
            );
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            \Log::error('Facebook Response Exception occurred.', [
                'commentId' => $commentId,
                'error' => $e->getMessage(),
            ]);
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            \Log::error('Facebook SDK Exception occurred.', [
                'commentId' => $commentId,
                'error' => $e->getMessage(),
            ]);
        }
	}

    public function sendAutoReplyMessage($commentId , $message, $page_access_token)
	{
        try {
            $data = [
                'messaging_type' => 'RESPONSE',
                'recipient' => [
                    'comment_id' => $commentId
                ],
                'message' => [
                    'text' => $message
                ]
            ];

            $response = $this->FB_Call(
                'me/messages?1', $page_access_token, 'POST', $data
            );
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            \Log::error('Facebook Response Exception occurred.', [
                'commentId' => $commentId,
                'error' => $e->getMessage(),
            ]);
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            \Log::error('Facebook SDK Exception occurred.', [
                'commentId' => $commentId,
                'error' => $e->getMessage(),
            ]);
        }
	}

    public function sendAutoReplyMessagePDF($FBID, $message, $page_access_token, $pdfFilePath)
    {
        try {
            $file = new \CURLFile($pdfFilePath, 'application/pdf');
            $uploadData = [
                'message' => [
                    'attachment' => [
                        'type' => 'file',
                        'payload' => [
                            'is_reusable' => true,
                        ]
                    ]
                ],
                'filedata' => $file
            ];

            $postData = [
                'message' => json_encode($uploadData['message']), 
                'filedata' => $file
            ];

            $uploadResponse = $this->FB_Call(
                'me/message_attachments?1', 
                $page_access_token, 
                'POST', 
                $postData,
                false
            );
            
  dd($uploadResponse);
            if (isset($uploadResponse['attachment_id'])) {
                $data = [
                    'messaging_type' => 'MESSAGE_TAG',
                    'tag' => 'POST_PURCHASE_UPDATE',
                    'recipient' => [
                        'id' => $FBID
                    ],
                    'message' => [
                        'attachment' => [
                            'type' => 'file',
                            'payload' => [
                                'attachment_id' => $uploadResponse['attachment_id']
                            ]
                        ]
                    ]
                ];
                
                if (!empty($message)) {
                    $textData = [
                        'messaging_type' => 'MESSAGE_TAG',
                        'tag' => 'POST_PURCHASE_UPDATE',
                        'recipient' => [
                            'id' => $FBID
                        ],
                        'message' => [
                            'text' => $message
                        ]
                    ];
                    $response = $this->FB_Call('me/messages?1', $page_access_token, 'POST', $textData);
                    if (isset($response['error'])) {
                        return response()->json(['error' => $response['error']], 400);
                    }
                }
            
                $response = $this->FB_Call(
                    'me/messages?1',
                    $page_access_token,
                    'POST',
                    $data
                );
              
                if (isset($response['error'])) {
                    return response()->json(['error' => $response['error']], 400);
                }
                return response()->json(['success' => 'Invoice Sent to Customer Successfully !'], 200);
            } else {
                \Log::error('Failed to upload the PDF to Facebook.', [
                   
                    'error' => 'No attachment ID received.'
                ]);
            }
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            \Log::error('Facebook Response Exception occurred.', [
                
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Facebook SDK encountered an error: ' . $e->getMessage()], 500);
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            \Log::error('Facebook SDK Exception occurred.', [
               
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            \Log::error('General Exception occurred.', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }


    function saveLeadDataToDB($leadData, $leadgenId)
    {
        Log::info('Attempting to save lead data:', ['lead_data' => $leadData]);

        $FacebookWebhookCall = FacebookWebhookCall::where('leadgen_id', $leadgenId)->first();
        if ($FacebookWebhookCall) {
            $FacebookWebhookCall->ad_id      = $leadData['ad_id'] ?? null;
            $FacebookWebhookCall->form_id    = $leadData['form_id'] ?? null;
            $FacebookWebhookCall->page_id    = $leadData['page_id'] ?? null;
            $FacebookWebhookCall->adgroup_id = $leadData['adgroup_id'] ?? null;
            $FacebookWebhookCall->created_at = $leadData['created_time'] ?? null;
            $FacebookWebhookCall->status = 1;
            $FacebookWebhookCall->update();
            Log::info('Lead data saved:', ['result' => $FacebookWebhookCall]);
            return true;
        }
        else{
            return false;
        }
    }



    public function getZapbyWebhookId($leadgenId)
    {
        $FacebookWebhookCall = FacebookWebhookCall::where('leadgen_id', $leadgenId)->first();
        if ($FacebookWebhookCall)
        {
            $formId = $FacebookWebhookCall->form_id;
            $pageId = $FacebookWebhookCall->page_id;
            $UserZap = UserZap::where('facebook_page_id', $pageId)
                ->where('facebook_form_id', $formId)
                ->where('status', 1)
                ->get();
            if ($UserZap)
            {
                foreach($UserZap as $Zap)
                {
                    $UserLead = UserLeads::create([
                        'facebook_page_id' => $pageId,
                        'facebook_form_id' => $formId,
                        'client_id' => $Zap->client_id,
                        'zap_id' => $Zap->id,
                        'source' => 'Webhook'
                    ]);
                    $FBPage = UserFacebookPage::where('page_id', $pageId)
                        ->where('facebook_id', $Zap->facebook_id)
                        ->first();
                    $TextforModeration = "";
                    $email = "";
                    $phone = "";
                    if ($FBPage)
                    {
                        $LeadData = $this->getLeadByForms($FacebookWebhookCall->leadgen_id,$FBPage->page_access_token);
                        if (isset($LeadData['field_data']))
                        {
                            foreach($LeadData['field_data'] as $leaddata)
                            {
                                if (isset($leaddata['values'])) {
                                    foreach ($leaddata['values'] as $leadValue) {
                                        UserLeadDetails::create([
                                            'lead_id' => $UserLead->id,
                                            'lead_key' => $leaddata['name'],
                                            'lead_value' => $leadValue,
                                        ]);

                                        if ($leaddata['name'] == 'email')
                                        {
                                            $email = $leadValue;
                                        }
                                        if ($leaddata['name'] == 'phone_number')
                                        {
                                            $phone = $leadValue;
                                        }

                                        $TextforModeration .= $leadValue." ";
                                    }
                                }
                            }
                        }                        
                    }
                    $ContentModeratorationAPIResponse    = $this->ContentModeratorationAPI($TextforModeration);
                    $ContentModeratorationCustomResponse = $this->ContentModeratorationCustom($TextforModeration);
                    $isJunk = $this->checkJunk($email,$phone);
                    if ($ContentModeratorationAPIResponse != 1 && $ContentModeratorationCustomResponse != 1 && $isJunk == false)
                    {
                        $this->sendLeadToDiscord($Zap->id, $UserLead->id);
                        $this->sendLeadToGoogleSheet($Zap->id, $UserLead->id);
                        if ($ContentModeratorationAPIResponse == 2)
                            UserLeads::where('id', $UserLead->id)->update(['status' => 3]);
                    }
                    else{
                        UserLeads::where('id', $UserLead->id)->update(['status' => 2]);
                    }
                }
                $FacebookWebhookCall->status = 2;
                $FacebookWebhookCall->update();
            }
        }
    }

    public function getLeadsFromFacebook()
    {
        $UserZap = UserZap::where('status', 1)
            ->get();
        if ($UserZap)
        {
            foreach($UserZap as $Zap)
            {
                $FBPage = UserFacebookPage::where('page_id', $Zap->facebook_page_id)
                    ->where('facebook_id', $Zap->facebook_id)
                    ->first();
                $TextforModeration = "";
                if ($FBPage)
                {
                    $LeadData = $this->getLeadsByForms($Zap->facebook_form_id,$FBPage->page_access_token);
                    foreach($LeadData as $leaddata)
                    {
                        if (isset($leaddata['field_data']))
                        {
                            $leadKeys = collect($leaddata['field_data'])->pluck('name');
                            $leadValues = collect($leaddata['field_data'])
                                ->flatMap(function ($field_data) {
                                    return $field_data['values'];
                                });
                            
                            $userLeadDetailsQuery = UserLeadDetails::query();

                            $userLeadDetailsQuery->where(function ($query) use ($leadKeys, $leadValues) {
                                foreach ($leadKeys as $index => $leadKey) {
                                    $quotedLeadKey = "'" . addslashes($leadKey) . "'";
                                    $quotedLeadValue = "'" . addslashes($leadValues[$index]) . "'";
                                    if ($leadKey == 'email' || $leadKey == 'phone_number') {
                                        $query->orWhere(function ($subquery) use ($leadKey, $quotedLeadValue) {
                                            $subquery->where('lead_value', $quotedLeadValue);
                                        });
                                    }
                                }
                            });
                            $userLeadDetailsQuery->join('user_leads', 'user_lead_details.lead_id', '=', 'user_leads.id');
                            $userLeadDetailsQuery->where('user_leads.zap_id', $Zap->id);
                            $sqlQuery = $userLeadDetailsQuery->toSql();
                            $bindings = $userLeadDetailsQuery->getBindings();
                            $finalQuery =  vsprintf(str_replace('?', '%s', $sqlQuery), $bindings);
                            $results = DB::select($finalQuery);
                            if (empty($results))
                            {
                                $createdTime = Carbon::parse($leaddata['created_time']);
                                $UserLead = UserLeads::create([
                                    'facebook_page_id' => $Zap->facebook_page_id,
                                    'facebook_form_id' => $Zap->facebook_form_id,
                                    'client_id' => $Zap->client_id,
                                    'zap_id' => $Zap->id,
                                    'created_at' => $createdTime,
                                    'status' => 4,
                                    'source' => 'API'
                                ]);
                                foreach ($leaddata['field_data'] as $field_data) {
                                    foreach ($field_data['values'] as $leadValue) {
                                        UserLeadDetails::create([
                                            'lead_id' => $UserLead->id,
                                            'lead_key' => $field_data['name'],
                                            'lead_value' => $leadValue,
                                            'created_at' => $createdTime
                                        ]);
                                        $TextforModeration .= $leadValue." ";
                                    }
                                }
                            }

                        }
                    }
                }
            }
        }
    }

    public function sendLeadManuallyDelay30(){
        
        $UserZap = UserZap::where('status', 1)
            ->get();
        foreach($UserZap as $Zap)
        {
            $UserLead = UserLeads::whereIn('status', [0,4])
                ->where('zap_id', $Zap->id)
                ->whereDate('created_at', Carbon::today())
                ->first();
            if ($UserLead)
            {
                $TextforModeration = "";
                $email = "";
                $phone = "";
                $UserLeadDetails = UserLeadDetails::where('lead_id', $UserLead->id)->get();
                foreach($UserLeadDetails as $LeadDetails)
                {
                    if ($LeadDetails->lead_key == 'email')
                    {
                        $email = $LeadDetails->lead_value;
                    }
                    if ($LeadDetails->lead_key == 'phone_number')
                    {
                        $phone = $LeadDetails->lead_value;
                    }
                    $TextforModeration .= $LeadDetails->lead_value." ";
                }               
                $ContentModeratorationAPIResponse    = $this->ContentModeratorationAPI($TextforModeration);
                $ContentModeratorationCustomResponse = $this->ContentModeratorationCustom($TextforModeration);
                $isJunk = $this->checkJunk($email,$phone);
                if ($ContentModeratorationAPIResponse != 1 && $ContentModeratorationCustomResponse != 1 && $isJunk == false)
                {
                    $this->sendLeadToDiscord($Zap->id, $UserLead->id);
                    $this->sendLeadToGoogleSheet($Zap->id, $UserLead->id);
                    if ($ContentModeratorationAPIResponse == 2)
                        UserLeads::where('id', $UserLead->id)->update(['status' => 3]);
                }
                else{
                    UserLeads::where('id', $UserLead->id)->update(['status' => 2]);
                }
            }
        }
    }

    public function sendLeadManuallyDelay(){
        $scheduledLeads = LeadSchedule::where('scheduled_at', '<=', now())
            ->where('is_sent', false)
            ->get();
        foreach($scheduledLeads as $Lead)
        {
            $userLead = UserLeads::find($Lead->lead_id);
            if ($userLead)
            {
                $TextforModeration = "";
                $email = "";
                $phone = "";
                $UserLeadDetails = UserLeadDetails::where('lead_id', $userLead->id)->get();
                foreach($UserLeadDetails as $LeadDetails)
                {
                    if ($LeadDetails->lead_key == 'email')
                    {
                        $email = $LeadDetails->lead_value;
                    }
                    if ($LeadDetails->lead_key == 'phone_number')
                    {
                        $phone = $LeadDetails->lead_value;
                    }
                    $TextforModeration .= $LeadDetails->lead_value." ";
                }
                $isJunk = $this->checkJunk($email,$phone);
                $ContentModeratorationAPIResponse    = $this->ContentModeratorationAPI($TextforModeration);
                $ContentModeratorationCustomResponse = $this->ContentModeratorationCustom($TextforModeration);
                if ($ContentModeratorationAPIResponse != 1 && $ContentModeratorationCustomResponse != 1 && $isJunk == false)
                {
                    $this->sendLeadToDiscord($userLead->zap_id, $userLead->id);
                    $this->sendLeadToGoogleSheet($userLead->zap_id, $userLead->id);
                    if ($ContentModeratorationAPIResponse == 2)
                        UserLeads::where('id', $userLead->id)->update(['status' => 3]);
                }
                else{
                    UserLeads::where('id', $userLead->id)->update(['status' => 2]);
                }
            }
        }
    }

    public function checkUserAccessToken(){
        return false;
        $userFacebookAccounts = UserFacebookAccount::where('error_sent',0)->get();
        $erro_message = "*[Username] Access Token Error* \n\nHi, Please connect the [Username] Account again in Meowtomations.\nAll Leads associated to that acccount are not working\n\nPlease goto this URL \nhttps://meowtomations.jomejourney-portal.com/facebook-accounts \n\nClick Add Facebook Account Button & give all access if promoted\n\n*Error From Facebook API*\n[Facebook Error]";
        $SendMessageToNumbers = [
            'Musadiq' => '+923116534263',
        ];

        foreach($userFacebookAccounts as $user)
        {
            try {
                $FB_Response = $this->FB_Call('me?fields=id,name', $user->access_token);
                if (isset($FB_Response['error']))
                {
                    $erro_message = str_replace('[Username]', $user->name, $erro_message);
                    $erro_message = str_replace('[Facebook Error]', $FB_Response['error']['message'], $erro_message);

                    foreach($SendMessageToNumbers as $number)
                    {
                        $post_array = array(
                            "to_number" => $number,
                            "from_number" => '+6589469107',
                            "text" => $erro_message
                        );

                        $curl = curl_init();
                        curl_setopt_array($curl, array(
                            CURLOPT_URL => "https://api.p.2chat.io/open/whatsapp/send-message",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => '',
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => 'POST',
                            CURLOPT_POSTFIELDS => json_encode($post_array),
                            CURLOPT_HTTPHEADER => array(
                                'Content-Type: application/json',
                                'X-User-API-Key: UAK32c243e8-e2ca-417a-ba7a-b3e1ee7b3d4c'
                            ),
                        ));
                        $response =  (array) json_decode(curl_exec($curl));
                        if (isset($response['success']))
                        {
                            $user->update([
                                'error_sent' => 1,
                            ]);
                        }
                    }

                }
            } catch (Facebook\Exceptions\FacebookResponseException $e) {
            } catch (Facebook\Exceptions\FacebookSDKException $e) {
            }
        }
    }

    public function sendLeadToGoogleSheet($ZapID, $LeadID)
    {
        $sheetId = null;
        $range = null;
        $header = [];
        $data = [];
        $UserZap = UserZap::where('id', $ZapID)->first();
        if ($UserZap)
        {
            $ZapClient = UserClients::where('client_id', $UserZap->client_id)->first();
            $sheetId = ($ZapClient) ? $ZapClient->sheet_id : null; 
            $range = $UserZap->name; 
        }
        $UserLeadDetails = UserLeadDetails::where('lead_id', $LeadID)->get();
        foreach($UserLeadDetails as $LeadDetails)
        {
            $header[] = $LeadDetails->lead_key;
            $data[] = $LeadDetails->lead_value;
        }
        Log::info('Google Sheets Data:', [
            'sheetId' => $sheetId,
            'range' => $range,
            'header' => $header,
            'data' => $data,
        ]);
        if ($sheetId && $range)
        {
            Log::info('Into If Condition SheetId && $range:');
            $googleInstance = new Google();
            Log::info('Created new googleInstance');
            $googleInstance->appendDataToSheet($sheetId,$range,$header,$data);
            Log::info('After function');
        }
    }

    public function sendLeadToDiscord($ZapID, $LeadID,$scheduled = false)
    {
        $UserZap = UserZap::where('id', $ZapID)->first();
        $UserZapLinks = UserZapLinks::where('zap_id', $UserZap->id)->get();
        $UserLeadDetails = UserLeadDetails::where('lead_id', $LeadID)->get();
        foreach($UserZapLinks as $ZapLink)
        {
            $Message = $UserZap->discord_message;
            $Message = str_replace('[[AUTOMATION_NAME]]',$UserZap->name,$Message);
            foreach($UserLeadDetails as $LeadDetails)
            {
                $Message = str_replace('[['.$LeadDetails->lead_key.']]',$LeadDetails->lead_value,$Message);
            }
            $Message = str_replace("\r\n", "\n", $Message);
            $Message = str_replace("\r", "\n", $Message);
            $this->DiscordPush($Message,$ZapLink->discord_url,$ZapLink->discord_bot_name,$LeadID, $scheduled);
        }
    }

    public function DiscordPush($Message,$WebhookLink,$BotName,$LeadID, $scheduled = false)
    {
        $post_array = array(
            "embeds" =>null,
            "content" => $Message,
            "attachments" => []
        );

        if($BotName != "")
        {
            $post_array['username'] = $BotName;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $WebhookLink,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($post_array),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
        ),
        ));
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode == 204) {
            UserLeads::where('id', $LeadID)->update(['status' => 1]);
            if ($scheduled)
            {
                LeadSchedule::where('lead_id', $LeadID)->update(['is_sent' => true]);
            }
        }
        curl_close($curl);
    }

    public function checkJunk($email, $phone)
    {
        $userLeadDetailsQuery = UserLeadDetails::query();
        $userLeadDetailsQuery->where(function($query) use ($email, $phone) {
            if ($email) {
                $query->where('lead_value', 'LIKE', "'%{$email}%'");
            }

            if ($phone) {
                $query->orWhere('lead_value', 'LIKE', "'%{$phone}%'");
            }
        });

        $userLeadDetailsQuery->join('user_leads', 'user_lead_details.lead_id', '=', 'user_leads.id');
        $userLeadDetailsQuery->where('user_leads.status', 2);

        $sqlQuery = $userLeadDetailsQuery->toSql();
        $bindings = $userLeadDetailsQuery->getBindings();
        $finalQuery = vsprintf(str_replace('?', '%s', $sqlQuery), $bindings);
        $results = DB::select($finalQuery);
        return (empty($results) ? false: true);
    }

    public function getLeadsByForms($FormID, $pageAccessToken)
    {
        try {
            $response = $this->FB_Call($FormID.'/leads?1', $pageAccessToken);
            $Leads = isset($response['data']) ? $response['data'] : array();
            return $Leads;
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
        }
    }

    public function getLeadAccessStatus($PageId, $FacebookId, $pageAccessToken)
    {
        try {
            $response = $this->FB_Call($PageId.'?fields=has_lead_access.user_id('.$FacebookId.')', $pageAccessToken);
            $has_lead_access = isset($response['has_lead_access']) ? $response['has_lead_access'] : null;
            
            if($has_lead_access) {
                $existingRecord = UserFacebookPageAccess::where('page_id', $PageId)
                    ->where('facebook_id', $FacebookId)
                    ->first();
                if($existingRecord) {
                    $existingRecord->update([
                        'failure_reason' => isset($has_lead_access['failure_reason']) ? $has_lead_access['failure_reason'] : null,
                        'failure_resolution' => isset($has_lead_access['failure_resolution']) ? $has_lead_access['failure_resolution'] : null,
                        'can_access_lead' => isset($has_lead_access['can_access_lead']) ? $has_lead_access['can_access_lead'] : false,
                        'enabled_lead_access_manager' => isset($has_lead_access['enabled_lead_access_manager']) ? $has_lead_access['enabled_lead_access_manager'] : false,
                        'is_page_admin' => isset($has_lead_access['is_page_admin']) ? $has_lead_access['is_page_admin'] : false,
                        'user_has_leads_permission' => isset($has_lead_access['user_has_leads_permission']) ? $has_lead_access['user_has_leads_permission'] : false,
                        'app_has_leads_permission' => isset($has_lead_access) ? true : false,
                    ]);
                    return true;
                } else {
                    $data = [
                        'page_id' => $PageId,
                        'facebook_id' => $FacebookId,
                        'failure_reason' => isset($has_lead_access['failure_reason']) ? $has_lead_access['failure_reason'] : null,
                        'failure_resolution' => isset($has_lead_access['failure_resolution']) ? $has_lead_access['failure_resolution'] : null,
                        'can_access_lead' => isset($has_lead_access['can_access_lead']) ? $has_lead_access['can_access_lead'] : false,
                        'enabled_lead_access_manager' => isset($has_lead_access['enabled_lead_access_manager']) ? $has_lead_access['enabled_lead_access_manager'] : false,
                        'is_page_admin' => isset($has_lead_access['is_page_admin']) ? $has_lead_access['is_page_admin'] : false,
                        'user_has_leads_permission' => isset($has_lead_access['user_has_leads_permission']) ? $has_lead_access['user_has_leads_permission'] : false,
                        'app_has_leads_permission' => isset($has_lead_access) ? true : false,
                    ];
                    UserFacebookPageAccess::create($data);
                    return true;
                }
            }
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
        }
    }

    public function getLeadByForms($LeadGenID, $pageAccessToken)
    {
        try {
            $response = $this->FB_Call($LeadGenID.'/?1', $pageAccessToken);
            return $response;
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
        }
    }

    public function SubscribePagetoWebhook($PageID, $pageAccessToken)
    {
        try {
            $response = $this->FB_Call($PageID.'/subscribed_apps?subscribed_fields=leadgen', $pageAccessToken,'POST');
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
        }
    }

    public function ContentModeratorationAPI($Text)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'text/plain',
            'Ocp-Apim-Subscription-Key' => '453fe3c404554800bc2c22d7ef681542',
        ])->post('https://jomejourney.cognitiveservices.azure.com/contentmoderator/moderate/v1.0/ProcessText/Screen', [
            'text' => $Text,
        ]);
        if ($response->successful()) {
            $statusCode = $response->status();
            $responseBody = $response->json();
            return (isset($responseBody['Terms'])) ? ((count($responseBody['Terms']) > 0) ? 1 : 0) : 0;
            // 0 for Clean | 1 for Junk Lead
        } else {
            return 2; // Error Case API not Working
        }
    }

    public function ContentModeratorationCustom($Text)
    {
        $blacklistWords = [
            'Chee bye',
            'Chao chee bye',
            'Fucking',
            'Agent',
            'Mama',
            'Mike',
            'Fuck',
            'Stupid',
            'demo',
        ];

        foreach ($blacklistWords as $substring) {
            if (strpos(strtolower($Text), strtolower($substring)) !== false) {
                return 1; // Junk Lead
            }
        }
        return 0; // Clean Lead
    }

    public function confirmDeleteAccount($facebookId)
    {
        $userFacebookPages = UserFacebookPage::where('facebook_id', $facebookId)->get();

        foreach ($userFacebookPages as $page) {
            $userFacebookForms = UserFacebookPageForm::where('page_id', $page->page_id)->get();

            foreach ($userFacebookForms as $form) {
                $form->delete();
            }
            $page->delete();
        }

        $userFacebookAccounts = UserFacebookAccount::where('facebook_id', $facebookId)->first();
        if ($userFacebookAccounts) {
            $userFacebookAccounts->delete();
        }
        $this->emit('AccountDeleted');
    }



    public function AccountDeleted()
    {
        return redirect()->route('facebook accounts')->with('success', 'Facebook Account deleted successfully from the List');
    }

    public function render()
    {
        $this->userFacebookAccountsList = UserFacebookAccount::all();
        if (count($this->userFacebookAccountsList) > 0 )
        {
            $this->userFacebookAccountsListId  = $this->userFacebookAccountsList[0]->facebook_id;
            $this->userFacebookAdAccountsList  = UserFacebookAdaccount::where("facebook_id",$this->userFacebookAccountsListId)->get();
            $this->userFacebookBusinessList    = UserFacebookBusiness::where("facebook_id",$this->userFacebookAccountsListId)->get();
            $this->userFacebookPagesList       = UserFacebookPage::where("facebook_id",$this->userFacebookAccountsListId)->get();
            $this->userFacebookPageFormsList   = UserFacebookPageForm::whereIn("page_id", $this->userFacebookPagesList->pluck('page_id'))->get();
        }
         

        return view('livewire.facebook-list');
    }
}
