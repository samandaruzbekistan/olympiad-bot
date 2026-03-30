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
        'payme_time',
        'create_time',
        'perform_time',
        'cancel_time',
        'reason',
        'account',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'state'   => 'integer',
        'reason'  => 'integer',
        'account' => 'array',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function msTimestamp(string $field): int
    {
        if (! array_key_exists($field, $this->attributes)) {
            return 0;
        }

        $raw = $this->attributes[$field];

        if ($raw === null || $raw === '') {
            return 0;
        }

        if (is_string($raw) && ctype_digit($raw)) {
            return (int) $raw;
        }

        return (int) $raw;
    }

    public function isExpired(): bool
    {
        $timeout = 43_200_000; // 12 hours in ms

        return $this->state === self::STATE_CREATED
            && (now()->getTimestampMs() - $this->msTimestamp('payme_time')) > $timeout;
    }

    public function isCancelled(): bool
    {
        return in_array($this->state, [self::STATE_CANCELLED, self::STATE_CANCELLED_AFTER_COMPLETE], true);
    }
}
