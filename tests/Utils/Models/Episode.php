<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;


/**
 * Primary key.
 *
 * @property int $id
 *
**/

final class Episode extends Model
{
    protected static function booted(): void
    {
        self::addGlobalScope("published", function ($q) {
            $q->whereNotNull("schedule_at");
        });
    }
}
