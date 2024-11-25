<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Primary key.
 *
 * @property int $category_id
 *
 * Attributes
 * @property string $name
 *
 * Timestamps
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class Category extends Model
{
    protected $primaryKey = 'category_id';

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Tests\Utils\Models\Post, $this> */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'category_post', 'post_id', 'category_id');
    }
}
