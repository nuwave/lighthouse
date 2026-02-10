<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Primary key.
 *
 * @property int $id
 *
 * Timestamps
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * Foreign keys
 * @property int $activity_id
 * @property string $activity_type
 * @property int $user_id
 *
 * Relations
 * @property-read \Tests\Utils\Models\Post|\Tests\Utils\Models\Task $content
 * @property-read \Tests\Utils\Models\User $user
 */
final class Activity extends Model
{
    /** @return \Illuminate\Database\Eloquent\Relations\MorphTo<\Illuminate\Database\Eloquent\Model, $this> */
    public function content(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Tests\Utils\Models\User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
