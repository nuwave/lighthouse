<?php

namespace Tests;

/**
 * This class exists as a workaround to both:
 * - use constants in migrations
 * - make PHPStan happy.
 *
 * As migrations are not namespaced, they do not play nice with PHPStan.
 */
class Constants
{
    const TAGS_DEFAULT_STRING = 'this is the default string';
}
