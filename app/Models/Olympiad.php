<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Olympiad extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type_id',
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

    public function type(): BelongsTo
    {
        return $this->belongsTo(OlympiadType::class, 'type_id');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'olympiad_subject')->withTimestamps();
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
