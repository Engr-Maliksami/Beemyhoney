<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class UserFacebookAccount extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'user_facebook_accounts';

    protected $fillable = [
        'Name',
        'facebook_id',
        'account_id',
        'access_token',
        'error_sent',
    ];
}
