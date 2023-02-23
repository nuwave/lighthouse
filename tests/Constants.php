<?php declare(strict_types=1);

namespace Tests;

/**
 * This class exists as a workaround to both:
 * - use constants in migrations
 * - make PHPStan happy.
 *
 * As migrations are not namespaced, they do not play nice with PHPStan.
 */
final class Constants
{
    public const TAGS_DEFAULT_STRING = 'this is the default string';
}
