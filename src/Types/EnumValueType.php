<?php


namespace Nuwave\Lighthouse\Types;


class EnumValueType extends Type
{
    protected $value;

    /**
     * EnumValueType constructor.
     *
     * @param string $name
     * @param string $description
     * @param string $value
     */
    public function __construct(string $name, string $description, string $value)
    {
        parent::__construct($name, $description);
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function value(): string
    {
        return $this->value;
    }



}