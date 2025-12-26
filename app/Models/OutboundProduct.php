<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboundProduct extends Model
{
    use HasFactory;

    protected $table = 'outbound_products';

    protected $guarder = [];
}
