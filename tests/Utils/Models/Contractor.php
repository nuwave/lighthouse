<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Contractor extends Model
{
    protected $guarded = [];

    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'person');
    }

    public function colors(): MorphMany
    {
        return $this->morphMany(Color::class, 'creator');
    }
}
