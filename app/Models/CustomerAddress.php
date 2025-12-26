<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    use HasFactory;

    protected $table = 'customer_addresses';

    protected $fillable = [
        'user_customer_id',
        'name',
        'contact_name',
        'phone',
        'email',
        'info',
        'country_id',
        'city_id',
    ];

    /**
     * Define the relationship to the UserCustomers model.
     */
    public function userCustomer()
    {
        return $this->belongsTo(UserCustomers::class, 'user_customer_id');
    }

    /**
     * Define the relationship to the Country model.
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }
}
