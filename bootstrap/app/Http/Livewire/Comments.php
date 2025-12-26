<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use App\Models\FacebookWebhookCall;
use Livewire\WithPagination;
use Illuminate\Validation\Rule;

class Comments extends Component
{
    use WithPagination;

    public $searchClient;
    public $selectedIds = [];
    public $selectAll = false;
    public $searchText = "";
    public $selectedSource = "";
    public $selectedDateRange;


    public function mount()
    {
        $this->searchCustomerId = Request::get('FBId', 0);
    }

    public function updatedSearchClient()
    {
        $this->resetPage();
    }

    public function getUserComments()
    {
        return FacebookWebhookCall::with(['customer', 'facebookPage'])
            ->where('item_type','comment')
            ->where('cus_fb_id', '!=', '106735618295724')
            ->when($this->searchText, function ($query) {
                $query->where(function ($q) {
                    $q->where('order_id', 'like', '%' . $this->searchText . '%')
                    ->orWhere('cus_fb_id', 'like', '%' . $this->searchText . '%')
                    ->orWhere('cus_fb_name', 'like', '%' . $this->searchText . '%')
                    ->orWhere('message', 'like', '%' . $this->searchText . '%');
                });
            })
            ->when($this->selectedSource, function ($query) {
                if ($this->selectedSource == 'with') {
                    $query->whereNotNull('order_id');
                } else {
                    $query->whereNull('order_id');
                }
            })
            ->when($this->searchCustomerId, function ($query) {
                $query->where('cus_fb_id', $this->searchCustomerId);
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
        return view('livewire.comments', [
            'UserComments' => $this->getUserComments()
        ]);
    }
}
