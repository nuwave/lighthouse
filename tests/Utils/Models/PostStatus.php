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
 * @property string $status
 *
 * Foreign keys
 * @property int $post_id
 *
 * Relations
 * @property-read \Tests\Utils\Models\Post $post
 */
final class PostStatus extends Model
{
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Tests\Utils\Models\Post, self> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
