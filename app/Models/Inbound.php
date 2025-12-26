<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inbound extends Model
{
    use HasFactory;

    protected $table = 'inbounds';

    protected $guarded = [];

    // Inbound Model
    public function products()
    {
        return $this->belongsToMany(Product::class, 'inbound_products');
    }

    public function attachments()
    {
        return $this->belongsToMany(Attachment::class, 'inbound_attachments');
    }
}
