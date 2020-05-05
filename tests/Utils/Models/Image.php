<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int|null $imageable_id
 * @property string|null $imageable_type
 * @property string|null $url
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Task|User $imageable
 */
class Image extends Model
{
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
