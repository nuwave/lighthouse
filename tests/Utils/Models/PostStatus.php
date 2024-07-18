<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;


/**
 * Primary key.
 * @property int $id
 * @property string $status
 *
 *   Foreign keys
 * @property int|null $post_id
 *
 *   Relations
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Post> $post
 */

final class PostStatus extends Model
{
    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<Post> */
    public function post(): HasOne
    {
        return $this->hasOne(Post::class);
    }
}
