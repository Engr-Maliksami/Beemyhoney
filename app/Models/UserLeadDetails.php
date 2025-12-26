<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLeadDetails extends Model
{
    use HasFactory;

    protected $table = 'user_lead_details';

    protected $fillable = [
        'lead_id',
        'lead_key',
        'lead_value',
        'created_at'
    ];
}
