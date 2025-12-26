<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display orders listing
     */
    public function index(Request $request, $customerId = null)
    {
        $deliveryFee = Setting::where('key', 'delivery_rate')->first();
        $customers = UserCustomers::all();
        // $products = Product::select('id', 'name', 'price', 'stock_quantity')->toArray(); // 50 products per page
            // $products = Product::select('id', 'name', 'price', 'stock_quantity')->where('stock_quantity','>',0)->get()->keyBy('id')->toArray();
            $products = Product::select('id', 'name', 'price', 'stock_quantity')->get()->keyBy('id')->toArray();
            // dd(count($products));
            // Optionally convert the current page results to array (if you need it)
            // $productsArray = $products->getCollection()->keyBy('id')->toArray();

        $data = [
            'deliveryFee' => $deliveryFee?->value ?? 0,
            'order_date' => now()->format('d-m-Y'),
            'customerId' => $customerId,
            'customers' => $customers,
            'products' => $products,
        ];

        return view('orders.index', $data);
    }
    
    public function ajaxList(Request $request)
    {
        $search = $request->query('search');
        $page = $request->query('page', 1);
        $limit = 10;
    
        $query = Product::select('id', 'name', 'price', 'stock_quantity');
    
        if (!empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }
    
        $products = $query->orderBy('id')
            ->paginate($limit, ['*'], 'page', $page);
    
        return response()->json([
            'data' => $products->items(),
            'hasMore' => $products->hasMorePages(),
        ]);
    }

    /**
     * Get orders with filters (AJAX)
     */
    public function getOrders(Request $request)
    {
        $query = Order::select(['id', 'order_date', 'user_customer_id', 'status', 'source', 'shipping_method_id', 'invoice_id', 'total_amount'])
            ->with([
                'userCustomer:id,name,email,phone',
                'orderDetails:id,order_id,product_id,quantity',
                'shippingMethod:id,name'
            ]);

        // Apply filters
        if ($request->filled('searchText')) {
            $searchText = $request->searchText;
            $query->where(function ($q) use ($searchText) {
                $q->where('id', 'like', '%' . $searchText . '%')
                    ->orWhere('total_amount', 'like', '%' . $searchText . '%')
                    ->orWhereHas('userCustomer', function ($customerQuery) use ($searchText) {
                        $customerQuery->where('name', 'like', '%' . $searchText . '%')
                            ->orWhere('email', 'like', '%' . $searchText . '%')
                            ->orWhere('phone', 'like', '%' . $searchText . '%');
                    });
            });
        }

        if ($request->filled('selectedStatus')) {
            $query->where('status', $request->selectedStatus);
        }

        if ($request->filled('selectedSource')) {
            $query->where('source', $request->selectedSource);
        }

        if ($request->filled('selectedDateRange')) {
            $dates = explode(' to ', $request->selectedDateRange);
            if (count($dates) === 2) {
                $query->whereBetween('order_date', [$dates[0], $dates[1]]);
            } elseif (count($dates) === 1) {
                $query->whereDate('order_date', $dates[0]);
            }
        }

        if ($request->filled('customerId')) {
            $query->where('user_customer_id', $request->customerId);
        }

        $orders = $query->orderBy('id', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'orders' => $orders
        ]);
    }

    /**
     * Get order details for editing (AJAX)
     */
    public function edit($id)
    {
        $order = Order::with('userCustomer', 'orderDetails', 'shippingMethod')->findOrFail($id);

        $existingProducts = [];
        foreach ($order->orderDetails as $details) {
            $existingProducts[] = [
                'id' => $details->product_id,
                'price' => $details->unit_price,
                'weight' => $details->weight,
                'quantity' => $details->quantity
            ];
        }

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'user_customer_id' => $order->user_customer_id,
                'status' => $order->status,
                'notes' => $order->notes,
                'order_date' => Carbon::parse($order->order_date)->format('d-m-Y'),
                'existingProducts' => $existingProducts
            ]
        ]);
    }

    /**
     * Update order status (AJAX)
     */
    public function updateStatus(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'status' => 'required|in:pending,confirmed,completed,cancelled,shipped,delivered'
        ]);

        Order::where('id', $request->order_id)->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully.'
        ]);
    }

    /**
     * Update order (AJAX)
     */
    public function update(Request $request, $id)
    {
        $rules = [
            'order_date' => 'required|date_format:d-m-Y',
        ];
        $messages = [];

        if ($request->has('newProducts') && count($request->newProducts) > 0) {
            $rules['newProducts.*.name'] = 'required|string|max:255';
            $rules['newProducts.*.price'] = 'required|numeric|min:0';
            $rules['newProducts.*.quantity'] = 'required|integer|min:1';
            $rules['newProducts.*.weight'] = 'required|numeric|min:0';
        }

        if ($request->has('existingProducts') && count($request->existingProducts) > 0) {
            $rules['existingProducts.*.id'] = 'required|exists:products,id';
            $rules['existingProducts.*.price'] = 'required|numeric|min:0';
            $rules['existingProducts.*.quantity'] = 'required|integer|min:1';
        }

        $request->validate($rules, $messages);

        if (!$request->has('newProducts') && !$request->has('existingProducts')) {
            return response()->json([
                'success' => false,
                'message' => 'You must add at least one product (new or existing).'
            ], 422);
        }

        $order = Order::with('userCustomer', 'orderDetails', 'shippingMethod')->findOrFail($id);
        $totalPrice = 0;
        $totalWeight = 0;

        // Restore stock for existing order details
        foreach ($order->orderDetails as $orderDetail) {
            $product = Product::find($orderDetail->product_id);
            if ($product) {
                $product->increment('stock_quantity', $orderDetail->quantity);
            }
        }

        $order->orderDetails()->delete();

        // Add new products
        if ($request->has('newProducts')) {
            foreach ($request->newProducts as $productData) {
                $product = Product::create([
                    'name' => $productData['name'],
                    'price' => $productData['price'],
                    'stock_quantity' => $productData['quantity'],
                    'weight' => $productData['weight'],
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

                $totalPrice += $productData['price'] * $productData['quantity'];
                $totalWeight += $productData['weight'] * $productData['quantity'];
                $min = min($product->stock_quantity, $productData['quantity']);
                $product->decrement('stock_quantity', $min);
            }
        }

        // Add existing products
        if ($request->has('existingProducts')) {
            foreach ($request->existingProducts as $productData) {
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

        $deliveryFeeSetting = Setting::where('key', 'delivery_rate')->first();
        $deliveryFee = round($totalWeight * ($deliveryFeeSetting?->value ?? 0));

        $updateData = [
            'total_amount' => $totalPrice,
            'subtotal' => $totalPrice,
            'tax' => $request->order_tax ?? 0,
            'discount' => $request->order_discount ?? 0,
            'status' => $request->selectedOrderStatus,
            'notes' => $request->notes,
            'delivery_fee' => $deliveryFee,
            'order_date' => Carbon::createFromFormat('d-m-Y', $request->order_date)->format('Y-m-d H:i:s')
        ];

        if ($request->selectedOrderStatus == 'shipped') {
            $updateData['shipped_at'] = now();
        }

        if ($request->selectedOrderStatus == 'delivered') {
            $updateData['delivered_at'] = now();
        }

        $order->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Order has been successfully updated.'
        ]);
    }

    /**
     * Delete order (AJAX)
     */
    public function destroy($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        // Restore stock
        foreach ($order->orderDetails as $orderDetail) {
            $product = Product::find($orderDetail->product_id);
            if ($product) {
                $product->increment('stock_quantity', $orderDetail->quantity);
            }
        }

        $order->orderDetails()->delete();
        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted and products restocked successfully.'
        ]);
    }

    /**
     * Store new order (AJAX)
     */
    public function store(Request $request)
    {
        try {
            // dd($request->all());
            DB::beginTransaction();
            // Log incoming request for debugging
            $deliveryFeeSetting = Setting::where('key', 'delivery_rate')->first();
            $deliveryFeeRate = $deliveryFeeSetting?->value ?? 0;

            // Get data from request
            $orderDate = $request->input('order_date');
            $newCustomers = $request->input('new_customers', []);
            $existingCustomers = $request->input('existing_customers', []);
            $existingProducts = $request->input('existing_products', []);
            $newProducts = $request->input('new_products', []);

            // Validate basic requirements
            if (empty($orderDate)) {
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'Order date is required'
                ], 422);
            }

            if (empty($existingCustomers) && empty($newCustomers)) {
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'At least one customer is required'
                ], 422);
            }

            if (empty($existingProducts) && empty($newProducts)) {
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'At least one product is required'
                ], 422);
            }

            // Create new customers if any
            $multipleCustomerId = is_array($existingCustomers) ? $existingCustomers : [];

            if (!empty($newCustomers) && is_array($newCustomers)) {
                foreach ($newCustomers as $nCustomer) {
                    $is_customer = UserCustomers::where('name', $nCustomer['name'])->first();
                    if ($is_customer) {
                        $multipleCustomerId[] = $is_customer->id;
                    } else {
                        if (isset($nCustomer['name'])) {
                            $customer = UserCustomers::create([
                                'name' => $nCustomer['name'],
                                'email' => $nCustomer['email'] ?? 'temp_' . time() . '@example.com',
                                'source' => $nCustomer['source']
                            ]);
                            $multipleCustomerId[] = $customer->id;
                        }
                    }
                }
            }

            // Create orders for each customer
            foreach ($multipleCustomerId as $customerId) {
                $is_customer = UserCustomers::where('id', $customerId)->first();
                if ($is_customer) {
                    $order = Order::create([
                        'user_customer_id' => $customerId,
                        'status' => 'pending',
                        'subtotal' => 0,
                        'total_amount' => 0,
                        'order_number' => Str::uuid()->toString(),
                        'order_date' => Carbon::createFromFormat('d-m-Y', $orderDate)->format('Y-m-d H:i:s'),
                        'source' => $request->is_scanned
                    ]);

                    $totalPrice = 0;
                    $totalWeight = 0;

                    // Add new products with images
                    if (!empty($newProducts) && is_array($newProducts)) {
                        foreach ($newProducts as $productData) {
                            if (isset($productData['name']) && isset($productData['price'])) {
                                $product = Product::where('batch_number', $productData['batch_number'])->first();
                                if (!$product) {
                                    $productCreateData = [
                                        'name' => $productData['name'],
                                        'price' => $productData['price'],
                                        'batch_number' => $productData['batch_number'],
                                        'stock_quantity' => $productData['quantity'] ?? 0,
                                        'weight' => $productData['weight'] ?? 0,
                                    ];

                                    // Add image path if provided
                                    if (isset($productData['image_path']) && !empty($productData['image_path'])) {
                                        $productCreateData['image_url'] = $productData['image_path'];
                                    }

                                    $product = Product::create($productCreateData);
                                }

                                $quantity = $productData['quantity'] ?? 0;
                                $price = $productData['price'];
                                $weight = $productData['weight'] ?? 0;

                                OrderDetail::create([
                                    'order_id' => $order->id,
                                    'product_id' => $product->id,
                                    'product_name' => $product->name,
                                    'unit_price' => $price,
                                    'quantity' => $quantity,
                                    'weight' => $weight,
                                    'total_price' => $price * $quantity,
                                ]);

                                $totalPrice += $price * $quantity;
                                $totalWeight += $weight * $quantity;

                                // Decrement stock
                                $min = min($product->stock_quantity, $quantity);
                                $product->decrement('stock_quantity', $min);
                            }
                        }
                    }

                    // Add existing products
                    if (!empty($existingProducts) && is_array($existingProducts)) {
                        foreach ($existingProducts as $productData) {
                            if (isset($productData['product_id'])) {
                                $product = Product::find($productData['product_id']);

                                if ($product) {
                                    $price = $productData['price'] ?? $product->price;
                                    $quantity = $productData['quantity'] ?? 1;
                                    $weight = $productData['weight'] ?? $product->weight;

                                    OrderDetail::create([
                                        'order_id' => $order->id,
                                        'product_id' => $product->id,
                                        'product_name' => $product->name,
                                        'unit_price' => $price,
                                        'quantity' => $quantity,
                                        'weight' => $weight,
                                        'total_price' => $price * $quantity,
                                    ]);

                                    $totalPrice += $price * $quantity;
                                    $totalWeight += $weight * $quantity;

                                    // Decrement stock
                                    $min = min($product->stock_quantity, $quantity);
                                    $product->decrement('stock_quantity', $min);
                                }
                            }
                        }
                    }

                    $deliveryFee = round($totalWeight * $deliveryFeeRate);

                    $order->update([
                        'total_amount' => $totalPrice,
                        'subtotal' => $totalPrice,
                        'delivery_fee' => $deliveryFee
                    ]);
                }
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order has been successfully added.'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Order Store Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating order: ' . $e->getMessage()
            ], 500);
        }
    }


    //     public function store(Request $request)
    // {
    //     try {
    //         DB::beginTransaction();
    
    //         $deliveryFeeSetting = Setting::where('key', 'delivery_rate')->first();
    //         $deliveryFeeRate = $deliveryFeeSetting?->value ?? 0;
    
    //         $orderDate = $request->input('order_date');
    //         $newCustomers = $request->input('new_customers', []);
    //         $existingCustomers = $request->input('existing_customers', []);
    //         $existingProducts = $request->input('existing_products', []);
    //         $newProducts = $request->input('new_products', []);
    
    //         if (empty($orderDate)) {
    //             DB::rollback();
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Order date is required'
    //             ], 422);
    //         }
    
    //         if (empty($existingCustomers) && empty($newCustomers)) {
    //             DB::rollback();
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'At least one customer is required'
    //             ], 422);
    //         }
    
    //         if (empty($existingProducts) && empty($newProducts)) {
    //             DB::rollback();
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'At least one product is required'
    //             ], 422);
    //         }
    
    //         // Collect all customer IDs
    //         $multipleCustomerId = is_array($existingCustomers) ? $existingCustomers : [];
    
    //         // Create new customers if needed
    //         if (!empty($newCustomers) && is_array($newCustomers)) {
    //             foreach ($newCustomers as $nCustomer) {
    //                 $is_customer = UserCustomers::where('name', $nCustomer['name'])->first();
    //                 if ($is_customer) {
    //                     $multipleCustomerId[] = $is_customer->id;
    //                 } else {
    //                     if (isset($nCustomer['name'])) {
    //                         $customer = UserCustomers::create([
    //                             'name' => $nCustomer['name'],
    //                             'email' => $nCustomer['email'] ?? 'temp_' . time() . '@example.com',
    //                             'source' => $nCustomer['source'] ?? null
    //                         ]);
    //                         $multipleCustomerId[] = $customer->id;
    //                     }
    //                 }
    //             }
    //         }
    
    //         // Process each customer
    //         foreach ($multipleCustomerId as $customerId) {
    //             $is_customer = UserCustomers::find($customerId);
    
    //             if (!$is_customer) continue;
    
    //             // 🔍 Check if customer already has a pending order
    //             $order = Order::where('user_customer_id', $customerId)
    //                 ->where('status', 'pending')
    //                 ->first();
    
    //             if (!$order) {
    //                 $order = Order::create([
    //                     'user_customer_id' => $customerId,
    //                     'status' => 'pending',
    //                     'subtotal' => 0,
    //                     'total_amount' => 0,
    //                     'order_number' => Str::uuid()->toString(),
    //                     'order_date' => Carbon::createFromFormat('d-m-Y', $orderDate)->format('Y-m-d H:i:s'),
    //                     'source' => $request->is_scanned
    //                 ]);
    //             }
    
    //             $totalPrice = 0;
    //             $totalWeight = 0;
    
    //             // Add or update NEW products
    //             if (!empty($newProducts) && is_array($newProducts)) {
    //                 foreach ($newProducts as $productData) {
    //                     if (isset($productData['name']) && isset($productData['price'])) {
    //                         $product = Product::where('batch_number', $productData['batch_number'])->first();
    
    //                         if (!$product) {
    //                             $productCreateData = [
    //                                 'name' => $productData['name'],
    //                                 'price' => $productData['price'],
    //                                 'batch_number' => $productData['batch_number'],
    //                                 'stock_quantity' => $productData['quantity'] ?? 0,
    //                                 'weight' => $productData['weight'] ?? 0,
    //                             ];
    
    //                             if (!empty($productData['image_path'])) {
    //                                 $productCreateData['image_url'] = $productData['image_path'];
    //                             }
    
    //                             $product = Product::create($productCreateData);
    //                         }
    
    //                         $quantity = $productData['quantity'] ?? 0;
    //                         $price = $productData['price'];
    //                         $weight = $productData['weight'] ?? 0;
    
    //                         // 🔁 Check if product already in order details
    //                         $existingDetail = OrderDetail::where('order_id', $order->id)
    //                             ->where('product_id', $product->id)
    //                             ->first();
    
    //                         // if ($existingDetail) {
    //                         //     $existingDetail->increment('quantity', $quantity);
    //                         //     $existingDetail->increment('total_price', $price * $quantity);
    //                         // } else {
    //                             OrderDetail::create([
    //                                 'order_id' => $order->id,
    //                                 'product_id' => $product->id,
    //                                 'product_name' => $product->name,
    //                                 'unit_price' => $price,
    //                                 'quantity' => $quantity,
    //                                 'weight' => $weight,
    //                                 'total_price' => $price * $quantity,
    //                             ]);
    //                         // }
    
    //                         $totalPrice += $price * $quantity;
    //                         $totalWeight += $weight * $quantity;
    
    //                         // Decrement stock
    //                         $min = min($product->stock_quantity, $quantity);
    //                         $product->decrement('stock_quantity', $min);
    //                     }
    //                 }
    //             }
    
    //             // Add or update EXISTING products
    //             if (!empty($existingProducts) && is_array($existingProducts)) {
    //                 foreach ($existingProducts as $productData) {
    //                     if (isset($productData['product_id'])) {
    //                         $product = Product::find($productData['product_id']);
    
    //                         if ($product) {
    //                             $price = $productData['price'] ?? $product->price;
    //                             $quantity = $productData['quantity'] ?? 1;
    //                             $weight = $productData['weight'] ?? $product->weight;
    
    //                             $existingDetail = OrderDetail::where('order_id', $order->id)
    //                                 ->where('product_id', $product->id)
    //                                 ->first();
    
    //                             // if ($existingDetail) {
    //                             //     $existingDetail->increment('quantity', $quantity);
    //                             //     $existingDetail->increment('total_price', $price * $quantity);
    //                             // } else {
    //                                 OrderDetail::create([
    //                                     'order_id' => $order->id,
    //                                     'product_id' => $product->id,
    //                                     'product_name' => $product->name,
    //                                     'unit_price' => $price,
    //                                     'quantity' => $quantity,
    //                                     'weight' => $weight,
    //                                     'total_price' => $price * $quantity,
    //                                 ]);
    //                             // }
    
    //                             $totalPrice += $price * $quantity;
    //                             $totalWeight += $weight * $quantity;
    
    //                             $min = min($product->stock_quantity, $quantity);
    //                             $product->decrement('stock_quantity', $min);
    //                         }
    //                     }
    //                 }
    //             }
    
    //             // Recalculate and update order totals
    //             $deliveryFee = round($totalWeight * $deliveryFeeRate);
    
    //             $order->update([
    //                 'total_amount' => DB::raw("total_amount + $totalPrice"),
    //                 'subtotal' => DB::raw("subtotal + $totalPrice"),
    //                 'delivery_fee' => $deliveryFee
    //             ]);
    //         }
    
    //         DB::commit();
    
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Order has been successfully created or updated.'
    //         ]);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         \Log::error('Order Store Error:', [
    //             'message' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);
    
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error creating/updating order: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }


    /**
     * Generate invoice PDF
     */
    public function generateInvoice($id)
    {
        $Invoice = Invoice::with('orders.orderDetails.product', 'userCustomer')
            ->where('id', $id)
            ->firstOrFail();

        $Addresses = CustomerAddress::where('id', $Invoice->address_id)->get();
        $PaymentInfo = Setting::where('key', 'payment_info')->first();
        $euroRate = Setting::where('key', 'euro_rate')->first();

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
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($Invoice->userCustomer->name . ' - ' . $Invoice->invoice_number . '.pdf');
    }

    /**
     * Create invoice for order
     */
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
                'shipping_method_id' => 1,
                'status' => 'completed'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invoices generated successfully.'
        ]);
    }

    /**
     * Apply bulk status update
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled,shipped,delivered',
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id'
        ]);

        Order::whereIn('id', $request->order_ids)->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Selected Orders status has been successfully updated.'
        ]);
    }

    /**
     * Download all orders as Excel
     */
    public function downloadAllOrders(Request $request)
    {
        $query = Order::with(['orderDetails', 'userCustomer']);

        // Apply same filters as getOrders
        if ($request->filled('searchText')) {
            $searchText = $request->searchText;
            $query->where(function ($q) use ($searchText) {
                $q->where('id', 'like', '%' . $searchText . '%')
                    ->orWhere('total_amount', 'like', '%' . $searchText . '%')
                    ->orWhereHas('userCustomer', function ($customerQuery) use ($searchText) {
                        $customerQuery->where('name', 'like', '%' . $searchText . '%')
                            ->orWhere('email', 'like', '%' . $searchText . '%')
                            ->orWhere('phone', 'like', '%' . $searchText . '%');
                    });
            });
        }

        $orders = $query->get()->map(function ($order) {
            return [
                'Order ID' => $order->id,
                'Order Date' => $order->order_date ? $order->order_date->format('d-m-Y H:i:s') : 'N/A',
                'Customer Name' => optional($order->userCustomer)->name ?? 'Guest',
                'Total Products' => $order->orderDetails->sum('quantity'),
                'Total Amount' => $order->total_amount,
                'Status' => $order->status,
                'Source' => $order->source,
            ];
        })->toArray();

        if (empty($orders)) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found.'
            ], 404);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray(array_keys($orders[0]), NULL, 'A1');
        $sheet->fromArray($orders, NULL, 'A2');

        $timestamp = Carbon::now()->format('d-m-Y_H-i-s');
        $filename = "allOrders_{$timestamp}.xlsx";
        $filePath = storage_path("app/public/{$filename}");

        Storage::makeDirectory('public');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    /**
     * Get product data by batch number (for QR scanning)
     */
    public function getProductByBatch($batch_number)
    {
        $product = Product::where('batch_number', $batch_number)->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'product' => [
                'name' => $product->name,
                'batch_number' => $product->batch_number,
                'price' => $product->price,
                'weight' => $product->weight,
                'quantity' => $product->quantity_box,
                'sku' => $product->sku,
            ]
        ]);
    }

    /**
     * Upload product image
     */
    public function uploadProductImage(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $filename = 'product_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

                // Store in public/products directory
                $path = $image->storeAs('products', $filename, 'public');

                return response()->json([
                    'success' => true,
                    'message' => 'Image uploaded successfully',
                    'path' => $path,
                    'url' => asset('storage/' . $path)
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No image file found'
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading image: ' . $e->getMessage()
            ], 500);
        }
    }
}
