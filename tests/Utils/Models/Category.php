<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $category_id
 * @property string $name
 * @property \lluminate\Support\Carbon $created_at
 * @property \lluminate\Support\Carbon $updated_at
 */
class Category extends Model
{
    protected $primaryKey = 'category_id';
}
