<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $table = 'countries';

    // Make sure to match the columns from migration
    protected $fillable = [
        'name', 'code', 'value','pudo'
    ];

    /**
     * Get all states for the country.
     */
    public function cities()
    {
        return $this->hasMany(City::class);
    }

    /**
     * Get all customer addresses associated with the country.
     */
    public function customerAddresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }
}
