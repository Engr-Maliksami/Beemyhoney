<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFacebookPageForm extends Model
{
    use HasFactory;

    protected $table = 'user_facebook_page_forms';

    protected $fillable = [
        'name',
        'form_id',
        'page_id',
    ];
}
