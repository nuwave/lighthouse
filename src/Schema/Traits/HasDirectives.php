<?php


namespace Nuwave\Lighthouse\Schema\Traits;


use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Directive;

trait HasDirectives
{
    protected $directives;

    public function directives() : Collection
    {
        return ($this->directives)();
    }

    public function directive($name) : ?Directive
    {
        return $this->directives()->first(function (Directive $directive) use ($name) {
            return $directive->name() === $name;
        });
    }
}