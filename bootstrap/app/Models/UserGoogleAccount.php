<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGoogleAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'google_user_id',
        'email',
        'access_token',
        'refresh_token',
        'token_type',
        'expires_in',
    ];
}
