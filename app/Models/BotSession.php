<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_id',
        'state',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
        'telegram_id' => 'string',
    ];
}
