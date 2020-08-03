<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $barcode
 * @property string $uuid
 * @property string $name
 * @property int $color_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Product extends Model
{
    /**
     * @var array<string>
     */
    protected $primaryKey = ['barcode', 'uuid'];

    /**
     * @var bool
     */
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
