<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserCustomers extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'user_customers';

    protected $fillable = [
        'facebook_id',
        'name',
        'email',
        'phone',
        'source',
        'additional_data',
    ];

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class, 'user_customer_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'user_customer_id');
    }
}
