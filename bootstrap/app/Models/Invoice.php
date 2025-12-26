<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';

    protected $fillable = [
        'invoice_number',
        'user_customer_id',
        'start_date',
        'end_date',
        'subtotal',
        'discount',
        'tax',
        'weight',
        'delivery_fee',
        'dpd_fee',
        'total_amount',
        'notes',
        'pudoId',
        'shipment_id',
        'parcel_no',
        'status'
    ];

    /**
     * Relationship: Customer associated with this invoice.
     */
    public function userCustomer()
    {
        return $this->belongsTo(UserCustomers::class, 'user_customer_id');
    }

    /**
     * Relationship: Orders included in this invoice.
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'invoice_id');
    }
}
