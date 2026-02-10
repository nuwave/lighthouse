<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Primary key.
 *
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
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Tests\Utils\Models\User $user
 * @property-read \Tests\Utils\Models\Post $post
 */
final class Comment extends Model
{
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Tests\Utils\Models\User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Tests\Utils\Models\Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
