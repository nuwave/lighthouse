<?php


namespace Nuwave\Lighthouse\Schema\Traits;


use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Directive;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Support\Contracts\Directives\ManipulatorDirective;

trait HasDirectives
{
    protected $directives;

    /** @var DirectiveRegistry */
    protected $directiveRegistry;

    public function directives() : Collection
    {
        return ($this->directives)();
    }

    public function directive(string $name) : ?Directive
    {
        return $this->directives()->first(function (Directive $directive) use ($name) {
            return $directive->name() === $name;
        });
    }

    public function manipulatorDirectives() : Collection
    {
        return $this->directiveRegistry->getFromDirectives($this->directives())->filter(function (\Nuwave\Lighthouse\Support\Contracts\Directive $directive) {
            return $directive instanceof ManipulatorDirective;
        });
    }
}
