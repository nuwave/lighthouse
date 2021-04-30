<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $title
 * @property int $price
 * @property \lluminate\Support\Carbon $created_at
 * @property \lluminate\Support\Carbon $updated_at
 */
class Book extends Model
{
    use SoftDeletes;

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'author_book', 'author_id', 'book_id');
    }
}
