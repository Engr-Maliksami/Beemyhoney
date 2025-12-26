<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $searchText = $request->get('searchText', '');
        $selectedStatus = $request->get('selectedStatus', '');
        $selectedDateRange = $request->get('selectedDateRange', '');

        $products = Product::when($searchText, function ($query) use ($searchText) {
            $query->where(function ($q) use ($searchText) {
                $q->where('name', 'like', '%' . $searchText . '%')
                    ->orWhere('description', 'like', '%' . $searchText . '%')
                    ->orWhere('sku', 'like', '%' . $searchText . '%');
            });
        })
            ->when($selectedStatus, function ($query) use ($selectedStatus) {
                if ($selectedStatus == 'out_stock') {
                    $query->where('stock_quantity', '<=', 0);
                } else {
                    $query->where('status', $selectedStatus);
                }
            })
            ->when($selectedDateRange, function ($query) use ($selectedDateRange) {
                $dates = explode(' to ', $selectedDateRange);
                if (count($dates) === 2) {
                    $query->whereBetween('created_at', [$dates[0], $dates[1]]);
                } elseif (count($dates) === 1) {
                    $query->whereDate('created_at', $dates[0]);
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(100);

        return view('products.index', compact('products', 'searchText', 'selectedStatus', 'selectedDateRange'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string|max:1000',
            'price'                 => 'required|numeric|min:0',
            'weight'                => 'required|numeric|min:0',
            'stock_quantity'        => 'required|integer|min:0',
            'sku'                   => 'nullable|string|max:255|unique:products,sku,NULL,id,deleted_at,NULL',
            'status'                => 'required|in:active,inactive',
            'ean'                   => 'nullable|string|max:255',
            'batch_number'          => 'nullable|string|max:255',
            'expire_date'           => 'nullable|date',
            'quantity_box'          => 'nullable|integer|min:0',
            'quantity_pallet'       => 'nullable|integer|min:0',
            'net_weight'            => 'nullable|numeric|min:0',
            'gross_weight'          => 'nullable|numeric|min:0',
            'length'                => 'nullable|numeric|min:0',
            'width'                 => 'nullable|numeric|min:0',
            'height'                => 'nullable|numeric|min:0',
            'quantity_per_box'      => 'nullable|integer|min:0',
            'quantity_per_pallet'   => 'nullable|integer|min:0',
            'comment'               => 'nullable|string|max:1000',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $validated['image_url'] = $imagePath;

        Product::create($validated);

        return redirect()->route('products')->with('success', 'Product added successfully.');
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string|max:1000',
            'price'                 => 'required|numeric|min:0',
            'weight'                => 'required|numeric|min:0',
            'stock_quantity'        => 'required|integer|min:0',
            'sku'                   => 'nullable|string|max:255|unique:products,sku,' . $id . ',id,deleted_at,NULL',
            'status'                => 'required|in:active,inactive',
            'ean'                   => 'nullable|string|max:255',
            'batch_number'          => 'nullable|string|max:255',
            'expire_date'           => 'nullable|date',
            'quantity_box'          => 'nullable|integer|min:0',
            'quantity_pallet'       => 'nullable|integer|min:0',
            'net_weight'            => 'nullable|numeric|min:0',
            'gross_weight'          => 'nullable|numeric|min:0',
            'length'                => 'nullable|numeric|min:0',
            'width'                 => 'nullable|numeric|min:0',
            'height'                => 'nullable|numeric|min:0',
            'quantity_per_box'      => 'nullable|integer|min:0',
            'quantity_per_pallet'   => 'nullable|integer|min:0',
            'comment'               => 'nullable|string|max:1000',
        ]);

        $product = Product::findOrFail($id);

        if ($request->hasFile('image')) {
            if ($product->image_url) {
                Storage::disk('public')->delete($product->image_url);
            }
            $validated['image_url'] = $request->file('image')->store('products', 'public');
        }

        $product->update($validated);

        return redirect()->route('products')->with('success', 'Product updated successfully.');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        if ($product->image_url && Storage::exists('public/' . $product->image_url)) {
            Storage::delete('public/' . $product->image_url);
        }

        $product->delete();

        return redirect()->route('products')->with('success', 'Product deleted successfully.');
    }

    public function toggleStatus($id)
    {
        $product = Product::findOrFail($id);
        $product->status = $product->status === 'active' ? 'inactive' : 'active';
        $product->save();

        return redirect()->route('products')->with('success', 'Product status updated successfully.');
    }

    public function getProductByBatch(Request $request)
    {
        $batchNumber = $request->get('batch_number');
        $product = Product::where('batch_number', $batchNumber)->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'batch_number' => $batchNumber,
                'message' => 'Product not exists'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'product' => $product
        ]);
    }
}
