<?php


namespace Nuwave\Lighthouse\Types;


use Closure;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\ResolveInfo;
use Nuwave\Lighthouse\Schema\Traits\HasDirectives;
use Nuwave\Lighthouse\Support\Pipeline;

class Field
{
    use HasAttributes, HasDirectives;

    public $name;

    protected $description;

    protected $type;

    protected $arguments;

    protected $resolver;

    /**
     * Field constructor.
     *
     * @param string $name
     * @param string $description
     * @param Type $type
     * @param Closure $arguments
     * @param Closure|null $directives
     * @param Closure|null $resolver
     */
    public function __construct(
        string $name,
        ?string $description,
        Type $type,
        Closure $arguments = null,
        Closure $directives = null,
        Closure $resolver = null
    )
    {
        $this->name = $name;
        $this->description = $description;
        $this->type = $type;
        $this->arguments = $arguments ?? function() {return collect();};
        $this->directives = $directives ?? function() {return collect();};
        $this->resolver = $resolver;
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

    public function arguments() : Collection
    {
        return($this->arguments)();
    }

    public function argument($name) : ?Argument
    {
        return $this->arguments()->first(function (Argument $argument) use ($name) {
            return $argument->name() == $name;
        });
    }

    public function hasArgument($name)
    {
        return !is_null($this->argument($name));
    }

    public function hasResolver() : bool
    {
        // Checks if arguments has any resolvers, then check if
        // Directives on the field has any resolver and then
        // check if the field itself has a resolver.
        return $this->arguments()->filter(function (Argument $argument) {
            return $argument->hasResolver();
        })->isNotEmpty() ||
            $this->directives()->isNotEmpty() ||
            !is_null($this->resolver);
    }

    public function resolver(ResolveInfo $resolveInfo) : Closure
    {
        return function () use ($resolveInfo) {
            $this->arguments()->each(function (Argument $argument) use (&$resolveInfo) {
                $resolveInfo = ($argument->resolver($resolveInfo))();
            });

            // First resolve with supplied resolver
            if(!is_null($this->resolver)) {
                $resolveInfo = ($this->resolver)($resolveInfo);
            }

            // Then resolve with directives.
            $resolveInfo = app(Pipeline::class)
                ->send($resolveInfo)
                ->through(graphql()->directives()->getFromDirectives($this->directives()))
                ->via('handleField')
                ->then(function($value) {
                    return $value;
                });

            // Then resolve all resolvers from the arguments.
            $this->arguments()->each(function (Argument $argument) use (&$resolveInfo) {
                $resolveInfo = ($argument->resolver($resolveInfo))();
            });

            $resolveInfo->runAfters();

            return $resolveInfo;
        };
    }


}