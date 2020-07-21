<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\Parser;
use HaydenPierce\ClassFinder\ClassFinder;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Support\Contracts\Directive;

class SchemaDirectives
{
    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $eventsDispatcher;

    public function __construct(EventsDispatcher $eventsDispatcher)
    {
        $this->eventsDispatcher = $eventsDispatcher;
    }

    /**
     * A list of namespaces with directives in descending priority.
     *
     * @return array<string>
     */
    public function namespaces(): array
    {
        // When looking for a directive by name, the namespaces are tried in order
        return (
            new Collection([
                // User defined directives (top priority)
                config('lighthouse.namespaces.directives'),

                // Plugin developers defined directives
                $this->eventsDispatcher->dispatch(new RegisterDirectiveNamespaces),

                // Lighthouse defined directives
                'Nuwave\\Lighthouse\\Schema\\Directives',
            ]))
            ->flatten()
            ->filter()
            ->all();
    }

    /**
     * Scan the namespaces for directive classes.
     *
     * @return array<string, class-string<\Nuwave\Lighthouse\Support\Contracts\Directive>>
     */
    public function classes(): array
    {
        $directives = [];

        foreach ($this->namespaces() as $directiveNamespace) {
            /** @var array<class-string> $classesInNamespace */
            $classesInNamespace = ClassFinder::getClassesInNamespace($directiveNamespace);

            foreach ($classesInNamespace as $class) {
                $reflection = new \ReflectionClass($class);
                if (! $reflection->isInstantiable()) {
                    continue;
                }

                if (! is_a($class, Directive::class, true)) {
                    continue;
                }
                /** @var class-string<\Nuwave\Lighthouse\Support\Contracts\Directive> $class */
                $name = DirectiveLocator::directiveName($class);

                // The directive was already found, so we do not add it twice
                if (isset($directives[$name])) {
                    continue;
                }

                $directives[$name] = $class;
            }
        }

        return $directives;
    }

    /**
     * Return the parsed definitions for all directive classes.
     *
     * @return array<\GraphQL\Language\AST\DirectiveDefinitionNode>
     */
    public function definitions(): array
    {
        $definitions = [];

        /** @var \Nuwave\Lighthouse\Support\Contracts\Directive $directiveClass */
        foreach($this->classes() as $directiveClass) {
            $definitions []= Parser::directiveDefinition($directiveClass::definition());
        }

        return $definitions;
    }
}
