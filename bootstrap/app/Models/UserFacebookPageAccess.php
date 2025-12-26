<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFacebookPageAccess extends Model
{
    use HasFactory;

    protected $table = 'user_facebook_page_access';

    protected $fillable = [
        'facebook_id',
        'page_id',
        'app_has_leads_permission',
        'can_access_lead',
        'enabled_lead_access_manager',
        'failure_reason',
        'failure_resolution',
        'is_page_admin',
        'user_has_leads_permission',
    ];
}
