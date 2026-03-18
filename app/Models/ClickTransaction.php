<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClickTransaction extends Model
{
    use HasFactory;

    public const STATUS_CANCELLED = -1;
    public const STATUS_PENDING = 0;
    public const STATUS_SUCCESS = 1;

    protected $fillable = [
        'click_trans_id',
        'click_paydoc_id',
        'merchant_trans_id',
        'amount',
        'status',
        'sign_time',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => 'integer',
    ];
}
