<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_customer_id',
        'invoice_id',
        'subtotal',
        'discount',
        'tax',
        'delivery_fee',
        'total_amount',
        'delivery_fee',
        'status',
        'source',
        'order_number',
        'notes',
        'meta',
        'shipping_method_id',
        'address_id',
        'order_date',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'order_date' => 'datetime',
    ];

    public function userCustomer()
    {
        return $this->belongsTo(UserCustomers::class, 'user_customer_id');
    }

    public function shippingMethod()
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function address()
    {
        return $this->belongsTo(CustomerAddress::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'order_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
