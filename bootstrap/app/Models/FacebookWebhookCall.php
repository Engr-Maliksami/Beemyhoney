<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacebookWebhookCall extends Model
{
    use HasFactory;

    protected $table = 'webhook_call';

    protected $fillable = [
        'page_id',
        'post_id',
        'comment_id',
        'message',
        'cus_fb_id',
        'cus_fb_name',
        'item_type',
        'field',
        'from',
        'order_id',
        'post',
        'value',
        'headers',
        'ip',
        'user_agent',
        'status',
        'raw_json'
    ];

    protected $casts = [
        'from' => 'array',
        'post' => 'array',
        'value' => 'array',
        'headers' => 'array',
        'raw_json' => 'array'
    ];

    public function customer()
    {
        return $this->belongsTo(UserCustomers::class, 'cus_fb_id', 'facebook_id');
    }

    public function facebookPage()
    {
        return $this->belongsTo(UserFacebookPage::class, 'page_id', 'page_id');
    }
}
