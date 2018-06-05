<?php


namespace Nuwave\Lighthouse\Types;


use Closure;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Pipeline;

class Field
{
    use HasAttributes;

    public $name;

    protected $description;

    protected $type;

    protected $arguments;

    protected $directives;

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
        $this->resolver = $resolver ?? function($result) {return $result;};
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

    public function directives()
    {
        return($this->directives)();
    }

    public function resolver($result) : Closure
    {
        return function () use ($result) {
            $this->arguments()->each(function (Argument $argument) use (&$result) {
                $result = ($argument->resolver($result))();
            });

            // First resolve with supplied resolver
            $result = ($this->resolver)($result);

            // Then resolve with directives.
            $result = app(Pipeline::class)
                ->send($result)
                ->through(graphql()->directives()->getFromDirectives($this->directives()))
                ->via('handleField')
                ->then(function($value) {
                    return $value;
                });

            // Then resolve all resolvers from the arguments.
            $this->arguments()->each(function (Argument $argument) use (&$result) {
                $result = ($argument->resolver($result))();
            });
            return $result;
        };
    }


}