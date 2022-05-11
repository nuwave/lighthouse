<?php

namespace Nuwave\Lighthouse\Deprecation;

use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\ValidationContext;

/**
 * @experimental not enabled by default, not guaranteed to be stable
 *
 * @phpstan-type DeprecationHandler callable(array<string, \Nuwave\Lighthouse\Deprecation\DeprecatedUsage>): void
 */
class DetectDeprecatedUsage extends ValidationRule
{
    /**
     * @var array<string, \Nuwave\Lighthouse\Deprecation\DeprecatedUsage>
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

    /**
     * @param DeprecationHandler $deprecationHandler
     */
    public static function handle(callable $deprecationHandler): void
    {
        DocumentValidator::addRule(new static($deprecationHandler));
    }

    public function getVisitor(ValidationContext $context): array
    {
        return [
            NodeKind::FIELD => function (FieldNode $node) use ($context): void {
                $field = $context->getFieldDef();
                // @phpstan-ignore-next-line can be null, remove ignore with graphql-php 15
                if (null === $field) {
                    return;
                }

                $deprecationReason = $field->deprecationReason;
                if (null !== $deprecationReason) {
                    $parent = $context->getParentType();
                    if (null === $parent) {
                        return;
                    }

                    $this->registerDeprecation("{$parent->name}.{$field->name}", $deprecationReason);
                }
            },
            NodeKind::ENUM => function (EnumValueNode $node) use ($context): void {
                $enum = $context->getInputType();
                if (! $enum instanceof EnumType) {
                    return;
                }

                $value = $enum->getValue($node->value);
                if (! $value instanceof EnumValueDefinition) {
                    return;
                }

                $deprecationReason = $value->deprecationReason;
                if (null !== $deprecationReason) {
                    $this->registerDeprecation("{$enum->name}.{$value->name}", $deprecationReason);
                }
            },
            NodeKind::OPERATION_DEFINITION => [
                'leave' => function (): void {
                    ($this->deprecationHandler)($this->deprecations);
                },
            ],
        ];
    }

    protected function registerDeprecation(string $element, string $reason): void
    {
        if (! isset($this->deprecations[$element])) {
            $this->deprecations[$element] = new DeprecatedUsage($reason);
        }

        ++$this->deprecations[$element]->count;
    }
}
