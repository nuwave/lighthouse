<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface WithReferenceRule
{
    /**
     * Used for custom validation classes so they can add the absolute argument
     * path to any parameters that reference other fields that are being
     * validated.
     *
     * @param array<int|string> $argumentPath
     */
    public function setArgumentPath(array $argumentPath): void;
}
