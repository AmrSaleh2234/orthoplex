<?php

namespace Modules\Analytics\app\Models;

use Illuminate\Database\Eloquent\Model;

class LoginDaily extends Model
{
    protected $table = 'login_daily';

    protected $fillable = [
        'date',
        'login_count',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
