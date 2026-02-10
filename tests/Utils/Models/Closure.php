<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * This model is used for checking that classes that are named like a
 * base PHP class can be correctly resolved.
 */
final class Closure extends Model {}
