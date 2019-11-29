<?php


namespace Tests\Utils\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Class Supplier
 */
class Supplier extends Model
{
    public function brand(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class);
    }
}
