<?php


namespace Nuwave\Lighthouse\Types;



use ArrayAccess;
use Closure;
use Exception;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Traits\CanManipulate;
use Nuwave\Lighthouse\Schema\Traits\HasDescription;
use Nuwave\Lighthouse\Schema\Traits\HasDirectives;
use Nuwave\Lighthouse\Schema\Traits\HasName;
use Nuwave\Lighthouse\Types\Scalar\IntType;
use Nuwave\Lighthouse\Types\Scalar\StringType;

abstract class Type implements ArrayAccess
{
    use HasAttributes, HasDirectives, HasName, CanManipulate, HasDescription;

    protected $fields;

    /** @var null|Collection */
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
        $this->resolvedFields = null;
    }

    public function fields() : Collection
    {
        if(is_null($this->resolvedFields)) {
            $this->resolvedFields = ($this->fields)();
        }
        return $this->resolvedFields;
    }

    public function resolvedFields() : ?Collection
    {
        return $this->resolvedFields;
    }

    public function resolvedField($name) : ?Field
    {
        return $this->resolvedFields()->get($name);
    }

    public function field($name) : ?Field
    {
        return $this->fields()->firstWhere('name', $name);
    }

    public function addField(Field $field)
    {
        $this->manipulatable();

        if($this->fields()->pluck('name')->contains($field->name())) {
            throw new Exception("Cannot add Field which already exist.");
        }

        $this->resolvedFields->push($field);
    }

    public static function string() : StringType
    {
        return StringType::instance();
    }

    public static function integer() : IntType
    {
        return IntType::instance();
    }
}
