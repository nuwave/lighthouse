<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property \lluminate\Support\Carbon $created_at
 * @property \lluminate\Support\Carbon $updated_at
 *
 */
class Author extends Model
{
    use SoftDeletes;

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'author_book', 'book_id', 'author_id');
    }
}
