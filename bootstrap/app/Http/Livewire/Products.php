<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Product;
use App\Models\CustomerAddress;
use Livewire\WithPagination;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class Products extends Component
{
    use WithPagination;
    use WithFileUploads;

    public $editProductId;
    public $deleteProductId = null;
    public $selectedIds = [];
    public $selectAll = false;
    public $selectedStatus = "";
    public $searchText = "";
    public $selectedDateRange;

    public $name, $description, $price, $weight, $stock_quantity, $sku, $image, $status = 'active';

    protected $rules = [
        'name'           => 'required|string|max:255',
        'description'    => 'nullable|string|max:1000',
        'price'          => 'required|numeric|min:0',
        'weight'         => 'required|numeric|min:0',
        'stock_quantity' => 'required|integer|min:0',
        'sku'            => 'nullable|string|max:255|unique:products,sku,NULL,id,deleted_at,NULL',
        'status'         => 'required|in:active,inactive',
        'image'          => 'nullable|image|max:10240',
    ];

    public function updatedSearchClient()
    {
        $this->resetPage();
    }

    public function toggleStatus($productId)
    {
        $product = Product::find($productId);
        if ($product) {
            $product->status = $product->status === 'active' ? 'inactive' : 'active';
            $product->save();
            session()->flash('success', 'Product status updated successfully.');
        }
    }

    public function addProduct()
    {
        $this->validate();

        $imagePath = null;
        if ($this->image) {
            $imagePath = $this->image->store('products', 'public');
        }

        Product::create([
            'name'           => $this->name,
            'description'    => $this->description,
            'price'          => $this->price,
            'weight'          => $this->weight,
            'stock_quantity' => $this->stock_quantity,
            'sku'            => $this->sku,
            'image_url'      => $imagePath,
            'status'         => $this->status,
        ]);

        session()->flash('success', 'Product added successfully.');

        $this->resetFields();
        $this->emit('productAdded');
    }

    public function editProduct($id)
    {
        $this->editProductId =  $id;
        $product = Product::findOrFail($id);
        $this->name = $product->name;
        $this->description = $product->description;
        $this->price = $product->price;
        $this->weight = $product->weight;
        $this->stock_quantity = $product->stock_quantity;
        $this->sku = $product->sku;
        $this->status = $product->status;
    }

    public function updateProduct()
    {
        $validatedData = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'weight'         => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'sku' => 'nullable|string|max:255|unique:products,sku,' . $this->editProductId . ',id,deleted_at,NULL',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|max:2048',
        ]);
        if ($this->editProductId) {

            $product = Product::findOrFail($this->editProductId);

            if ($this->image) {
                if ($product->image_url) {
                    Storage::disk('public')->delete('products/' . $product->image_url);
                }
                $imagePath = $this->image->store('products', 'public');
                $validatedData['image_url'] = $imagePath;
            }

            $product->update($validatedData);
            session()->flash('success', 'Product updated successfully.');
            $this->resetFields();
            $this->emit('productUpdated');
        }
    }

    public function confirmDelete($id)
    {
        $this->deleteProductId = $id;
        $this->dispatchBrowserEvent('swal:confirm', [
            'title' => 'Are you sure?',
            'text' => 'This will permanently delete the product.',
            'type' => 'warning',
            'function' => 'product',
            'showCancelButton' => true,
            'confirmButtonText' => 'Yes, delete it!',
            'cancelButtonText' => 'No, keep it',
        ]);
    }

    public function deleteProduct()
    {
        if ($this->deleteProductId) {
            $product = Product::find($this->deleteProductId);
            if ($product) {
                if ($product->image_url && Storage::exists('public/' . $product->image_url)) {
                    Storage::delete('public/' . $product->image_url);
                }
                $product->delete();
                session()->flash('success', 'Product deleted successfully.');
                $this->reset(['deleteProductId']);
            } else {
                session()->flash('error', 'Product not found.');
            }
        }
    }

    public function resetFields()
    {
        $this->reset(['name', 'description', 'price', 'stock_quantity', 'sku', 'weight', 'image', 'status']);
    }

    public function getProduct()
    {
        return Product::when($this->searchText, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->searchText . '%')
                    ->orWhere('description', 'like', '%' . $this->searchText . '%')
                    ->orWhere('sku', 'like', '%' . $this->searchText . '%');
                });
            })
            ->when($this->selectedStatus, function ($query) {
                if ($this->selectedStatus == 'out_stock') {
                    $query->where('stock_quantity', '<=', 0);
                } else {
                    $query->where('status', $this->selectedStatus);
                }
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
        return view('livewire.products', [
            'Products' => $this->getProduct()
        ]);
    }
}
