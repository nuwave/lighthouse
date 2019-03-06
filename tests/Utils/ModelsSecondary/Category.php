<?php

namespace Tests\Utils\ModelsSecondary;

use Illuminate\Database\Eloquent\Model;

/**
 * This class is named the same as a model in the primary namespace,
 * so it will only be used if the namespace is explicitly given.
 */
class Category extends Model
{
    //
}
