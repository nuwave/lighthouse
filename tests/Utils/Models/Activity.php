<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Primary key.
 * @property int $id
 *
 * Timestamps
 * @property \lluminate\Support\Carbon $created_at
 * @property \lluminate\Support\Carbon $updated_at
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
