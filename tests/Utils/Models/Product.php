<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Primary key.
 *
 * @property string $barcode
 * @property string $uuid
 *
 * Attributes
 * @property string $name
 *
 * Timestamps
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * Foreign keys
 * @property int $color_id
 *
 * Relations
 * @property-read \Tests\Utils\Models\Color $color
 */
final class Product extends Model
{
    /** @var array<string> */
    // @phpstan-ignore-next-line PHPDoc type array<string> of property Tests\Utils\Models\Product::$primaryKey is not covariant with PHPDoc type string of overridden property Illuminate\Database\Eloquent\Model::$primaryKey.
    protected $primaryKey = ['barcode', 'uuid'];

    public $incrementing = false;

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Tests\Utils\Models\Color, $this> */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    /**
     * By default, Laravel does not support composite keys.
     * So, you will need to override the getKey() method.
     * Usually this is placed on traits, this is not related to Lighthouse.
     *
     * @return array<string, mixed>
     */
    public function getKey(): array
    {
        $attributes = [];
        foreach ($this->primaryKey as $key) {
            $attributes[$key] = $this->getAttribute($key);
        }

        return $attributes;
    }
}
