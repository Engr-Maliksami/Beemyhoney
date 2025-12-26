<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFacebookAdaccount extends Model
{
    use HasFactory;

    protected $table = 'user_facebook_adaccounts';

    protected $fillable = [
        'facebook_id',
        'account_id',
        'account_name',
    ];

    public function business()
    {
        return $this->belongsTo(UserFacebookBusiness::class, 'facebook_id', 'facebook_id');
    }
    
    public function adaccountBusiness()
    {
        return $this->belongsTo(UserFacebookAdaccountBusiness::class, 'account_id', 'adaccount_id');
    }
}
