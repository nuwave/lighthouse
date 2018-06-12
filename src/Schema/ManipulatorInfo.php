<?php


namespace Nuwave\Lighthouse\Schema;


use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Types\Argument;
use Nuwave\Lighthouse\Types\Field;
use Nuwave\Lighthouse\Types\Type;

class ManipulatorInfo
{
    private $field;
    private $type;
    private $argument;
    private $types;

    /**
     * ManipulatorInfo constructor.
     *
     * @param Collection $types
     * @param Type $type
     * @param Field|null $field
     * @param Argument|null $argument
     */
    public function __construct(Collection $types, Type $type, Field $field = null, Argument $argument = null)
    {
        $this->types = $types;
        $this->type = $type;
        $this->field = optional($field)->name();
        $this->argument = optional($argument)->name();
    }

    /**
     * Returns the argument of which the manipulator is currently
     * executing on.
     *
     * @return null|Argument
     */
    public function argument() : ?Argument
    {
        return $this->field()->argument($this->argument);
    }

    /**
     * Returns the field of which the manipulator is currently
     * executing on.
     *
     * @return null|Field
     */
    public function field() : ?Field
    {
        return $this->type()->field($this->field);
    }

    /**
     * Returns the type of which the manipulator is currently
     * executing on.
     *
     * @return Type
     */
    public function type(): Type
    {
        return $this->type;
    }

    /**
     * Adds a type to the schema builder.
     *
     * This could also be an extension to a already existing type.
     * Eg. if you would like to add a field to User type, then you
     * can either modify the existing user type or add a new user
     * type and give that the field. It will be merged later on.
     *
     * @param Type $type
     * @return ManipulatorInfo
     */
    public function addType(Type $type) : ManipulatorInfo
    {
        $this->types->push($type);
        return $this;
    }

    /**
     * @return Collection
     */
    public function getTypes(): Collection
    {
        return $this->types;
    }
}
