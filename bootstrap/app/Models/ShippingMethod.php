<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'username',
        'password',
        'api_key',
        'api_secret',
        'api_token',
        'api_url',
        'additional_info',
        'validUntil',
    ];
    
    protected $casts = [
        'validUntil' => 'datetime',
    ];

    /**
     * Relationship to orders using this shipping method.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
