<?php


namespace Nuwave\Lighthouse\Types;


use Closure;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Support\Pipeline;

class Argument
{
    use HasAttributes;

    protected $name;

    protected $description;

    protected $type;

    protected $defaultValue;

    protected $directives;

    protected $directiveRegistry;

    /**
     * Argument constructor.
     *
     * @param DirectiveRegistry $directiveRegistry
     * @param string $name
     * @param null|string $description
     * @param Type $type
     * @param string|null $defaultValue
     * @param Closure|null $directives
     */
    public function __construct(DirectiveRegistry $directiveRegistry, string $name, ?string $description, Type $type, string $defaultValue = null, Closure $directives = null)
    {
        $this->name = $name;
        $this->description = $description;
        $this->type = $type;
        $this->defaultValue = $defaultValue;
        $this->directives = $directives ?? function() {return collect();};
        $this->directiveRegistry = $directiveRegistry;
    }


    public function description() : ?string
    {
        return $this->description;
    }

    public function name() : string
    {
        return $this->name;
    }

    public function type() : Type
    {
        return $this->type;
    }

    public function defaultValue() : ?string
    {
        return $this->defaultValue;
    }

    public function directives() : Collection
    {
        return($this->directives)();
    }

    public function resolver($data) : Closure
    {
        return function () use ($data) {
            return app(Pipeline::class)
                ->send($data)
                ->through($this->directiveRegistry->getFromDirectives($this->directives()))
                ->via('handleArgument')
                ->then(function($value) {
                    return $value;
                });
        };
    }

    public function hasResolver()
    {
        return $this->directives()->isNotEmpty();
    }
}
