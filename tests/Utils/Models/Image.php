<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Primary key.
 *
 * @property int $id
 *
 * Attributes
 * @property string|null $url
 *
 * Timestamps
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * Foreign keys
 * @property int|null $imageable_id
 * @property string|null $imageable_type
 * @property-read \Tests\Utils\Models\Task|\Tests\Utils\Models\User|null $imageable
 */
final class Image extends Model
{
    /** @return \Illuminate\Database\Eloquent\Relations\MorphTo<Model, $this> */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
