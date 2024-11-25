<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Primary key.
 *
 * @property int $id
 *
 * Attributes
 * @property array<string, mixed> $extra
 *
 * Timestamps
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * Foreign keys
 * @property int|null $parent_id
 *
 * Relations
 * @property-read \Tests\Utils\Models\Location $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Location> $children
 */
final class Location extends Model
{
    /** @var array<string, string> */
    protected $casts = [
        'extra' => 'array',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(__CLASS__, 'parent_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(__CLASS__, 'parent_id');
    }
}
