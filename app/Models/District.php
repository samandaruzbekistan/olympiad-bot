<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    use HasFactory;

    protected $fillable = [
        'region_id',
        'name_uz',
        'name_ru',
        'name_en',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
