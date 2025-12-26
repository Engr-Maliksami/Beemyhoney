<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Product;
use App\Models\UserCustomers;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use App\Models\CustomerAddress;
use App\Models\Invoice;
use App\Models\Setting;
use Livewire\WithPagination;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use Illuminate\Support\Facades\Cache;

class Orders extends Component
{
    use WithPagination;
    use WithFileUploads;

    // Lazy update mode for better performance
    // protected $updateMode = 'lazy';
    public $customerId;
    public $editOrderId;
    public $deleteOrderId = null;
    public $selectedIds = [];
    public $selectAll = false;
    public $selectedStatus = "";
    public $selectedSource = "";
    public $searchText = "";
    public $selectedDateRange;
    public $bulkActionStatus;
    public $selectedOrders = [];

    public $existingCustomerId;
    public $multipleCustomerId = [];
    public $existingProductId;
    public $addNewCustomer = false;
    public $addNewProduct = false;
    public $newProducts = [];

    public $existingProducts = [];

    public $products = [];
    public $availableProducts = [];

    public $activeTab = 'customer';

    protected $listeners = [
        'updatedExistingCustomerId',
        'updatedExistingProductId',
        'batchUpdateProducts',
        'addOrder',
        'showProductData'
    ];

    // Defer loading and updating for better performance
    protected $deferLoading = true;


    // Customer fields
    public $customer_name, $customer_email, $customer_address;

    // Product fields
    public $product_name, $product_price, $product_weight, $product_quantity, $sku;

    // Order fields
    public $quantity, $order_image;
    //flat rate for 3kg
    public $deliveryFee = 0;

    public $order_discount = 0, $order_del_fee = 0, $order_tax = 0;

    public $selectedOrderStatus = '', $notes = '', $OrderStatus = '';

    public $shippingMethodId = 1;


