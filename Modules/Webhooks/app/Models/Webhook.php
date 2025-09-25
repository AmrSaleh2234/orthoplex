<?php

namespace Modules\Webhooks\app\Models;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    protected $fillable = [
        'url',
        'secret',
        'events',
        'status',
    ];

    protected $casts = [
        'events' => 'array',
    ];
}
