<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int|null $acl_id
 * @property string $name
 */
class Role extends Model
{
    /** @var bool */
    public $timestamps = false;

    public function users(): BelongsToMany
    {
        return $this
            ->belongsToMany(User::class)
            ->withPivot(['meta']);
    }

    public function acl(): BelongsTo
    {
        return $this->belongsTo(ACL::class);
    }
}
