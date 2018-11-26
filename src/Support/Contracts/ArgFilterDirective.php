<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgFilterDirective extends Directive
{
    const SINGLE_TYPE = 'single_type';
    const MULTI_TYPE = 'multi_type';

    /**
     * Get the filter.
     *
     * @return \Closure
     */
    public function filter(): \Closure;

    /**
     * Get the type of the ArgFilterDirective.
     *
     * @return string self::SINGLE_TYPE | self::MULTI_TYPE
     */
    public function type(): string;
}
