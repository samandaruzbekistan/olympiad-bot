<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OlympiadType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function olympiads(): HasMany
    {
        return $this->hasMany(Olympiad::class, 'type_id');
    }
}
