<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFacebookAdaccountBusiness extends Model
{
    use HasFactory;

    protected $table = 'user_facebook_adaccount_business';

    protected $fillable = [
        'business_id',
        'adaccount_id',
        'facebook_id'
    ];

    public function adaccount()
    {
        return $this->belongsTo(UserFacebookAdaccount::class, 'adaccount_id', 'account_id');
    }
}
