<?php

namespace App\Http\Livewire\Auth;

use App\Models\UserDeleteRequest;
use Livewire\Component;

class DeleteRequest extends Component
{
    public $email = '';
    public $successMessage = '';

    protected $rules = [
        'email' => 'required|email',
    ];

    public function store()
    {
        $attributes = $this->validate();

        UserDeleteRequest::create($attributes);
    
        $this->reset('email');
        $this->successMessage = 'Account deletion request has been successfully submitted.';
    } 

    public function render()
    {
        return view('livewire.auth.delete-request');
    }
}
