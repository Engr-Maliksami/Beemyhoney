<?php

namespace App\Http\Livewire;

use App\Models\Country;
use App\Models\City;
use App\Models\CustomerAddress;
use Livewire\Component;

class CustomerAddressComponent extends Component
{
    public $customer_id;
    public $name, $contact_name, $email, $phone, $info, $selectedCountry, $selectedCity;
    public $countries = [], $cities = [];

    protected $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email',
        'phone' => ['required', 'regex:/^\+(\d{1,4})\s?(\d{7,15})$/'],
        'selectedCountry' => 'required|exists:countries,id',
        'selectedCity' => 'required|exists:cities,id',
    ];

    protected $listeners = ['openAddAddressModal','updatedSelectedCountry','updatedSelectedCity'];

    public function hydrate()
    {
        $this->emit('select2');
    }

    public function openAddAddressModal($customer_id)
    {
        $this->reset();
        $this->customer_id = $customer_id;
        $this->dispatchBrowserEvent('show-address-modal');
    }

    public function updatedSelectedCountry($countryId)
    {
        $this->selectedCountry = $countryId;
        if ($countryId) {
            $this->cities = City::where('country_id', $countryId)->get();
        } else {
            $this->cities = [];
        }
        $this->selectedCity = null;
    }

    public function updatedSelectedCity($cityId)
    {
        $this->selectedCity = $cityId;
    }

    public function saveAddress()
    {
        $this->validate();

        CustomerAddress::create([
            'user_customer_id' => $this->customer_id,
            'name' => $this->name,
            'contact_name' => $this->contact_name,
            'city_id' => $this->selectedCity,
            'info' => $this->info,
            'email' => $this->email,
            'phone' => $this->phone,
            'country_id' => $this->selectedCountry,
        ]);

        $this->reset();
        $this->dispatchBrowserEvent('hide-address-modal');
        $this->emit('addressAdded');
        session()->flash('success', 'Address added successfully!');
    }

    public function render()
    {
        $this->countries = Country::all();
        return view('livewire.customer-address-component');
    }
}
