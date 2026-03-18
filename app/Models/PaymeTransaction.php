<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymeTransaction extends Model
{
    use HasFactory;

    public const STATE_CREATED = 1;
    public const STATE_COMPLETED = 2;
    public const STATE_CANCELLED = -1;
    public const STATE_CANCELLED_AFTER_COMPLETE = -2;

    protected $fillable = [
        'payment_id',
        'payme_id',
        'state',
        'amount',
        'time',
        'account',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'state' => 'integer',
        'time' => 'integer',
        'account' => 'array',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
