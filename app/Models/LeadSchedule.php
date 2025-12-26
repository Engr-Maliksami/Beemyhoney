<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'interval_value',
        'interval_unit',
        'scheduled_at',
        'is_sent',
    ];
}
