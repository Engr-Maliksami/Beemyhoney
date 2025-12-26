<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFacebookPageBusiness extends Model
{
    use HasFactory;

    protected $table = 'user_facebook_page_business';

    protected $fillable = [
        'page_id',
        'facebook_id',
        'business_id',
    ];
}