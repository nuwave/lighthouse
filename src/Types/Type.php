<?php


namespace Nuwave\Lighthouse\Types;



use ArrayAccess;
use Closure;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Traits\HasDirectives;
use Nuwave\Lighthouse\Schema\Traits\HasName;

abstract class Type implements ArrayAccess
{
    use HasAttributes, HasDirectives, HasName;

    protected $description;

    protected $fields;

    protected $resolvedFields;

    /**
     * Type constructor.
     *
     * @param string $name
     * @param null|string $description
     * @param Closure $fields
     * @param Closure|null $directives
     */
    public function __construct(?string $name, ?string $description, Closure $fields = null, Closure $directives = null)
    {
        $this->name = $name;
        $this->description = $description;
        $this->fields = $fields ?? function() {return collect();};
        $this->directives = $directives ?? function() {return collect();};
        $this->resolvedFields = collect();
    }

    public function fields() : Collection
    {
        return $this->resolvedFields = ($this->fields)();
    }

    public function resolvedFields() : Collection
    {
        return $this->resolvedFields;
    }

    public function resolvedField($name) : ?Field
    {
        return $this->resolvedFields()->get($name);
    }

    public function field($name) : ?Field
    {
        return $this->fields()->get($name);
    }

    public function description() : ?string
    {
        return $this->description;
    }
}
