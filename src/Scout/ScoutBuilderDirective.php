<?php

namespace Nuwave\Lighthouse\Scout;

use Laravel\Scout\Builder as ScoutBuilder;

interface ScoutBuilderDirective
{
    /**
     * Modify the scout builder with a client given value.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  mixed  $value  Any client given value
     * @return \Laravel\Scout\Builder
     */
    public function handleScoutBuilder(ScoutBuilder $builder, $value): ScoutBuilder;
}
