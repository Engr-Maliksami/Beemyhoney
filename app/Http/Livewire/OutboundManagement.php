<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Outbound;
use App\Models\Product;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class OutboundManagement extends Component
{
    use WithPagination, WithFileUploads;

    protected $paginationTheme = 'bootstrap';

    // Search and filter properties
    public $searchText = '';
    public $selectedDeliveryType = '';
    public $selectedDateRange = '';

    // Form properties
    public $order_ext_id;
    public $order_ext_number;
    public $recipient_name;
    public $recipient_company_code;
    public $recipient_street;
    public $recipient_city;
    public $recipient_post_code;
    public $recipient_country;
    public $recipient_county;
    public $recipient_parish;
    public $recipient_house_name;
    public $recipient_email;
    public $recipient_tel;
    public $carrier_name;
    public $carrier_transport_no;
    public $delivery_type_code;
    public $order_comment;
    public $delivery_date;

    // Dynamic properties
    public $selectedProducts = [];
    public $attachments = [];
    public $availableProducts = [];

    // Edit mode
    public $editMode = false;
    public $editingOutboundId;

    // Delete confirmation
    public $confirmingDeletion = false;
    public $deletingOutboundId;

    protected $rules = [
        'order_ext_id' => 'required|integer',
        'order_ext_number' => 'nullable|string|max:255',
        'recipient_name' => 'required|string|max:255',
        'recipient_company_code' => 'nullable|string|max:255',
        'recipient_street' => 'required|string|max:255',
        'recipient_city' => 'required|string|max:255',
        'recipient_post_code' => 'required|string|max:255',
        'recipient_country' => 'required|string|max:255',
        'recipient_county' => 'nullable|string|max:255',
        'recipient_parish' => 'nullable|string|max:255',
        'recipient_house_name' => 'nullable|string|max:255',
        'recipient_email' => 'required|email|max:255',
        'recipient_tel' => 'required|string|max:255',
        'carrier_name' => 'nullable|string|max:255',
        'carrier_transport_no' => 'nullable|string|max:255',
        'delivery_type_code' => 'required|in:SD,WL,CT',
        'order_comment' => 'nullable|string',
        'delivery_date' => 'nullable|date',
        'selectedProducts' => 'required|array|min:1',
        'selectedProducts.*' => 'exists:products,id',
        'attachments.*.file_name' => 'required|string|max:255',
        'attachments.*.file' => 'required|file|max:10240',
        'attachments.*.description' => 'nullable|string|max:255',
    ];

    public function mount()
    {
        $this->loadAvailableProducts();
        $this->addAttachment(); // Start with one attachment field
    }

    public function updatingSearchText()
    {
        $this->resetPage();
    }

    public function updatingSelectedDeliveryType()
    {
        $this->resetPage();
    }

    public function updatingSelectedDateRange()
    {
        $this->resetPage();
    }

    public function loadAvailableProducts()
    {
        $this->availableProducts = Product::orderBy('name')->get();
    }

    public function addAttachment()
    {
        $this->attachments[] = [
            'file_name' => '',
            'file' => null,
            'description' => ''
        ];
    }

    public function removeAttachment($index)
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function addOutbound()
    {
        $this->validate();

        try {
            $outbound = Outbound::create([
                'order_ext_id' => $this->order_ext_id,
                'order_ext_number' => $this->order_ext_number,
                'recipient_name' => $this->recipient_name,
                'recipient_company_code' => $this->recipient_company_code,
                'recipient_street' => $this->recipient_street,
                'recipient_city' => $this->recipient_city,
                'recipient_post_code' => $this->recipient_post_code,
                'recipient_country' => $this->recipient_country,
                'recipient_county' => $this->recipient_county,
                'recipient_parish' => $this->recipient_parish,
                'recipient_house_name' => $this->recipient_house_name,
                'recipient_email' => $this->recipient_email,
                'recipient_tel' => $this->recipient_tel,
                'carrier_name' => $this->carrier_name,
                'carrier_transport_no' => $this->carrier_transport_no,
                'delivery_type_code' => $this->delivery_type_code,
                'order_comment' => $this->order_comment,
                'delivery_date' => $this->delivery_date,
            ]);

            // Attach products
            $outbound->products()->attach($this->selectedProducts);

            // Handle attachments
            foreach ($this->attachments as $attachmentData) {
                if ($attachmentData['file']) {
                    $filePath = $attachmentData['file']->store('outbound-attachments', 'public');

                    $attachment = Attachment::create([
                        'file_name' => $attachmentData['file_name'],
                        'file_path' => $filePath,
                        'original_name' => $attachmentData['file']->getClientOriginalName(),
                        'file_size' => $attachmentData['file']->getSize(),
                        'mime_type' => $attachmentData['file']->getMimeType(),
                        'description' => $attachmentData['description'],
                    ]);

                    $outbound->attachments()->attach($attachment->id);
                }
            }

            $this->resetFields();
            $this->emit('outboundAdded');
            session()->flash('success', 'Outbound order created successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Error creating outbound order: ' . $e->getMessage());
        }
    }

    public function editOutbound($outboundId)
    {
        $outbound = Outbound::with('products', 'attachments')->findOrFail($outboundId);

        $this->editMode = true;
        $this->editingOutboundId = $outboundId;

        $this->order_ext_id = $outbound->order_ext_id;
        $this->order_ext_number = $outbound->order_ext_number;
        $this->recipient_name = $outbound->recipient_name;
        $this->recipient_company_code = $outbound->recipient_company_code;
        $this->recipient_street = $outbound->recipient_street;
        $this->recipient_city = $outbound->recipient_city;
        $this->recipient_post_code = $outbound->recipient_post_code;
        $this->recipient_country = $outbound->recipient_country;
        $this->recipient_county = $outbound->recipient_county;
        $this->recipient_parish = $outbound->recipient_parish;
        $this->recipient_house_name = $outbound->recipient_house_name;
        $this->recipient_email = $outbound->recipient_email;
        $this->recipient_tel = $outbound->recipient_tel;
        $this->carrier_name = $outbound->carrier_name;
        $this->carrier_transport_no = $outbound->carrier_transport_no;
        $this->delivery_type_code = $outbound->delivery_type_code;
        $this->order_comment = $outbound->order_comment;
        $this->delivery_date = $outbound->delivery_date;

        $this->selectedProducts = $outbound->products->pluck('id')->toArray();
    }

    public function updateOutbound()
    {
        $this->validate();

        try {
            $outbound = Outbound::findOrFail($this->editingOutboundId);

            $outbound->update([
                'order_ext_id' => $this->order_ext_id,
                'order_ext_number' => $this->order_ext_number,
                'recipient_name' => $this->recipient_name,
                'recipient_company_code' => $this->recipient_company_code,
                'recipient_street' => $this->recipient_street,
                'recipient_city' => $this->recipient_city,
                'recipient_post_code' => $this->recipient_post_code,
                'recipient_country' => $this->recipient_country,
                'recipient_county' => $this->recipient_county,
                'recipient_parish' => $this->recipient_parish,
                'recipient_house_name' => $this->recipient_house_name,
                'recipient_email' => $this->recipient_email,
                'recipient_tel' => $this->recipient_tel,
                'carrier_name' => $this->carrier_name,
                'carrier_transport_no' => $this->carrier_transport_no,
                'delivery_type_code' => $this->delivery_type_code,
                'order_comment' => $this->order_comment,
                'delivery_date' => $this->delivery_date,
            ]);

            // Update products
            $outbound->products()->sync($this->selectedProducts);

            // Handle new attachments
            foreach ($this->attachments as $attachmentData) {
                if ($attachmentData['file']) {
                    $filePath = $attachmentData['file']->store('outbound-attachments', 'public');

                    $attachment = Attachment::create([
                        'file_name' => $attachmentData['file_name'],
                        'file_path' => $filePath,
                        'original_name' => $attachmentData['file']->getClientOriginalName(),
                        'file_size' => $attachmentData['file']->getSize(),
                        'mime_type' => $attachmentData['file']->getMimeType(),
                        'description' => $attachmentData['description'],
                    ]);

                    $outbound->attachments()->attach($attachment->id);
                }
            }

            $this->resetFields();
            $this->emit('outboundUpdated');
            session()->flash('success', 'Outbound order updated successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Error updating outbound order: ' . $e->getMessage());
        }
    }

    public function confirmDelete($outboundId)
    {
        $this->deletingOutboundId = $outboundId;

        $this->dispatchBrowserEvent('swal:confirm', [
            'type' => 'warning',
            'title' => 'Are you sure?',
            'text' => 'You won\'t be able to revert this!',
            'showCancelButton' => true,
            'confirmButtonText' => 'Yes, delete it!',
            'cancelButtonText' => 'Cancel',
            'function' => 'outbound'
        ]);
    }

    public function deleteOutbound()
    {
        try {
            $outbound = Outbound::findOrFail($this->deletingOutboundId);

            // Delete associated attachment files
            foreach ($outbound->attachments as $attachment) {
                if (Storage::disk('public')->exists($attachment->file_path)) {
                    Storage::disk('public')->delete($attachment->file_path);
                }
            }

            $outbound->delete();

            session()->flash('success', 'Outbound order deleted successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting outbound order: ' . $e->getMessage());
        }
    }

    public $viewingOutbound;
    public $viewingProducts = [];
    public $viewingAttachments = [];

    public function showOutboundDetails($outboundId)
    {
        $this->viewingOutbound = Outbound::with('products', 'attachments')->findOrFail($outboundId);
        $this->viewingProducts = $this->viewingOutbound->products;
        $this->viewingAttachments = $this->viewingOutbound->attachments;
    }

    public function resetFields()
    {
        $this->editMode = false;
        $this->editingOutboundId = null;

        $this->order_ext_id = '';
        $this->order_ext_number = '';
        $this->recipient_name = '';
        $this->recipient_company_code = '';
        $this->recipient_street = '';
        $this->recipient_city = '';
        $this->recipient_post_code = '';
        $this->recipient_country = '';
        $this->recipient_county = '';
        $this->recipient_parish = '';
        $this->recipient_house_name = '';
        $this->recipient_email = '';
        $this->recipient_tel = '';
        $this->carrier_name = '';
        $this->carrier_transport_no = '';
        $this->delivery_type_code = '';
        $this->order_comment = '';
        $this->delivery_date = '';

        $this->selectedProducts = [];
        $this->attachments = [];
        $this->addAttachment(); // Add one empty attachment field

        $this->resetErrorBag();
    }

    public function render()
    {
        $query = Outbound::with('products')
            ->withCount('products');

        // Apply search filters
        if ($this->searchText) {
            $query->where(function ($q) {
                $q->where('order_ext_number', 'like', '%' . $this->searchText . '%')
                    ->orWhere('recipient_name', 'like', '%' . $this->searchText . '%')
                    ->orWhere('carrier_name', 'like', '%' . $this->searchText . '%');
            });
        }

        if ($this->selectedDeliveryType) {
            $query->where('delivery_type_code', $this->selectedDeliveryType);
        }

        if ($this->selectedDateRange) {
            $dateRange = explode(' to ', $this->selectedDateRange);
            if (count($dateRange) === 2) {
                $startDate = Carbon::createFromFormat('Y-m-d', $dateRange[0])->startOfDay();
                $endDate = Carbon::createFromFormat('Y-m-d', $dateRange[1])->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }
        }

        $Outbounds = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('livewire.outbound-management', compact('Outbounds'));
    }
}
