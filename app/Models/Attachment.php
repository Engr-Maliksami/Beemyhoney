<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    protected $table = 'attachments';
    protected $guarded = [];

    public function inbounds()
    {
        return $this->belongsToMany(Inbound::class, 'inbound_attachments');
    }
}
