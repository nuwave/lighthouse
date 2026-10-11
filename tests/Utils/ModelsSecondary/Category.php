<?php declare(strict_types=1);

namespace Tests\Utils\ModelsSecondary;

use Illuminate\Database\Eloquent\Model;

/**
 * This class has the same name as a model in the primary namespace.
 * It will only be used if the namespace is explicitly given.
 */
final class Category extends Model {}
