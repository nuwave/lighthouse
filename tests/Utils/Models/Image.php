<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Primary key.
 * @property int $id
 *
 * Attributes
 * @property string|null $url
 *
 * Timestamps
 * @property \lluminate\Support\Carbon $created_at
 * @property \lluminate\Support\Carbon $updated_at
 *
 * Foreign keys
 * @property int|null $imageable_id
 * @property string|null $imageable_type
 *
 * @property-read \Tests\Utils\Models\Task|\Tests\Utils\Models\User|null $imageable
 */
class Image extends Model
{
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
