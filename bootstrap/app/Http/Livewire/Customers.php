<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use App\Models\UserCustomers;
use App\Models\CustomerAddress;
use App\Models\FacebookWebhookCall;
use Livewire\WithPagination;
use Illuminate\Validation\Rule;

class Customers extends Component
{
    use WithPagination;

    public $searchClient;
    public $CustomerFBID;
    public $CustomerName;
    public $CustomerEmail;
    public $CustomerPhone;
    public $editCustomerId;
    public $deleteCustomerId = null;
    public $deleteAddressId = null;
    public $selectedIds = [];
    public $selectAll = false;
    public $selectedSource = "";
    public $searchText = "";
    public $customerAddresses = [];
    public $currentCustomerId;
    public $searchCustomerId;
    public $CustomerComments = [];
    public $selectedDateRange;

    protected $rules = [
        'CustomerName'   => 'required|string|max:255',
        'CustomerFBID'   => 'nullable|numeric',
        'CustomerEmail' => 'nullable|email|unique:user_customers,email,NULL,deleted_at',
        'CustomerPhone'  => 'nullable|numeric|digits_between:10,15',
    ];

    public function mount()
    {
        $this->searchCustomerId = Request::get('CustId', 0);
    }

    public function showCustomerAddresses($customerId)
    {
        $this->currentCustomerId = $customerId;
        $this->customerAddresses = UserCustomers::find($customerId)->addresses()->get();
    }

    public function confirmDeleteAddress($addressId)
    {
        $this->deleteAddressId = $addressId;
        $this->dispatchBrowserEvent('swal:confirm', [
            'title' => 'Are you sure?',
            'text' => 'This will permanently delete the address.',
            'type' => 'warning',
            'function' => 'address',
            'showCancelButton' => true,
            'confirmButtonText' => 'Yes, delete it!',
            'cancelButtonText' => 'No, keep it',
        ]);
    }

    public function deleteAddress()
    {
        if ($this->deleteAddressId) {
            $address = CustomerAddress::find($this->deleteAddressId);
            if ($address) {
                $address->delete();
                $this->reset(['deleteAddressId']);
                $this->showCustomerAddresses($this->currentCustomerId);
            }
        }
    }

    public function updatedSearchClient()
    {
        $this->resetPage();
    }

    public function addCustomer()
    {
        $this->validate();

        UserCustomers::create([
            'facebook_id' => $this->CustomerFBID,
            'name'        => $this->CustomerName,
            'email'       => $this->CustomerEmail,
            'phone'       => $this->CustomerPhone,
            'source'      => 'manual'
        ]);

        session()->flash('success', 'Customer added successfully.');

        $this->resetFields();
        $this->emit('customerAdded');
    }

    public function editCustomer($id)
    {
        $customer = UserCustomers::findOrFail($id);

        $this->editCustomerId = $customer->id;
        $this->CustomerFBID   = $customer->facebook_id;
        $this->CustomerName   = $customer->name;
        $this->CustomerEmail  = $customer->email;
        $this->CustomerPhone  = $customer->phone;
    }

    public function updateCustomer()
    {
        $validatedData = $this->validate([
            'CustomerName'   => 'required|string|max:255',
            'CustomerFBID'   => 'nullable|numeric',
            'CustomerEmail'  => [
                'nullable',
                'email',
                Rule::unique('user_customers', 'email')->ignore($this->editCustomerId)->whereNull('deleted_at'),
            ],
            'CustomerPhone'  => 'nullable|numeric|digits_between:10,15',
        ]);

        if ($this->editCustomerId) {
            $customer = UserCustomers::findOrFail($this->editCustomerId);

            $customer->update([
                'facebook_id' => $this->CustomerFBID,
                'name'        => $this->CustomerName,
                'email'       => $this->CustomerEmail,
                'phone'       => $this->CustomerPhone,
            ]);

            session()->flash('success', 'Customer updated successfully.');

            $this->resetFields();
            $this->emit('customerUpdated');
        }
    }

    public function confirmDelete($id)
    {
        $this->deleteCustomerId = $id;
        $this->dispatchBrowserEvent('swal:confirm', [
            'title' => 'Are you sure?',
            'text' => 'This will permanently delete the customer.',
            'type' => 'warning',
            'function' => 'customer',
            'showCancelButton' => true,
            'confirmButtonText' => 'Yes, delete it!',
            'cancelButtonText' => 'No, keep it',
        ]);
    }

    public function deleteCustomer()
    {
        if ($this->deleteCustomerId) {
            $customer = UserCustomers::find($this->deleteCustomerId);
            if ($customer) {

                if ($customer->orders()->count() > 0) {
                    session()->flash('error', 'Customer cannot be deleted as they have existing orders.');
                    return;
                }

                $customer->addresses()->delete();
                $customer->delete();
                session()->flash('success', 'Customer deleted successfully.');
                $this->reset(['deleteCustomerId']);
            }
        }
    }

    public function viewComments($FBId)
    {
        $this->CustomerComments = FacebookWebhookCall::where('cus_fb_id', $FBId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function resetFields()
    {
        $this->reset(['CustomerFBID', 'CustomerName', 'CustomerEmail', 'CustomerPhone', 'editCustomerId']);
    }

    public function getUserCustomers()
    {

        return UserCustomers::with('orders')->when($this->searchText, function ($query) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchText . '%')
                    ->orWhere('email', 'like', '%' . $this->searchText . '%')
                    ->orWhere('phone', 'like', '%' . $this->searchText . '%');
            });
        })
            ->when($this->selectedSource, function ($query) {
                $query->where('source', $this->selectedSource);
            })
            ->when($this->searchCustomerId, function ($query) {
                $query->where('id', $this->searchCustomerId);
            })
            ->when($this->selectedDateRange, function ($query) {
                $dates = explode(' to ', $this->selectedDateRange);
                if (count($dates) === 2) {
                    $query->whereBetween('created_at', [$dates[0], $dates[1]]);
                } elseif (count($dates) === 1) {
                    $query->whereDate('created_at', $dates[0]);
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(100);
    }

    public function render()
    {
        return view('livewire.customers', [
            'UserCustomers' => $this->getUserCustomers()
        ]);
    }
}
