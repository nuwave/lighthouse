<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $category_id
 * @property string $name
 */
class Category extends Model
{
    protected $guarded = [];
    protected $primaryKey = 'category_id';
}
