<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Primary key.
 * @property int $id
 *
 * Attributes
 * @property string $comment
 *
 * Foreign keys
 * @property int $user_id
 * @property int $post_id
 *
 * Timestamps
 * @property \lluminate\Support\Carbon $created_at
 * @property \lluminate\Support\Carbon $updated_at
 *
 * @property-read \Tests\Utils\Models\User $user
 * @property-read \Tests\Utils\Models\Post $post
 */
class Comment extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
