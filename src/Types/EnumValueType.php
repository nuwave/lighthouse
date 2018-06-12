<?php


namespace Nuwave\Lighthouse\Types;


use Nuwave\Lighthouse\Schema\DirectiveRegistry;

class EnumValueType extends Type
{
    protected $value;

    /**
     * EnumValueType constructor.
     *
     * @param DirectiveRegistry $directiveRegistry
     * @param string $name
     * @param string $description
     * @param string $value
     */
    public function __construct(DirectiveRegistry $directiveRegistry, string $name, string $description, string $value)
    {
        parent::__construct($directiveRegistry, $name, $description);
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
