<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface WithReferenceRule
{
    public function setArgumentPath(string $argumentPath): void;
}