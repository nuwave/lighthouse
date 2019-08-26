<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

interface FieldMiddleware extends Directive, ProvidesFieldMiddleware
{
}
