<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Primary key.
 * @property int $id
 *
 * Attributes
 * @property string $name
 *
 * Foreign keys
 * @property int|null $creator_id
 * @property string|null $creator_type
 *
 * Timestamps
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * Relations
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Product> $products
 * @property-read \Tests\Utils\Models\Employee|\Tests\Utils\Models\Contractor|null $creator
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
