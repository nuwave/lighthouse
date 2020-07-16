<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $activity_id
 * @property string $activity_type
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * Relations
 * @property-read \Tests\Utils\Models\Post|\Tests\Utils\Models\Task $content
 * @property-read \Tests\Utils\Models\User $user
 */
class Activity extends Model
{
    public function content(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
