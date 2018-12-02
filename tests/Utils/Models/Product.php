<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    protected $primaryKey = ['barcode', 'uuid'];
    public $incrementing = false;

    public function color() : BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    // By default Laravel does not support composite keys
    // So, you will need to override some getKey() method
    // Usually this is placed on traits
    // This is not related to Lighthouse

    public function getKey()
    {
        $attributes = [];
        foreach ($this->getKeyName() as $key) {
            $attributes[$key] = $this->getAttribute($key);
        }
        return $attributes;
    }
}
