<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Scout;

use Laravel\Scout\Builder as ScoutBuilder;

interface ScoutBuilderDirective
{
    /**
     * Modify the scout builder with a client given value.
     *
     * @param  mixed  $value  Any client given value
     */
    public function handleScoutBuilder(ScoutBuilder $builder, mixed $value): ScoutBuilder;
}
