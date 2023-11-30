<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Type\Schema;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;

class FederationHelper
{
    /** @return array<string> */
    public static function directivesToCompose(Schema $schema): array
    {
        $composedDirectives = [];

        foreach ($schema->extensionASTNodes as $extension) {
            foreach (ASTHelper::directiveDefinitions($extension, 'composeDirective') as $directive) {
                $name = ASTHelper::directiveArgValue($directive, 'name');

                if (! is_string($name)) {
                    continue;
                }

                $composedDirectives[] = ltrim($name, '@');
            }
        }

        return $composedDirectives;
    }

    /** @return array<DirectiveNode> */
    public static function schemaExtensionDirectives(Schema $schema): array
    {
        $schemaDirectives = [];

        foreach ($schema->extensionASTNodes as $extension) {
            foreach ($extension->directives as $directive) {
                assert($directive instanceof DirectiveNode);

                $schemaDirectives[] = $directive;
            }
        }

        return $schemaDirectives;
    }
}
