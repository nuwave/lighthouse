<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;

final class WithoutRelationClassImport extends Model
{
    public function users(): HasMany // Missing the import on purpose
    {
        return $this->hasMany(User::class);
    }
}
