<?php


namespace Nuwave\Lighthouse\Schema;


use Nuwave\Lighthouse\Types\Field;
use Nuwave\Lighthouse\Types\Type;

class ManipulatorInfo
{
    protected $schema;
    private $field;
    private $type;

    /**
     * ManipulatorInfo constructor.
     *
     * @param $schema
     */
    public function __construct($schema)
    {
        $this->schema = $schema;
    }

    public function field() : Field
    {
        return $this->schema()->type($this->type)->resolvedField($this->field);
    }

    public function setType(Type $type) : ManipulatorInfo
    {
        $this->type = $type->name();
        return $this;
    }

    public function setField(Field $field) : ManipulatorInfo
    {
        $this->field = $field->name();
        return $this;
    }

    public function schema() : Schema
    {
        return $this->schema;
    }
}