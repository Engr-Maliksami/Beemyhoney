<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboundAttachment extends Model
{
    use HasFactory;

    protected $table = 'outbound_attachments';

    protected $guarder = [];

    public function outbounds()
    {
        return $this->belongsToMany(Outbound::class, 'outbound_attachments');
    }

    public function getFileUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }
}
