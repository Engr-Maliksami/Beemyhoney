<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Outbound extends Model
{
    use HasFactory;

    protected $table = 'outbounds';

    protected $guarded = [];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'outbound_products');
    }

    public function attachments()
    {
        return $this->belongsToMany(Attachment::class, 'outbound_attachments');
    }
}
