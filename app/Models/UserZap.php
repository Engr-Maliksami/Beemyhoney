<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserZap extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'user_zaps';

    protected $fillable = [
        'name',
        'facebook_id',
        'adaccount_id',
        'facebook_page_id',
        'facebook_form_id',
        'user_id',
        'client_id',
        'folder_id',
        'sub_sheet_id',
        'discord_message',
        'status',
    ];

    public function facebookAccount()
    {
        return $this->belongsTo(UserFacebookAccount::class, 'facebook_id');
    }

    public function facebookPage()
    {
        return $this->belongsTo(UserFacebookPage::class, 'facebook_page_id');
    }

    public function facebookPageForm()
    {
        return $this->belongsTo(UserFacebookPageForm::class, 'facebook_form_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function facebookPageAccess()
    {
        return $this->hasOne(UserFacebookPageAccess::class, 'page_id', 'facebook_page_id');
    }
}
