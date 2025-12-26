<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InboundProduct extends Model
{
    use HasFactory;

    protected $table = 'inbound_products';

    protected $guarded = [];
}
