<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'products';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'status' => 'string',
        'weight' => 'decimal:2',
    ];

    /**
     * Get the customer that owns the product.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class)->withDefault([]);
    }

    public function inbounds()
    {
        return $this->belongsToMany(Inbound::class, 'inbound_products');
    }

    public function outbounds()
    {
        return $this->belongsToMany(Outbound::class, 'outbound_products');
    }
}