    public function mount($customerId = null)
    {
        $deliveryFee = Setting::where('key', 'delivery_rate')->first();
        $this->deliveryFee = $deliveryFee?->value;
        $this->order_date = now()->format('d-m-Y');
        $this->customerId = $customerId;
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function hydrate()
    {
        $this->emit('updateexistingproductcomp');
    }

    public function updatedExistingCustomerId($id)
    {
        $this->multipleCustomerId = $id;
    }

    public function addProduct()
    {
        $this->newProducts[] = ['name' => '', 'price' => '', 'quantity' => '', 'weight' => 0];
    }

    public function removeProduct($index)
    {
        unset($this->newProducts[$index]);
        $this->newProducts = array_values($this->newProducts);
    }

    public function addExistingProduct()
    {
        $this->existingProducts[] = [
            'id' => null,
            'price' => null,
            'quantity' => 1,
            'weight' => 0
        ];
        $this->updateAvailableProducts();
    }

    public function removeExistingProduct($index)
    {
        unset($this->existingProducts[$index]);
        $this->existingProducts = array_values($this->existingProducts);
        $this->updateAvailableProducts();
    }

    private function updateAvailableProducts()
    {
        $selectedProductIds = array_filter(array_column($this->existingProducts, 'id'));
        $this->availableProducts = array_filter($this->products, function ($product) use ($selectedProductIds) {
            return !in_array($product['id'], $selectedProductIds);
        });
    }

    public function updatedExistingProducts($value, $name)
    {
        if (str_contains($name, 'id')) {
            $index = explode('.', $name)[0];
            $product = Product::find($value);
            if ($product) {
                $this->existingProducts[$index]['id'] = $product->id;

                $this->existingProducts[$index]['price'] = $product->price;
                $this->existingProducts[$index]['quantity'] = (true) ? 1 : $product->stock_quantity;
                $this->existingProducts[$index]['weight'] = $product->weight;
            }
        }
    }

    public function updatedExistingProductId($index, $productId)
    {
        $product = Product::find($productId);
        if ($product) {
            $this->existingProducts[$index]['id']    = $productId;
            $this->existingProducts[$index]['price'] = $product->price;
            $this->existingProducts[$index]['quantity'] = 1;
            $this->existingProducts[$index]['weight'] = $product->weight;
        }
    }

    /**
     * Handle batch updates for products to minimize network requests
     */
    public function batchUpdateProducts($updates)
    {
        if (empty($updates)) {
            return;
        }

        // Get all product IDs at once to reduce DB queries
        $productIds = collect($updates)->pluck('productId')->toArray();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($updates as $update) {
            $index = $update['index'];
            $productId = $update['productId'];

            if (isset($products[$productId])) {
                $product = $products[$productId];
                $this->existingProducts[$index]['id'] = $productId;
                $this->existingProducts[$index]['price'] = $product->price;
                $this->existingProducts[$index]['quantity'] = 1;
                $this->existingProducts[$index]['weight'] = $product->weight;
            }
        }
    }

    public function editOrder($id)
    {
        $this->editOrderId =  $id;
        $order = Order::with('userCustomer', 'orderDetails', 'shippingMethod')->findOrFail($id);
        $this->existingCustomerId = $order->user_customer_id;
        $this->selectedOrderStatus = $order->status;
        $this->OrderStatus = $order->status;
        $this->notes             = $order->notes;
        $this->order_date = Carbon::parse($order->order_date)->format('d-m-Y');
        $this->existingProducts = [];
        foreach ($order->orderDetails as $details) {
            $this->existingProducts[] = [
                'id' => $details->product_id,
                'price' => $details->unit_price,
                'weight' => $details->weight,
                'quantity' => $details->quantity
            ];
        }
        $this->updateAvailableProducts();
    }
    public function updateOrderStatus($status)
    {
        Order::where('id', $this->editOrderId)
            ->update(['status' => $status]);
    }

    public function updateOrder()
    {
        $rules = [];
        $messages = [];
        $rules['order_date'] = 'required|date_format:d-m-Y';
        if (count($this->newProducts) > 0) {
            $rules['newProducts.*.name'] = 'required|string|max:255';
            $rules['newProducts.*.price'] = 'required|numeric|min:0';
            $rules['newProducts.*.quantity'] = 'required|integer|min:1';
            $rules['newProducts.*.weight'] = 'required|numeric|min:0';
            $messages['newProducts.*.name.required'] = 'The product name is required.';
            $messages['newProducts.*.name.string'] = 'The product name must be a valid string.';
            $messages['newProducts.*.name.max'] = 'The product name may not be greater than 255 characters.';
            $messages['newProducts.*.price.required'] = 'The product price is required.';
            $messages['newProducts.*.price.numeric'] = 'The product price must be a number.';
            $messages['newProducts.*.price.min'] = 'The product price must be at least 0.';
            $messages['newProducts.*.quantity.required'] = 'The product quantity is required.';
            $messages['newProducts.*.quantity.integer'] = 'The product quantity must be an integer.';
            $messages['newProducts.*.quantity.min'] = 'The product quantity must be at least 1.';
            $messages['newProducts.*.weight.required'] = 'The product weight is required.';
            $messages['newProducts.*.weight.numeric'] = 'The product weight must be a number.';
            $messages['newProducts.*.weight.min'] = 'The product weight must be at least 0.';
        }

        if (count($this->existingProducts) > 0) {
            $rules['existingProducts.*.id'] = 'required|exists:products,id';
            $rules['existingProducts.*.price'] = 'required|numeric|min:0';
            $rules['existingProducts.*.quantity'] = 'required|integer|min:1';

            $messages['existingProducts.*.id.required'] = 'Please select a product.';
            $messages['existingProducts.*.id.exists'] = 'The selected product does not exist.';
            $messages['existingProducts.*.price.required'] = 'The price for the selected product is required.';
            $messages['existingProducts.*.price.numeric'] = 'The price for the selected product must be a number.';
            $messages['existingProducts.*.price.min'] = 'The price for the selected product must be at least 0.';
            $messages['existingProducts.*.quantity.required'] = 'The quantity for the selected product is required.';
            $messages['existingProducts.*.quantity.integer'] = 'The quantity for the selected product must be an integer.';
            $messages['existingProducts.*.quantity.min'] = 'The quantity for the selected product must be at least 1.';
        }
        if (count($rules) > 0) {
            $this->validate($rules, $messages);
        }

        if (count($this->newProducts) === 0 && count($this->existingProducts) === 0) {
            $this->addError('products', 'You must add at least one product (new or existing).');
            return;
        }

        if ($this->editOrderId) {
            $order = Order::with('userCustomer', 'orderDetails', 'shippingMethod')->findOrFail($this->editOrderId);
            $totalPrice = 0;
            $totalWeight = 0;
            foreach ($order->orderDetails as $orderDetail) {
                $product = Product::find($orderDetail->product_id);
                if ($product) {
                    $product->increment('stock_quantity', $orderDetail->quantity);
                }
            }
            $order->orderDetails()->delete();
            if (count($this->newProducts) > 0) {
                foreach ($this->newProducts as $productData) {
                    $product = Product::create([
                        'name'           => $productData['name'],
                        'price'          => $productData['price'],
                        'stock_quantity' => $productData['quantity'],
                        'weight'         => $productData['weight'],
                    ]);

                    OrderDetail::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit_price' => $productData['price'],
                        'quantity' => $productData['quantity'],
                        'weight' => $productData['weight'],
                        'total_price' => $productData['price'] * $productData['quantity'],
                    ]);

                    // Update the total price
                    $totalPrice += $productData['price'] * $productData['quantity'];
                    $totalWeight += $productData['weight'] * $productData['quantity'];
                    $min = min($product->stock_quantity, $productData['quantity']);
                    $product->decrement('stock_quantity', $min);
                }
            }

            if (count($this->existingProducts) > 0) {
                foreach ($this->existingProducts as $productData) {
                    $product = Product::find($productData['id']);

                    OrderDetail::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit_price' => $productData['price'],
                        'quantity' => $productData['quantity'],
                        'weight' => $productData['weight'],
                        'total_price' => $productData['price'] * $productData['quantity'],
                    ]);

                    $totalPrice += $productData['price'] * $productData['quantity'];
                    $totalWeight += $productData['weight'] * $productData['quantity'];
                    $min = min($product->stock_quantity, $productData['quantity']);
                    $product->decrement('stock_quantity', $min);
                }
            }

            $deliveryFee = round($totalWeight * $this->deliveryFee);

            $updateData = [
                'total_amount' => $totalPrice,
                'subtotal' => $totalPrice,
                // 'delivery_fee' => $this->order_del_fee,
                'tax' => $this->order_tax,
                'discount' => $this->order_discount,
                'status' => $this->selectedOrderStatus,
                'notes' => $this->notes,
                'delivery_fee' => $deliveryFee,
                'order_date' => Carbon::createFromFormat('d-m-Y', $this->order_date)->format('Y-m-d H:i:s')
            ];

            if ($this->selectedOrderStatus == 'shipped') {
                $updateData['shipped_at'] =  now();
            }

            if ($this->selectedOrderStatus == 'delivered') {
                $updateData['delivered_at'] = now();
            }

            $order->update($updateData);
            $this->activeTab = 'customer';
            $this->reset([
                'addNewCustomer',
                'customer_name',
                'customer_email',
                'existingCustomerId',
                'addNewProduct',
                'newProducts',
                'existingProducts',
                'notes',
                'selectedOrderStatus'
            ]);

            session()->flash('success', 'Order has been successfully updated.');
            $this->emit('orderUpdated');
        }
    }

    public function confirmDelete($id)
    {
        $this->deleteOrderId = $id;
        $this->dispatchBrowserEvent('swal:confirm', [
            'title' => 'Are you sure?',
            'text' => 'This will permanently delete the order.',
            'type' => 'warning',
            'function' => 'order',
            'showCancelButton' => true,
            'confirmButtonText' => 'Yes, delete it!',
            'cancelButtonText' => 'No, keep it',
        ]);
    }

    public function deleteOrder()
    {
        if ($this->deleteOrderId) {
            $order = Order::find($this->deleteOrderId);

            if ($order) {
                foreach ($order->orderDetails as $orderDetail) {
                    $product = Product::find($orderDetail->product_id);
                    if ($product) {
                        $product->increment('stock_quantity', $orderDetail->quantity);
                    }
                }

                $order->orderDetails()->delete();
                $order->delete();
                session()->flash('success', 'Order deleted and products restocked successfully.');
                $this->reset(['deleteOrderId']);
            } else {
                session()->flash('error', 'Order not found.');
            }
        }
    }

    public function resetFields()
    {
        $this->activeTab = 'customer';
        $this->multipleCustomerId = [];
        $this->reset([
            'addNewCustomer',
            'customer_name',
            'customer_email',
            'multipleCustomerId',
            'existingCustomerId',
            'addNewProduct',
            'newProducts',
            'existingProducts'
        ]);
    }

    /**
     * Get orders with optimized eager loading and caching for better performance
     */
    public function getOrders()
    {
        static $cachedOrders = null;
        static $lastParams = null;

        // Create a hash of search parameters
        $currentParams = md5(json_encode([
            'search' => $this->searchText,
            'status' => $this->selectedStatus,
            'source' => $this->selectedSource,
            'dateRange' => $this->selectedDateRange,
            'page' => request()->query('page', 1)
        ]));

        // Return cached result if parameters haven't changed
        if ($cachedOrders !== null && $lastParams === $currentParams) {
            return $cachedOrders;
        }

        $lastParams = $currentParams;

        // Query with optimized eager loading - only select needed columns
        $query = Order::select(['id', 'order_date', 'user_customer_id', 'status', 'source', 'shipping_method_id', 'invoice_id', 'total_amount'])
            ->with([
                'userCustomer:id,name,email,phone',
                'orderDetails:id,order_id,product_id,quantity',
                'shippingMethod:id,name'
            ]);

        // Apply filters
        if ($this->searchText) {
            $query->where(function ($q) {
                $q->where('id', 'like', '%' . $this->searchText . '%')
                    ->orWhere('total_amount', 'like', '%' . $this->searchText . '%')
                    ->orWhereHas('userCustomer', function ($customerQuery) {
                        $customerQuery->where('name', 'like', '%' . $this->searchText . '%')
                            ->orWhere('email', 'like', '%' . $this->searchText . '%')
                            ->orWhere('phone', 'like', '%' . $this->searchText . '%');
                    });
            });
        }

        if ($this->selectedStatus) {
            $query->where('status', $this->selectedStatus);
        }

        if ($this->selectedSource) {
            $query->where('source', $this->selectedSource);
        }

        if ($this->selectedDateRange) {
            $dates = explode(' to ', $this->selectedDateRange);
            if (count($dates) === 2) {
                $query->whereBetween('order_date', [$dates[0], $dates[1]]);
            } elseif (count($dates) === 1) {
                $query->whereDate('order_date', $dates[0]);
            }
        }
        if ($this->customerId) {
            $query->where('user_customer_id', $this->customerId);
        }
        // Use indexes efficiently by ordering by id instead of created_at if possible
        $cachedOrders = $query->orderBy('id', 'desc')->paginate(100);

        return $cachedOrders;
    }

    public function addOrder($data)
    {
        $this->existingProducts = $data['existing_products'];
        $this->newProducts = $data['new_products'];
        $this->multipleCustomerId = $data['existing_customers'] ?? [];
        $newCustomer = $data['new_customers'];
        $this->order_date = $data['order_date'];
        if (!empty($newCustomer)) {
            foreach ($newCustomer as $nCustomer) {
                $customer = UserCustomers::create([
                    'name'    => $nCustomer['name'],
                    'email'   => $nCustomer['email'],
                    'source'  => 'manual'
                ]);
                $this->multipleCustomerId[] = $customer->id;
            }
        }


        foreach ($this->multipleCustomerId as $existingCustomerId) {

            $order = Order::create([
                'user_customer_id' => $existingCustomerId,
                'status' => 'pending',
                'subtotal' => 0,
                'total_amount' => 0,
                'order_number' => Str::uuid()->toString(),
                'order_date' => Carbon::createFromFormat('d-m-Y', $this->order_date)->format('Y-m-d H:i:s'),
            ]);

            $totalPrice = 0;
            $totalWeight = 0;

            if (count($this->newProducts) > 0) {
                foreach ($this->newProducts as $productData) {
                    $product = Product::create([
                        'name'           => $productData['name'],
                        'price'          => $productData['price'],
                        'stock_quantity' => $productData['quantity'],
                        'weight'         => $productData['weight'],
                    ]);

                    OrderDetail::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit_price' => $productData['price'],
                        'quantity' => $productData['quantity'],
                        'weight' => $productData['weight'],
                        'total_price' => $productData['price'] * $productData['quantity'],
                    ]);

                    // Update the total price
                    $totalPrice += $productData['price'] * $productData['quantity'];
                    $totalWeight += $productData['weight'] * $productData['quantity'];
                    $min = min($product->stock_quantity, $productData['quantity']);
                    $product->decrement('stock_quantity', $min);
                }
            }

            if (count($this->existingProducts) > 0) {
                foreach ($this->existingProducts as $productData) {
                    $product = Product::find($productData['product_id']);

                    OrderDetail::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit_price' => $productData['price'],
                        'quantity' => $productData['quantity'],
                        'weight' => $productData['weight'],
                        'total_price' => $productData['price'] * $productData['quantity'],
                    ]);

                    $totalPrice += $productData['price'] * $productData['quantity'];
                    $totalWeight += $productData['weight'] * $productData['quantity'];
                    $min = min($product->stock_quantity, $productData['quantity']);
                    $product->decrement('stock_quantity', $min);
                }
            }

            $deliveryFee = round($totalWeight * $this->deliveryFee);

            $order->update([
                'total_amount' => $totalPrice,
                'subtotal' => $totalPrice,
                'delivery_fee' => $deliveryFee
            ]);
        }
        $this->activeTab = 'customer';
        $this->multipleCustomerId = [];
        $this->reset([
            'multipleCustomerId',
            'addNewCustomer',
            'customer_name',
            'customer_email',
            'existingCustomerId',
            'addNewProduct',
            'newProducts',
            'existingProducts'
        ]);

        session()->flash('success', 'Order has been successfully added.');
        $this->emit('orderAdded');
    }

    public function generateInvoice($id)
    {
        $Invoice = Invoice::with('orders.orderDetails.product', 'userCustomer')
            ->where('id', $id)
            ->firstOrFail();

        $Addresses = CustomerAddress::where('id', $Invoice->address_id)
            ->get();

        $PaymentInfo = Setting::where('key', 'payment_info')->first();
        $euroRate = Setting::where('key', 'euro_rate')->first();

        $invoiceData = [
            'invoice' => $Invoice,
            'addresses' => $Addresses,
            'payment_info' => $PaymentInfo,
            'euroRate' => $euroRate
        ];

        //return view('livewire.invoice', $invoiceData);

        $pdf = Pdf::loadView('livewire.invoice', $invoiceData);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOption('margin-top', 0);
        $pdf->setOption('margin-right', 0);
        $pdf->setOption('margin-bottom', 0);
        $pdf->setOption('margin-left', 0);
        $pdf->setOption('defaultFont', 'DejaVu Sans');
        return $pdf->download($Invoice->userCustomer->name . ' - ' . $Invoice->invoice_number . '.pdf');
    }

    public function createInvoice($order_id)
    {
        $orders = Order::where('id', $order_id)->get();
        $subtotal = $orders->sum('subtotal');
        $discount = $orders->sum('discount');
        $tax = $orders->sum('tax');
        $delivery_fee = $orders->sum('delivery_fee');
        $weight = $orders->map(function ($order) {
            return $order->orderDetails->sum('weight');
        })->sum();
        $total = $subtotal - $discount + $tax + $delivery_fee;

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        foreach ($orders as $order) {
            $invoice = Invoice::create([
                'invoice_number' => 'INV-' . $order->user_customer_id . '-' . substr(uniqid(), -6),
                'user_customer_id' => $order->user_customer_id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'weight' => $weight,
                'delivery_fee' => $delivery_fee,
                'total_amount' => $total,
                'notes' => 'Generated automatically for orders between ' . $startDate . ' and ' . $endDate,
            ]);

            $order->update([
                'invoice_id' => $invoice->id,
                'shipping_method_id' => $this->shippingMethodId,
                'status' => 'completed'
            ]);
        }
        session()->flash('success', 'Invoices generated successfully.');
    }

    /**
     * Cache the product totals to prevent recalculation on every property access
     * Using memoization pattern for better performance
     */
    public function getProductTotalsProperty()
    {
        static $cachedTotals = null;
        static $lastExistingProducts = null;
        static $lastNewProducts = null;

        // Only recalculate if the products have changed
        $currentExistingProductsHash = md5(json_encode($this->existingProducts));
        $currentNewProductsHash = md5(json_encode($this->newProducts));

        if (
            $cachedTotals !== null &&
            $lastExistingProducts === $currentExistingProductsHash &&
            $lastNewProducts === $currentNewProductsHash
        ) {
            return $cachedTotals;
        }

        // Update calculation only when needed
        $lastExistingProducts = $currentExistingProductsHash;
        $lastNewProducts = $currentNewProductsHash;

        $cachedTotals = collect($this->existingProducts)->map(function ($product) {
            $price = is_numeric($product['price'] ?? 0) ? (float)$product['price'] : 0;
            $quantity = is_numeric($product['quantity'] ?? 0) ? (int)$product['quantity'] : 0;
            $weight = is_numeric($product['weight'] ?? 0) ? (float)$product['weight'] : 0;
            $totalPrice = $price * $quantity;
            $totalWeight = $weight * $quantity;
            return array_merge($product, [
                'total_price' => $totalPrice,
                'total_weight' => $totalWeight
            ]);
        })->concat(collect($this->newProducts)->map(function ($product) {
            $price = is_numeric($product['price'] ?? 0) ? (float)$product['price'] : 0;
            $quantity = is_numeric($product['quantity'] ?? 0) ? (int)$product['quantity'] : 0;
            $weight = is_numeric($product['weight'] ?? 0) ? (float)$product['weight'] : 0;
            $totalPrice = $price * $quantity;
            $totalWeight = $weight * $quantity;
            return array_merge($product, [
                'total_price' => $totalPrice,
                'total_weight' => $totalWeight
            ]);
        }));

        return $cachedTotals;
    }

    public function getGrandTotalProperty()
    {
        static $cachedTotal = null;
        static $lastProductTotals = null;

        $currentProductTotalsHash = md5(json_encode($this->productTotals));

        if ($cachedTotal !== null && $lastProductTotals === $currentProductTotalsHash) {
            return $cachedTotal;
        }

        $lastProductTotals = $currentProductTotalsHash;
        $cachedTotal = $this->productTotals->sum('total_price');

        return $cachedTotal;
    }

    public function getTotalWeightProperty()
    {
        static $cachedWeight = null;
        static $lastProductTotals = null;

        $currentProductTotalsHash = md5(json_encode($this->productTotals));

        if ($cachedWeight !== null && $lastProductTotals === $currentProductTotalsHash) {
            return $cachedWeight;
        }

        $lastProductTotals = $currentProductTotalsHash;
        $cachedWeight = $this->productTotals->sum('total_weight');

        return $cachedWeight;
    }
    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedOrders = Order::pluck('id')->toArray();
        } else {
            $this->selectedOrders = [];
        }
    }
    public function applyBulkAction()
    {
        if ($this->bulkActionStatus && !empty($this->selectedOrders)) {
            Order::whereIn('id', $this->selectedOrders)->update(['status' => $this->bulkActionStatus]);
            session()->flash('success', 'Selected Orders status has been successfully updated.');
            $this->reset(['selectedOrders', 'selectAll', 'bulkActionStatus']);
        } elseif ($this->bulkActionStatus == "" && !empty($this->selectedOrders)) {
            session()->flash('error', 'Please Select Status to Update for Selected Orders.');
        } elseif ($this->bulkActionStatus == "" && empty($this->selectedOrders)) {
            session()->flash('error', 'Please Select Status and Orders to Update.');
        } else {
            session()->flash('error', 'Please Select Orders to Update Status.');
        }
    }
    public function downloadAllOrders()
    {
        $orders = Order::with(['orderDetails', 'userCustomer'])->get()->map(function ($order) {
            return [
                'Order ID'       => $order->id,
                'Order Date'     => $order->order_date ? $order->order_date->format('d-m-Y H:i:s') : 'N/A',
                'Customer Name'  => optional($order->userCustomer)->name ?? 'Guest',
                'Total Products' => $order->orderDetails->sum('quantity'),
                'Total Amount'   => $order->total_amount,
                'Status'         => $order->status,
                'Source'         => $order->source,
            ];
        })->toArray();

        if (empty($orders)) {
            return back()->with('error', 'No orders found.');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Add column headers
        $sheet->fromArray(array_keys($orders[0]), NULL, 'A1');

        // Add order data
        $sheet->fromArray($orders, NULL, 'A2');

        $timestamp = Carbon::now()->format('d-m-Y_H-i-s');
        $filename = "allOrders_{$timestamp}.xlsx";
        $filePath = storage_path("app/public/{$filename}");

        // Ensure directory exists
        Storage::makeDirectory('public');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function showProductData($batch_number)
    {
        $product = Product::where('batch_number', $batch_number)->first();

        if (!$product) {
            $this->dispatchBrowserEvent('product-error', ['message' => 'Product not found']);
            return;
        }

        $this->dispatchBrowserEvent('product-scanned', [
            'name'     => $product->name,
            'price'    => $product->price,
            'weight'   => $product->weight,
            'quantity' => $product->quantity_box,
            'sku'      => $product->sku,
        ]);
    }
    public function render()
    {
        // Use static property to cache products and customers that don't change frequently
        static $cachedProducts = null;
        static $cachedCustomers = null;
        // Cache::forget('products_by_id');

        $cachedProducts = Cache::remember('products_by_id', now()->addMinutes(30), function () {
            return Product::select('id', 'name', 'price','stock_quantity')->get()->keyBy('id')->toArray();
        });
        $this->products = $cachedProducts;

        // Lazy load countries only when needed
        if (!isset($this->countries)) {
            $this->countries = Country::all();
        }

        // Lazy load or use cached customers
        // if ($cachedCustomers === null) {
            $cachedCustomers = UserCustomers::all();
        // } 

        return view('livewire.orders', [
            'Orders' => $this->getOrders(),
            'customers' => $cachedCustomers,
            'products' => $this->products,
        ]);
    }
}
