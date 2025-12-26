<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFacebookBusiness extends Model
{
    use HasFactory;

    protected $table = 'user_facebook_business';

    protected $fillable = [
        'facebook_id',
        'business_id',
        'business_name',
    ];

    public function adaccounts()
    {
        return $this->hasManyThrough(UserFacebookAdaccount::class, UserFacebookAdaccountBusiness::class, 'business_id', 'account_id', 'business_id', 'adaccount_id');
    }
}
