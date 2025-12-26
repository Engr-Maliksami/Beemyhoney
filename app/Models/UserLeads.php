<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLeads extends Model
{
    use HasFactory;

    protected $table = 'user_leads';

    protected $fillable = [
        'facebook_page_id',
        'facebook_form_id',
        'client_id',
        'zap_id',
        'status',
        'source',
        'created_at'
    ];

    public function leadDetails()
    {
        return $this->hasMany(UserLeadDetails::class, 'lead_id');
    }
}
