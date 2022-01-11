<?php

namespace Nuwave\Lighthouse\Deprecation;

use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\ValidationContext;

/**
 * @phpstan-type DeprecationHandler callable(array<string, true>): void
 */
class DeprecationValidationRule extends ValidationRule
{
    /**
     * @var array<string, true>
     */
    protected $deprecations = [];

    /**
     * @var DeprecationHandler
     */
    protected $deprecationHandler;

    /**
     * @param DeprecationHandler $deprecationHandler
     */
    public function __construct(callable $deprecationHandler)
    {
        $this->deprecationHandler = $deprecationHandler;
    }

    public function getVisitor(ValidationContext $context): array
    {
        return [
            NodeKind::FIELD => function (FieldNode $node) use ($context): void {
                $field = $context->getFieldDef();
                if (null === $field) {
                    return;
                }

                if ($field->isDeprecated()) {
                    $parent = $context->getParentType();
                    if (null === $parent) {
                        return;
                    }

                    $this->deprecations["{$parent->name}.{$field->name}"] = true;
                }
            },
            NodeKind::ENUM => function (EnumValueNode $node) use ($context): void {
                $enum = $context->getInputType();
                if (! $enum instanceof EnumType) {
                    return;
                }

                $value = $enum->getValue($node->value);
                if (null === $value) {
                    return;
                }

                if ($value->isDeprecated()) {
                    $this->deprecations["{$enum->name}.{$value->name}"] = true;
                }
            },
            NodeKind::OPERATION_DEFINITION => [
                'leave' => function () {
                    ($this->deprecationHandler)($this->deprecations);
                },
            ],
        ];
    }
}
