<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Olympiad extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'subject_id',
        'price',
        'start_date',
        'location_name',
        'location_address',
        'latitude',
        'longitude',
        'logo',
        'capacity',
        'status',
    ];

    protected $casts = [
        'start_date' => 'datetime',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
