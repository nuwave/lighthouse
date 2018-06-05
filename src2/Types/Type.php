<?php


namespace Nuwave\Lighthouse\Types;



use ArrayAccess;
use Closure;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Directive;

abstract class Type implements ArrayAccess
{
    use HasAttributes;

    protected $name;

    protected $description;

    protected $fields;

    protected $directives;

    /**
     * Type constructor.
     *
     * @param string $name
     * @param null|string $description
     * @param Closure $fields
     * @param Closure|null $directives
     */
    public function __construct(string $name, ?string $description, Closure $fields = null, Closure $directives = null)
    {
        $this->name = $name;
        $this->description = $description;
        $this->fields = $fields ?? function() {return collect();};
        $this->directives = $directives ?? function() {return collect();};
    }

    public function fields() : Collection
    {
        return ($this->fields)();
    }

    public function field($name) : ?Field
    {
        return $this->fields()->get($name);
    }

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

    public function description() : ?string
    {
        return $this->description;
    }

    public function name() : string
    {
        return $this->name;
    }
}