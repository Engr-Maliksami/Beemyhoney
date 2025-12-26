<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Inbound as InboundModel;
use App\Models\Product;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Inbound extends Component
{
    use WithPagination, WithFileUploads;

    // Component properties
    public $name, $sender_name, $carrier_name, $delivery_transport_no,
        $doc_comment, $delivery_date;

    public $selectedProducts = [];
    public $attachments = [];
    public $availableProducts;

    // Search and filter properties
    public $searchText = '';
    public $selectedCarrier = '';
    public $selectedDateRange = '';

    // Edit mode
    public $editInboundId;
    public $deleteInboundId;

    public $selectedInbound;

    // Add this method to show inbound details
    public function showInboundDetails($id)
    {
        $this->selectedInbound = InboundModel::with([
            'products' => function ($query) {
                $query->select([
                    'products.id',
                    'products.name',
                    'products.description',
                    'products.sku',
                    'products.ean',
                    'products.price',
                    'products.weight',
                    'products.stock_quantity',
                    'products.status',
                    'products.image_url',
                    'products.batch_number',
                    'products.expire_date',
                    'products.net_weight',
                    'products.gross_weight',
                    'products.length',
                    'products.width',
                    'products.height',
                    'products.quantity_per_box',
                    'products.quantity_per_pallet',
                    'products.comment'
                ]);
            },
            'attachments'
        ])->findOrFail($id);
    }

    // Helper method to format file sizes
    public function formatFileSize($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    protected $rules = [
        'name'                   => 'required|string|max:255',
        'sender_name'            => 'nullable|string|max:255',
        'carrier_name'           => 'nullable|string|max:255',
        'delivery_transport_no'  => 'nullable|string|max:255',
        'doc_comment'            => 'nullable|string|max:1000',
        'delivery_date'          => 'nullable|date',
        'selectedProducts'       => 'nullable|array',
        'selectedProducts.*'     => 'exists:products,id',
        'attachments'            => 'nullable|array',
        'attachments.*.file_name' => 'nullable|string|max:255',
        'attachments.*.file'     => 'nullable|file|max:10240',
        'attachments.*.description' => 'nullable|string|max:500',
    ];

    protected $listeners = [
        'resetFields' => 'resetFields'
    ];

    public function mount()
    {
        $this->availableProducts = Product::where('status', 'active')->get();
        $this->addAttachment(); // Start with one attachment field
    }

    public function addInbound()
    {
        $this->validate();

        try {
            // Create inbound record
            $inbound = InboundModel::create([
                'name'                  => $this->name,
                'sender_name'          => $this->sender_name,
                'carrier_name'         => $this->carrier_name,
                'delivery_transport_no' => $this->delivery_transport_no,
                'doc_comment'          => $this->doc_comment,
                'delivery_date'        => $this->delivery_date,
            ]);

            // Attach products if selected
            if (!empty($this->selectedProducts)) {
                $inbound->products()->attach($this->selectedProducts);
            }

            // Handle attachments
            if (!empty($this->attachments)) {
                foreach ($this->attachments as $attachmentData) {
                    if (isset($attachmentData['file']) && $attachmentData['file']) {
                        $filePath = $attachmentData['file']->store('inbound-attachments', 'public');

                        $attachment = Attachment::create([
                            'file_name'   => $attachmentData['file_name'] ?? $attachmentData['file']->getClientOriginalName(),
                            'files'       => $filePath,
                            'description' => $attachmentData['description'] ?? null,
                        ]);

                        $inbound->attachments()->attach($attachment->id);
                    }
                }
            }

            $this->resetFields();
            $this->emit('inboundAdded');
            session()->flash('success', 'Inbound created successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Error creating inbound: ' . $e->getMessage());
        }
    }

    public function editInbound($id)
    {
        $this->editInboundId = $id;
        $inbound = InboundModel::with(['products', 'attachments'])->findOrFail($id);

        $this->name = $inbound->name;
        $this->sender_name = $inbound->sender_name;
        $this->carrier_name = $inbound->carrier_name;
        $this->delivery_transport_no = $inbound->delivery_transport_no;
        $this->doc_comment = $inbound->doc_comment;
        $this->delivery_date = $inbound->delivery_date;

        $this->selectedProducts = $inbound->products->pluck('id')->toArray();

        // Load existing attachments (without files for security)
        $this->attachments = $inbound->attachments->map(function ($attachment) {
            return [
                'id' => $attachment->id,
                'file_name' => $attachment->file_name,
                'description' => $attachment->description,
                'existing' => true
            ];
        })->toArray();
    }

    public function updateInbound()
    {
        $this->validate([
            'name'                   => 'required|string|max:255',
            'sender_name'            => 'nullable|string|max:255',
            'carrier_name'           => 'nullable|string|max:255',
            'delivery_transport_no'  => 'nullable|string|max:255',
            'doc_comment'            => 'nullable|string|max:1000',
            'delivery_date'          => 'nullable|date',
            'selectedProducts'       => 'nullable|array',
            'selectedProducts.*'     => 'exists:products,id',
        ]);

        try {
            $inbound = InboundModel::findOrFail($this->editInboundId);

            $inbound->update([
                'name'                  => $this->name,
                'sender_name'          => $this->sender_name,
                'carrier_name'         => $this->carrier_name,
                'delivery_transport_no' => $this->delivery_transport_no,
                'doc_comment'          => $this->doc_comment,
                'delivery_date'        => $this->delivery_date,
            ]);

            // Update product relationships
            $inbound->products()->sync($this->selectedProducts ?? []);

            // Handle new attachments
            foreach ($this->attachments as $attachmentData) {
                if (!isset($attachmentData['existing']) && isset($attachmentData['file']) && $attachmentData['file']) {
                    $filePath = $attachmentData['file']->store('inbound-attachments', 'public');

                    $attachment = Attachment::create([
                        'file_name'   => $attachmentData['file_name'] ?? $attachmentData['file']->getClientOriginalName(),
                        'files'       => $filePath,
                        'description' => $attachmentData['description'] ?? null,
                    ]);

                    $inbound->attachments()->attach($attachment->id);
                }
            }

            $this->resetFields();
            $this->emit('inboundUpdated');
            session()->flash('success', 'Inbound updated successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Error updating inbound: ' . $e->getMessage());
        }
    }

    public function confirmDelete($id)
    {
        $this->deleteInboundId = $id;
        $this->dispatchBrowserEvent('swal:confirm', [
            'title'             => 'Are you sure?',
            'text'              => "You won't be able to revert this!",
            'type'              => 'warning',
            'showCancelButton'  => true,
            'confirmButtonText' => 'Yes, delete it!',
            'cancelButtonText'  => 'Cancel',
            'function'          => 'inbound'
        ]);
    }

    public function deleteInbound()
    {
        try {
            $inbound = InboundModel::findOrFail($this->deleteInboundId);

            // Delete associated attachment files
            foreach ($inbound->attachments as $attachment) {
                if (Storage::disk('public')->exists($attachment->files)) {
                    Storage::disk('public')->delete($attachment->files);
                }
            }

            // Delete the inbound (cascade will handle pivot tables)
            $inbound->delete();

            session()->flash('success', 'Inbound deleted successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting inbound: ' . $e->getMessage());
        }
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
        $this->attachments = array_values($this->attachments); // Re-index array
    }

    public function removeProduct($productId)
    {
        $this->selectedProducts = array_filter($this->selectedProducts, function ($id) use ($productId) {
            return $id != $productId;
        });
    }

    public function resetFields()
    {
        $this->name = '';
        $this->sender_name = '';
        $this->carrier_name = '';
        $this->delivery_transport_no = '';
        $this->doc_comment = '';
        $this->delivery_date = '';
        $this->selectedProducts = [];
        $this->attachments = [['file_name' => '', 'file' => null, 'description' => '']];
        $this->editInboundId = null;
        $this->resetValidation();
    }

    // Pagination methods
    public function gotoPage($page)
    {
        $this->setPage($page);
    }

    public function previousPage()
    {
        $this->setPage($this->getPage() - 1);
    }

    public function nextPage()
    {
        $this->setPage($this->getPage() + 1);
    }

    // Update search results
    public function updatingSearchText()
    {
        $this->resetPage();
    }

    public function updatingSelectedCarrier()
    {
        $this->resetPage();
    }

    public function updatingSelectedDateRange()
    {
        $this->resetPage();
    }
    public function render()
    {
        $query = InboundModel::query()
            ->withCount(['products', 'attachments']);

        // Apply search filters
        if ($this->searchText) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchText . '%')
                    ->orWhere('sender_name', 'like', '%' . $this->searchText . '%')
                    ->orWhere('carrier_name', 'like', '%' . $this->searchText . '%');
            });
        }

        if ($this->selectedCarrier) {
            $query->where('carrier_name', $this->selectedCarrier);
        }

        if ($this->selectedDateRange) {
            $dates = explode(' to ', $this->selectedDateRange);
            if (count($dates) === 2) {
                $query->whereBetween('delivery_date', [
                    Carbon::createFromFormat('Y-m-d', trim($dates[0]))->startOfDay(),
                    Carbon::createFromFormat('Y-m-d', trim($dates[1]))->endOfDay(),
                ]);
            }
        }

        $inbounds = $query->latest()->paginate(10);

        return view('livewire.inbound', [
            'Inbounds' => $inbounds
        ]);
    }
}
