<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserZapLinks extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'user_zap_links';

    protected $fillable = [
        'zap_id',
        'discord_url',
        'discord_bot_name',
    ];
}
