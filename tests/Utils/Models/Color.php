<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Color extends Model
{
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
