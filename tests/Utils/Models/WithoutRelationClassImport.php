<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 */
class WithoutRelationClassImport extends Model
{
    protected $guarded = [];

    public function users(): HasMany // @phpstan-ignore-line Missing the import on purpose
    {
        return $this->hasMany(User::class);
    }
}
