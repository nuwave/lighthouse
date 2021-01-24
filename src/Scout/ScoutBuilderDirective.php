<?php

namespace Nuwave\Lighthouse\Scout;

use Laravel\Scout\Builder as ScoutBuilder;

interface ScoutBuilderDirective
{
    public function handleScoutBuilder(ScoutBuilder $builder, $value);
}
