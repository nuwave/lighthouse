<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Primary key.
 * @property string $barcode
 * @property string $uuid
 *
 * Attributes
 * @property string $name
 *
 * Timestamps
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * Foreign keys
 * @property int $color_id
 *
 * Relations
 * @property-read \Tests\Utils\Models\Color $color
 */
class Product extends Model
{
    /**
     * @var array<string>
     */
    protected $primaryKey = ['barcode', 'uuid'];

    public $incrementing = false;

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    // By default Laravel does not support composite keys
    // So, you will need to override some getKey() method
    // Usually this is placed on traits
    // This is not related to Lighthouse

    /**
     * @return array<string, mixed>
     */
    public function getKey(): array
    {
        $attributes = [];
        foreach ($this->primaryKey as $key) {
            $attributes[$key] = $this->getAttribute($key);
        }

        return $attributes;
    }
}
