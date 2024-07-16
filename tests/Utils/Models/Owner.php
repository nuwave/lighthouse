<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
 * Primary key.
 * @property int $id
 * @property string $name
 */

final class Owner extends Model
{
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
