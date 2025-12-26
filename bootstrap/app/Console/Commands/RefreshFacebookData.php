<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Livewire\Facebook;
use App\Models\UserFacebookAccount;
use App\Models\User;
use Illuminate\Support\Facades\Auth;


class RefreshFacebookData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:refresh-facebook-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Facebook data daily';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        info('Starting Facebook data refresh...');
        $usersAccounts = UserFacebookAccount::all();
        $facebookComponent = new Facebook();
        if ($facebookComponent)
        {
            foreach ($usersAccounts as $account) {
                $user = User::all();
                if ($user)
                {
                    Auth::login($user);
                    $facebookComponent->refreshData($account->facebook_id);
                }
            }
        }
        info('Facebook data refresh completed successfully.');
    }
}
