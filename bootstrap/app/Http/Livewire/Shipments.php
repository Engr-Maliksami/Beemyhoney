<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Services\DpdService;
use Livewire\WithPagination;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Invoice as Invoices;

class Shipments extends Component
{
    use WithPagination;

    public $shippingMethodId = 1;

    public $selectedStatus = 'pending';
    public $searchText     = '';
    public $selectedDateRange;

    public $currentPage = 1;
    public $pageSize = 50;

    public $deleteParcel_no = '';
    
    public function mount()
    {
        $this->selectedDateRange = Carbon::today()->subDays(30)->format('Y-m-d') . ' to ' . Carbon::today()->format('Y-m-d');
    }

    public function getShipments()
    {
        $dpdService = new DpdService($this->shippingMethodId);
        $queryParams = [
            'limit' => $this->pageSize,
            'page' => $this->currentPage,
            'status[]' => $this->selectedStatus
        ];

        if ($this->searchText != '')
        {
            $queryParams['parcelNumber'] = $this->searchText;
        }

        $dates = explode(' to ', $this->selectedDateRange);
        if (count($dates) === 2) {
            try {
                $creationDateFrom = \Carbon\Carbon::createFromFormat('Y-m-d', $dates[0])->format('Y-m-d');
                $creationDateTo = \Carbon\Carbon::createFromFormat('Y-m-d', $dates[1])->format('Y-m-d');
                $queryParams['creationDateFrom'] = $creationDateFrom;
                $queryParams['creationDateTo'] = $creationDateTo;
            } catch (\Exception $e) {
            }
        }
        
        try {
            $response = $dpdService->getShipments($queryParams);
            $items = $response['items'];
            $total = $response['total'];
    
            return new LengthAwarePaginator(
                $items,
                $total,
                $this->pageSize,
                $this->currentPage,
                ['path' => Paginator::resolveCurrentPath()]
            );
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to fetch shipments: ' . $e->getMessage());
            return new LengthAwarePaginator([], 0, $this->pageSize, $this->currentPage, ['path' => Paginator::resolveCurrentPath()]);
        }
    }

    public function deleteShipment()
    {
        $dpdService = new DpdService($this->shippingMethodId);
        
        try {
            $response = $dpdService->deleteShipments("0".$this->deleteParcel_no);
            if ($response && isset($response['success']) && $response['success']) {
                $Invoice = Invoices::where('parcel_no', $this->deleteParcel_no)->first();
                if ($Invoice) {
                    $Invoice->parcel_no = '';
                    $Invoice->shipment_id = '';
                    $Invoice->save();
                }
                $this->deleteParcel_no = '';    
                session()->flash('success', 'Shipments Deleted Successfully');
            } else {
                throw new \Exception('Failed to delete shipment: Response was unsuccessful.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete shipment: ' . $e->getMessage());
        }
    }

    public function confirmDeleteShipment($parcel_no)
    {
        $this->deleteParcel_no = $parcel_no;
        $this->dispatchBrowserEvent('swal:confirm', [
            'title' => 'Are you sure?',
            'text' => 'This will permanently delete the shipment.',
            'type' => 'warning',
            'function' => 'shipment',
            'showCancelButton' => true,
            'confirmButtonText' => 'Yes, delete it!',
            'cancelButtonText' => 'No, keep it',
        ]);
    }

    public function previousPage()
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
        }
    }

    public function nextPage()
    {
        if ($this->currentPage < $this->lastPage()) {
            $this->currentPage++;
        }
    }

    public function gotoPage($page)
    {
        $this->currentPage = $page;
    }

    public function render()
    {
        return view('livewire.shipments', [
            'Shipments' => $this->getShipments(),
        ]);
    }
}
