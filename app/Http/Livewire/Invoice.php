<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Product;
use App\Models\UserCustomers;
use App\Models\Country;
use App\Models\City;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\FacebookWebhookCall;
use App\Models\UserFacebookPage;
use App\Models\Invoice as Invoices;
use App\Models\Setting;
use Livewire\WithPagination;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Http\Livewire\Facebook;
use App\Services\DpdService;
use Illuminate\Support\Facades\Response;
use ZipArchive;

class Invoice extends Component
{
    use WithPagination;
    use WithFileUploads;

    public $selectedIds = [];
    public $selectAll = false;
    public $selectedStatus = "";
    public $selectedSource = "";
    public $searchText = "";
    public $selectedDateRange;

    public $shippingMethodId = 1;

    public $start_date;
    public $end_date;

    public $grandTotal = 0.00;
    public $subTotal = 0.00;
    public $dpdFee = 0.00;
    public $deliveryFee = 0.00;

    public $editInvoiceId;
    public $editInvoiceData = [];
    public $editselectedStatus = "pending";

    public $editableOrderDates = [];

    public $selectedAddressId = null;
    public $customerAddresses = [];
    public $addNewAddress = false;

    public $name, $contact_name, $email, $phone, $info, $selectedCountry, $selectedCity;
    public $countries = [], $cities = [];

    public $pickups = [], $selectedPickup;

    protected $listeners = ['openAddAddressModal','updatedSelectedCountry','updatedSelectedCity','updatedSelectedPickup', 'updateOrderDate' ];

    public function openAddAddressModal()
    {
        $this->reset();
        $this->dispatchBrowserEvent('show-address-modal');
    }

    public function resetFields()
    {
        $this->reset([]);
    }

    public function mount()
    {
        $this->start_date = now()->subDays(14)->format('Y-m-d');
        $this->end_date = now()->format('Y-m-d');
    }

    public function hydrate()
    {
        $this->emit('select2');
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

    public function updatedSelectedPickup($pudoId)
    {
        $this->selectedPickup = $pudoId;
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedIds = $this->getInvocies()->pluck('id')->toArray();
        } else {
            $this->selectedIds = [];
        }
    }

