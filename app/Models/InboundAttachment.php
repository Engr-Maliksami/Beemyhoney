<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InboundAttachment extends Model
{
    use HasFactory;

    protected $table = 'inbound_attachments';

    protected $guarded = [];
}
