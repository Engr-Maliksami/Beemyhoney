<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    use HasFactory;

    protected $table = 'states';

    // Make sure to include all the fields defined in your migration
    protected $fillable = [
        'name', 'country_id', 'country_code', 'fips_code', 'iso2', 
        'latitude', 'longitude', 'flag', 'wikiDataId'
    ];

    // Optional: Cast fields like latitude, longitude, or wikiDataId as needed
    protected $casts = [
        'latitude' => 'string',  // or 'float' if you expect numeric latitude/longitude
        'longitude' => 'string', // or 'float'
        'wikiDataId' => 'array', // If you want to treat wikiDataId as a JSON array
    ];

    /**
     * Get the country that the state belongs to.
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get all cities for the state.
     */
    public function cities()
    {
        return $this->hasMany(City::class);
    }

    /**
     * Get all customer addresses associated with the state.
     */
    public function customerAddresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }
}
