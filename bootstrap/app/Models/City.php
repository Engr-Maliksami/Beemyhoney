<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $table = 'cities';

    // Include all the fields defined in your migration
    protected $fillable = [
        'name', 'country_id'
    ];

    /**
     * Get the state that the city belongs to.
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get all customer addresses associated with the city.
     */
    public function customerAddresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }
}