    public function saveAddress()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => ['required', 'regex:/^\+(\d{1,4})\s?(\d{7,15})$/'],
            'selectedCountry' => 'required|exists:countries,id',
            'selectedCity' => 'required|exists:cities,id',
        ];
        $this->validate($rules);

        $Address = CustomerAddress::create([
            'user_customer_id' => $this->editInvoiceData->userCustomer->id,
            'name' => $this->name,
            'contact_name' => $this->contact_name,
            'city_id' => $this->selectedCity,
            'info' => $this->info,
            'email' => $this->email,
            'phone' => $this->phone,
            'country_id' => $this->selectedCountry,
        ]);

        $this->customerAddresses = UserCustomers::find($this->editInvoiceData->userCustomer->id)->addresses;
        $this->selectedAddressId = $Address->id;
        $this->getLockers();
        $this->addNewAddress = false;

        $this->reset([
            'name', 
            'contact_name', 
            'info', 
            'email', 
            'phone', 
            'selectedCity', 
            'selectedCountry',
        ]);        
    }

    public function setAddressFlag($flag){
        $this->reset([
            'name', 
            'contact_name', 
            'info', 
            'email', 
            'phone', 
            'selectedCity', 
            'selectedCountry',
        ]);
        $this->addNewAddress = $flag;
    }
    
    public function getCurrency() {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.currencyfreaks.com/v2.0/rates/latest?apikey=2524cde93c564fb2b3ba8d82d7120139',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));
    
        $response = curl_exec($curl);
        curl_close($curl);
    
        $data = json_decode($response, true);
    
        // Example: get specific currencies
        $eur = isset($data['rates']['EUR']) ? round($data['rates']['EUR'], 2) : null;
    
        return [
            'EUR' => $eur,
        ];
    }

    public function sendInvoice($id)
    {
        // Fetch the invoice details along with associated orders and customer addresses
        $Invoice = Invoices::with('orders.orderDetails.product', 'userCustomer')
            ->where('id', $id)
            ->firstOrFail();
        
        // Collect the address IDs from orders
        $Addresses = CustomerAddress::where('id', $Invoice->address_id)
            ->get();
        $PaymentInfo = Setting::where('key','payment_info')->first();
        $euroRate = Setting::where('key','euro_rate')->first();
        
        $invoiceData = [
            'invoice' => $Invoice,
            'addresses' => $Addresses,
            'payment_info' => $PaymentInfo,
            'euroRate' => $euroRate
        ];

        // Generate the PDF
        $pdf = Pdf::loadView('livewire.invoice', $invoiceData);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOption('margin-top', 0);
        $pdf->setOption('margin-right', 0);
        $pdf->setOption('margin-bottom', 0);
        $pdf->setOption('margin-left', 0);
        $pdf->setOption('defaultFont','DejaVu Sans');

        // Define the path for saving the PDF
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $fileName = $Invoice->userCustomer->name .' - '. $Invoice->invoice_number . '.pdf';
        $filePath = storage_path('app/temp/' . $fileName);

        if (file_exists($filePath)) {
            unlink($filePath); // Deletes the existing file
        }

        $pdf->save($filePath);

        // Retrieve the Facebook Webhook Call for the relevant order
        $FacebookWebhookCall = FacebookWebhookCall::whereIn('order_id', $Invoice->orders->pluck('id')->unique())->first();
        // dd($FacebookWebhookCall);
        if ($FacebookWebhookCall) {
            // Fetch Facebook page data if available
            $FBPage = UserFacebookPage::with('facebookAccount')
                ->where('page_id', $FacebookWebhookCall->page_id)
                ->where('bot_enabled', 1)
                ->first();
            if ($FBPage) {
                $t_invoice = str_replace('[Customer Name]', $Invoice->userCustomer->name, $FBPage->t_invoice);
                $euro1=$this->getCurrency();
                $euro=$euro1['EUR'];
                $t_invoice = str_replace('[Total Amount]', $Invoice->total_amount, $t_invoice);
                $t_invoice = str_replace('[Euro Amount]', $Invoice->total_amount * $euro, $t_invoice);
                $t_invoice = str_replace('[Invoice Number]', $Invoice->invoice_number, $t_invoice);
                // Initialize the Facebook instance
                $fbInstance = new Facebook();
                // Send the auto-reply message with the PDF attached
                $response = $fbInstance->sendAutoReplyMessagePDF(
                    $Invoice->userCustomer->facebook_id,
                    $t_invoice,
                    $FBPage->page_access_token,
                    $filePath
                );
                $responseData = json_decode($response->getContent(), true);
                if ($response->status() !== 200) {
                    session()->flash('error', $responseData['error'] ?? 'An unknown error occurred');
                } else {
                    session()->flash('success', $responseData['success']);
                }
            }
            else{
                session()->flash('error', 'This Invoice cannot be send due to no Facebook Page Automation');
            }
        }
        else{
            session()->flash('error', 'This Invoice cannot be send due to no Webhook');
        }
    }

    public function downloadInvoices()
    {
        $zipFileName = 'invoices_' . now()->format('Y_m_d_H_i_s') . '.zip';
        $zipPath = storage_path('app/' . $zipFileName);
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($this->selectedIds as $id) {
                $Invoice = Invoices::with('orders.orderDetails.product', 'userCustomer')
                    ->where('id', $id)
                    ->firstOrFail();

                $addressIds = $Invoice->orders->pluck('address_id')->unique();

                $Addresses = CustomerAddress::where('id', $Invoice->address_id)
                    ->get();
                
                $PaymentInfo = Setting::where('key','payment_info')->first();
                $euroRate = Setting::where('key','euro_rate')->first();
                    
                $invoiceData = [
                    'invoice' => $Invoice,
                    'addresses' => $Addresses,
                    'payment_info' => $PaymentInfo,
                    'euroRate' => $euroRate
                ];

                $pdf = Pdf::loadView('livewire.invoice', $invoiceData);
                $pdf->setPaper('A4', 'portrait');
                $pdf->setOption('margin-top', 0);
                $pdf->setOption('margin-right', 0);
                $pdf->setOption('margin-bottom', 0);
                $pdf->setOption('margin-left', 0);
                $pdf->setOption('defaultFont','DejaVu Sans');

                $tempDir = storage_path('app/temp');
                if (!file_exists($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                $fileName = $Invoice->userCustomer->name .' - '. $Invoice->invoice_number . '.pdf';
                $filePath = storage_path('app/temp/' . $fileName);

                // Save the PDF to a temporary location
                $pdf->save($filePath);

                // Add the file to the ZIP archive
                $zip->addFile($filePath, $fileName);
            }

            $zip->close();

            // Clean up temporary files
            Storage::deleteDirectory('temp');

            // Return the ZIP file as a download
            return response()->download($zipPath)->deleteFileAfterSend(true);
        } else {
            return response()->json(['error' => 'Unable to create ZIP file'], 500);
        }
    }

    public function generateInvoices()
    {
        $this->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($this->start_date)->startOfDay();
        $endDate = Carbon::parse($this->end_date)->endOfDay();

        $customers = Order::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', 'confirmed')
            ->whereNull('invoice_id')
            ->groupBy('user_customer_id')
            ->pluck('user_customer_id');

        foreach ($customers as $customer_id) {
            $orders = Order::where('user_customer_id', $customer_id)
                ->whereBetween('order_date', [$startDate, $endDate])
                ->where('status', 'confirmed')
                ->whereNull('invoice_id')
                ->get();

            $subtotal = $orders->sum('subtotal');
            $discount = $orders->sum('discount');
            $tax = $orders->sum('tax');
            $delivery_fee = $orders->sum('delivery_fee');
            $weight = $orders->map(function ($order) {
                return $order->orderDetails->sum(function ($detail) {
                    return $detail->weight * $detail->quantity;
                });
            })->sum();
            $dpdFee = 3.30;
            $total = $subtotal - $discount + $tax + $delivery_fee + $dpdFee;

            $invoice = Invoices::create([
                'invoice_number' => 'INV-' . $customer_id . '-' . substr(uniqid(), -6),
                'user_customer_id' => $customer_id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'weight' => $weight,
                'delivery_fee' => $delivery_fee,
                'dpd_fee' => $dpdFee,
                'total_amount' => $total,
                'notes' => 'Generated automatically for orders between ' . $startDate . ' and ' . $endDate,
            ]);

            foreach ($orders as $order) {
                $order->update([
                    'invoice_id' => $invoice->id,
                    'status' => 'completed'
                ]);
            }
        }

        session()->flash('success', 'Invoices generated successfully.');
        $this->emit('Invoicesgenerated');
    }

    public function selectAddress($addressId)
    {
        $this->selectedAddressId = $addressId;
        $this->getLockers();
    }

    public function getLockers()
    {
        if ($this->selectedAddressId)
        {
            $dpdService = new DpdService($this->shippingMethodId);
            $Addresses = CustomerAddress::find($this->selectedAddressId);
            try {
                $response = $dpdService->getLockers($Addresses->country->code);
                $this->pickups = $response;
            } catch (\Exception $e) {
                session()->flash('error', 'Failed to fetch Lockers: ' . $e->getMessage());
            }
        }
    }

    public function createShipment($id)
    {
        $Invoice = Invoices::find($id);
        if ($Invoice->address_id && $Invoice->pudoId) {
            $Address = CustomerAddress::find($Invoice->address_id);
            $dpdService = new DpdService($this->shippingMethodId);
            $data = [
                [
                    "id" => null,
                    "customerId" => null,
                    "parcels" => [
                        [
                            "count" => 0,
                            "weight" => 1,
                        ],
                    ],
                    "pallets" => [],
                    "senderAddress" => [
                        "name" => "Aija Loce",
                        "contactName" => "Aija Loce",
                        "email" => "wmfkwe@mfwekmf.com",
                        "phone" => "+37168888888",
                        "street" => "Uriekstes ielu 8a",
                        "city" => "Riga",
                        "postalCode" => "1005",
                        "country" => "LV",
                    ],
                    "receiverAddress" => [
                        "name" => $Address->name,
                        "contactName" => $Address->contact_name,
                        "email" => $Address->email,
                        "phone" => $Address->phone,
                        "city" => $Address->city->name,
                        "country" => $Address->country->code,
                        "pudoId" => $Invoice->pudoId,
                    ],
                    "returnAddress" => [
                        "name" => "Aija Loce",
                        "contactName" => "Aija Loce",
                        "email" => "wmfkwe@mfwekmf.com",
                        "phone" => "+37129810145",
                        "street" => "Uriekstes ielu 8a",
                        "city" => "Riga",
                        "postalCode" => "1005",
                        "country" => "LV",
                    ],
                    "shipmentFlags" =>[
                        "savesSenderAddress" => true,
                        "generatesDplPin" => true,
                    ],
                    "service" => [
                        "serviceAlias" => "DPD Pickup",
                        "serviceType" => "Pudo",
                    ],
                ],
            ];

            try {
                $response = $dpdService->createShipments($data);
            
                if ($response) {
                    $shipment_id  = $response['id'] ?? null;
                    $parcelNumber = $response['parcelNumbers'][0] ?? null;
            
                    if ($shipment_id && $Invoice) {
                        $Invoice->shipment_id = $shipment_id;
                        $Invoice->parcel_no = $parcelNumber;
                        $Invoice->save();
                    }
                    session()->flash('success', 'Shipment created successfully!');
                }
            } catch (\Exception $e) {
                session()->flash('error', 'Failed to create shipment: ' . $e->getMessage());
            }
        } else {
            session()->flash('error', 'Please select an address and a pickup location.');
        }
    }

    public function printLabel($id)
    {
        $Invoice = Invoices::find($id);

        if ($Invoice && $Invoice->parcel_no) {
            $data = [
                    "parcelNumbers" => [
                        $Invoice->parcel_no
                    ],
                    "offsetPosition" => null,
                    "downloadLabel"  => true,
                    "emailLabel"  => false,
                    "labelFormat" => "application/pdf",
                    "paperSize"   => "A4"
            ];

            try {
                $dpdService = new DpdService($this->shippingMethodId);
                $response = $dpdService->createShipmentLabel($data);

                if ($response && isset($response['pages'][0]['binaryData'])) {
                    $binaryData = $response['pages'][0]['binaryData'];
                    $base64String = str_replace('data:application/pdf;base64,', '', $binaryData);
                    $pdfContent = base64_decode($base64String);
                    return Response::make($pdfContent, 200, [
                        'Content-Type'        => 'application/pdf',
                        'Content-Disposition' => 'attachment; filename="' . $Invoice->parcel_no . ".pdf" . '"',
                        'Content-Length'      => strlen($pdfContent),
                    ]);
                }
                session()->flash('error', 'Label data not found in response.');
            } catch (\Exception $e) {
                session()->flash('error', 'Failed to print label: ' . $e->getMessage());
            }
        } else {
            session()->flash('error', 'Parcel number is missing for this invoice.');
        }

        return redirect()->back();
    }

    // public function editInvoice($id)
    // {
    //     $this->editInvoiceId   =  $id;
    //     $this->editInvoiceData = Invoices::with('orders.orderDetails.product','userCustomer')->findOrFail($id);
    //     $this->subTotal = $this->editInvoiceData->orders->reduce(function ($carry, $order) {
    //         return $carry + $order->orderDetails->sum('total_price');
    //     }, 0);
    //     $this->editselectedStatus = $this->editInvoiceData->status;
    //     $this->deliveryFee = (float) ($this->editInvoiceData->delivery_fee ?? 0);
    //     $this->dpdFee = (float) ($this->editInvoiceData->dpd_fee ?? 0);
    //     $this->customerAddresses = UserCustomers::find($this->editInvoiceData->userCustomer->id)->addresses;
    //     $this->selectedAddressId = $this->editInvoiceData->address_id;
    //     $this->shippingMethodId  = $this->editInvoiceData->shipping_method_id;
    //     if ($this->shippingMethodId == 1)
    //     {
    //         $this->getLockers();
    //         $this->selectedPickup    = $this->editInvoiceData->pudoId;
    //     }
    //     $this->addNewAddress = false;
    //     $this->grandTotal = $this->subTotal + $this->deliveryFee + $this->dpdFee;
    // }
    
    
   public function editInvoice($id)
{
    $this->editInvoiceId   =  $id;
    $this->editInvoiceData = Invoices::with('orders.orderDetails.product','userCustomer')->findOrFail($id);
    $this->subTotal = $this->editInvoiceData->orders->reduce(function ($carry, $order) {
        return $carry + $order->orderDetails->sum('total_price');
    }, 0);
    $this->editselectedStatus = $this->editInvoiceData->status;
    $this->deliveryFee = (float) ($this->editInvoiceData->delivery_fee ?? 0);
    $this->dpdFee = (float) ($this->editInvoiceData->dpd_fee ?? 0);
    $this->customerAddresses = UserCustomers::find($this->editInvoiceData->userCustomer->id)->addresses;
    $this->selectedAddressId = $this->editInvoiceData->address_id;
    $this->shippingMethodId  = $this->editInvoiceData->shipping_method_id;
    
    // Initialize editable order dates
    foreach ($this->editInvoiceData->orders as $order) {
        $this->editableOrderDates[$order->id] = $order->order_date->format('Y-m-d');
    }
    
    if ($this->shippingMethodId == 1)
    {
        $this->getLockers();
        $this->selectedPickup    = $this->editInvoiceData->pudoId;
    }
    $this->addNewAddress = false;
    $this->grandTotal = $this->subTotal + $this->deliveryFee + $this->dpdFee;
    
    // Dispatch event to initialize datepickers after data is loaded
    $this->dispatchBrowserEvent('invoice-data-loaded');
}

public function updateOrderDate($orderId, $newDate)
{
    try {
        $order = Order::findOrFail($orderId);
        $order->order_date = $newDate;
        $order->save();
        
        // Update the local array
        $this->editableOrderDates[$orderId] = $newDate;
        
        // Refresh the invoice data
        $this->editInvoiceData = Invoices::with('orders.orderDetails.product','userCustomer')
            ->findOrFail($this->editInvoiceId);
        
        session()->flash('success', 'Order date updated successfully.');
        
        // Re-initialize datepickers after update
        $this->dispatchBrowserEvent('invoice-data-loaded');
    } catch (\Exception $e) {
        session()->flash('error', 'Error updating order date: ' . $e->getMessage());
    }
}

    public function getInvocies()
    {
        return Invoices::with(['userCustomer', 'orders'])
            ->when($this->searchText, function ($query) {
                $query->where(function ($q) {
                    $q->where('id', 'like', '%' . $this->searchText . '%')
                    ->orWhere('invoice_number', 'like', '%' . $this->searchText . '%')
                    ->orWhere('total_amount', 'like', '%' . $this->searchText . '%')
                    ->orWhereHas('userCustomer', function ($customerQuery) {
                        $customerQuery->where('name', 'like', '%' . $this->searchText . '%')
                            ->orWhere('email', 'like', '%' . $this->searchText . '%')
                            ->orWhere('phone', 'like', '%' . $this->searchText . '%');
                    });
                });
            })
            ->when($this->selectedStatus, function ($query) {
                    $query->where('status', $this->selectedStatus);
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

    public function getTotalProperty()
    {
        if ($this->shippingMethodId == 1)
        {
            return number_format(
                (float) ($this->subTotal ?? 0) +
                (float) ($this->deliveryFee ?? 0) +
                (float) ($this->dpdFee ?? 0),
                2
            );
        }
        else{
            return number_format(
                (float) ($this->subTotal ?? 0) +
                (float) ($this->deliveryFee ?? 0),
                2
            );
        }
    }

    public function updateInvoice()
    {
        if ($this->shippingMethodId == 1)
        {
            $this->validate([
                'deliveryFee' => 'required|numeric|min:0|max:9999.99',
                'dpdFee' => 'required|numeric|min:0|max:9999.99',
                'editselectedStatus' => 'required|in:pending,paid',
                'selectedPickup' => 'required',
            ]);
        }
        else{
            $this->validate([
                'deliveryFee' => 'required|numeric|min:0|max:9999.99',
                'editselectedStatus' => 'required|in:pending,paid',
            ]);
        }

        $invoice = Invoices::find($this->editInvoiceId);
       

        if (!$invoice) {
            session()->flash('error', 'Invoice not found.');
            return;
        }

        try {
            $invoice->delivery_fee = $this->deliveryFee;
            $invoice->dpd_fee = $this->dpdFee;
            $invoice->total_amount = $this->Total;
            $invoice->status = $this->editselectedStatus;
            $invoice->address_id = $this->selectedAddressId;
            $invoice->shipping_method_id = $this->shippingMethodId;
            $invoice->pudoId = $this->selectedPickup;
            $invoice->save();
            
            $customer_id=$invoice->user_customer_id;
            $user_customer=UserCustomers::findOrFail($customer_id);
            $facebook_id=$user_customer->facebook_id;
            $facebookWebHookCall=FacebookWebhookCall::where('cus_fb_id',$facebook_id)->firstOrFail();
            $order=Order::where('user_customer_id',$customer_id)->where('status','!=','pending')->firstOrFail();
            $facebookWebHookCall->order_id=$order->id;
            $facebookWebHookCall->save();

            Order::where('invoice_id', $this->editInvoiceId)->update([
                'shipping_method_id' => $this->shippingMethodId,
            ]);
            session()->flash('success', 'Invoice updated successfully.');
            $this->emit('InvoiceUpdated');
        } catch (\Exception $e) {
            session()->flash('error', 'Error updating invoice: ' . $e->getMessage());
        }

        $this->reset(['deliveryFee', 'dpdFee', 'editselectedStatus','selectedPickup','selectedAddressId']);
    }

    
    public function render()
    {
        $this->countries = Country::all();
        return view('livewire.invoices', [
            'Invoices' => $this->getInvocies(),
        ]);
    }
}
