<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $name
 * @property int|null $creator_id
 * @property string|null $creator_type
 */
class Color extends Model
{
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function creator(): MorphTo
    {
        return $this->morphTo();
    }
}
