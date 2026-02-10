<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Primary key.
 *
 * @property int $id
 *
 * Attributes
 * @property string $title
 * @property \Illuminate\Support\Carbon|null $schedule_at
 *
 * Timestamps
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class Podcast extends Model
{
    protected static function booted(): void
    {
        self::addGlobalScope('scheduled', fn (Builder $query): Builder => $query
            ->whereNotNull('schedule_at'));
    }
}
