<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\UserFacebookPage;
use Illuminate\Support\Facades\Auth;


class FacebookPages extends Component
{
    public $facebook_id;

    public function mount($facebook_id)
    {
        $this->facebook_id = $facebook_id;
    }

    public function render()
    {
        $userFacebookPages = UserFacebookPage::where('facebook_id', $this->facebook_id)
            ->get();
        return view('livewire.facebook-pages', ['pages' => $userFacebookPages]);
    }
}
