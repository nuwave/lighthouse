<?php

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Type\Definition\ObjectType;
use Nuwave\Lighthouse\Events\ValidateSchema;
use Nuwave\Lighthouse\Exceptions\FederationException;
use Nuwave\Lighthouse\Federation\Directives\KeyDirective;
use Nuwave\Lighthouse\Schema\DirectiveLocator;

class SchemaValidator
{
    /**
     * @var \Nuwave\Lighthouse\Schema\DirectiveLocator
     */
    protected $directiveLocator;

    public function __construct(DirectiveLocator $directiveLocator)
    {
        $this->directiveLocator = $directiveLocator;
    }

    public function __invoke(ValidateSchema $validateSchema)
    {
        $schema = $validateSchema->schema;

        foreach ($schema->getTypeMap() as $type) {
            if ($type instanceof ObjectType) {
                $this->validateObjectType($type);
            }
        }
    }

    protected function validateObjectType(ObjectType $type): void
    {
        $ast = $type->astNode;
        if ($ast !== null) {
            $directives = $this->directiveLocator->associated($ast);

            /** @var \Nuwave\Lighthouse\Support\Contracts\Directive $directive */
            foreach ($directives as $directive) {
                if ($directive instanceof KeyDirective) {
                    $fields = $directive->fields();

                    foreach ($fields->selections as $selection) {
                        if (! $selection instanceof FieldNode) {
                            throw new FederationException('Must only use field selections in the `fields` argument of @key, got: ' . $selection->kind);
                        }

                        try {
                            // Throws if the field is not defined
                            $type->getField($selection->name->value);
                        } catch (InvariantViolation $i) {
                            throw new FederationException($i->getMessage(), $i->getCode(), $i);
                        }

                        // TODO recurse when field definitions are nested
                    }
                }
            }
        }
    }
}
