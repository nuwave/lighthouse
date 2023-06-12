<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Deprecation;

use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\QueryValidationContext;
use GraphQL\Validator\Rules\ValidationRule;

/**
 * @experimental not enabled by default, not guaranteed to be stable
 *
 * @phpstan-type DeprecationHandler callable(array<string, \Nuwave\Lighthouse\Deprecation\DeprecatedUsage>): void
 */
class DetectDeprecatedUsage extends ValidationRule
{
    /** @var array<string, \Nuwave\Lighthouse\Deprecation\DeprecatedUsage> */
    protected array $deprecations = [];

    /** @var DeprecationHandler */
    protected $deprecationHandler;

    /** @param  DeprecationHandler  $deprecationHandler */
    public function __construct(callable $deprecationHandler)
    {
        $this->deprecationHandler = $deprecationHandler;
    }

    /** @param  DeprecationHandler  $deprecationHandler */
    public static function handle(callable $deprecationHandler): void
    {
        DocumentValidator::addRule(new static($deprecationHandler));
    }

    public function getVisitor(QueryValidationContext $context): array
    {
        // @phpstan-ignore-next-line NodeVisitor does not know about the mapping between node kind and node type
        return [
            NodeKind::FIELD => function (FieldNode $_) use ($context): void {
                $field = $context->getFieldDef();
                if ($field === null) {
                    return;
                }

                $deprecationReason = $field->deprecationReason;
                if ($deprecationReason !== null) {
                    $parent = $context->getParentType();
                    if (! $parent instanceof NamedType) {
                        return;
                    }

                    $this->registerDeprecation("{$parent->name()}.{$field->name}", $deprecationReason);
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
                if ($deprecationReason !== null) {
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
        $usage = $this->deprecations[$element] ??= new DeprecatedUsage($reason);
        ++$usage->count;
    }
}
