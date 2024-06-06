<?php declare(strict_types=1);

namespace Tests\Utils\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

final class GlobalFieldMiddlewareDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
directive @globalFieldMiddleware on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->wrapResolver(
            fn (): callable
                // Must not crash
                => fn (): bool => $this->definitionNode instanceof FieldDefinitionNode
                    && $this->directiveArgValue('random') === null,
        );
    }
}
