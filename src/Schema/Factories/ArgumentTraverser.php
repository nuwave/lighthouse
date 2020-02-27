<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\HasArgPathValue;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath;
use Nuwave\Lighthouse\Support\Contracts\HasErrorBuffer;
use Nuwave\Lighthouse\Support\Contracts\ProvidesRules;
use Nuwave\Lighthouse\Support\Traits\HasResolverArguments;
use Nuwave\Lighthouse\Support\Utils;

class ArgumentTraverser
{
    use HasResolverArguments;

    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory
     */
    protected $directiveFactory;

    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\ArgumentFactory
     */
    protected $argumentFactory;

    /**
     * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory
     */
    protected $typedArgs;

    /**
     * A snapshot of the arguments that are passed to "handleArgDirectives".
     *
     * This is used to pause and resume the evaluation of arg directives
     * before and after validation.
     *
     * @var mixed[]
     */
    protected $handleArgDirectivesSnapshots = [];

    /**
     * @param  \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory  $directiveFactory
     * @param  \Nuwave\Lighthouse\Schema\Factories\ArgumentFactory  $argumentFactory
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory  $typedArgs
     * @return void
     */
    public function __construct(
        DirectiveFactory $directiveFactory,
        ArgumentFactory $argumentFactory,
        ArgumentSetFactory $typedArgs
    ) {
        $this->directiveFactory = $directiveFactory;
        $this->argumentFactory = $argumentFactory;
        $this->typedArgs = $typedArgs;
    }

    public function handle(array $argumentMap): array
    {
        foreach ($argumentMap as $name => $argumentValue) {
            $this->handleArgDirectivesRecursively(
                $argumentValue['type'],
                $argumentValue['astNode'],
                [$name]
            );
        }
    }

    /**
     * Handle the ArgMiddleware.
     *
     * @param  \GraphQL\Type\Definition\InputType  $type
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $astNode
     * @param  mixed[]  $argumentPath
     * @return void
     */
    protected function handleArgDirectivesRecursively(
        InputType $type,
        InputValueDefinitionNode $astNode,
        array $argumentPath
    ): void {
        if ($type instanceof NonNull) {
            $this->handleArgDirectivesRecursively(
                $type->getWrappedType(),
                $astNode,
                $argumentPath
            );

            return;
        }

        $directives = $this->passResolverArguments(
            $this->directiveFactory->createAssociatedDirectivesOfType($astNode, ArgDirective::class)
        );

        // Handle the argument itself. At this point, it can be wrapped
        // in a list or an input object
        $this->handleArgWithAssociatedDirectives($type, $astNode, $directives, $argumentPath);

        // If we no value or null is given, we bail here to prevent
        // infinitely going down a chain of nested input objects
        if (! $this->argValueExists($argumentPath) || $this->argValue($argumentPath) === null) {
            return;
        }

        if ($type instanceof InputObjectType) {
            $inputDirectives = $this->directiveFactory->createAssociatedDirectives($type->astNode);
            $this->handleArgWithAssociatedDirectives($type, $astNode, $inputDirectives, $argumentPath);

            foreach ($type->getFields() as $field) {
                $this->handleArgDirectivesRecursively(
                    $field->type,
                    $field->astNode,
                    array_merge($argumentPath, [$field->name])
                );
            }
        }

        if ($type instanceof ListOfType) {
            foreach ($this->argValue($argumentPath) as $index => $value) {
                // here we are passing by reference so the `$argValue[$key]` is intended.
                $this->handleArgDirectivesRecursively(
                    $type->ofType,
                    $astNode,
                    array_merge($argumentPath, [$index])
                );
            }
        }
    }

    /**
     * @param  \GraphQL\Type\Definition\InputType  $type
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $astNode
     * @param  \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\Directive>  $directives
     * @param  mixed[]  $argumentPath
     * @return void
     */
    protected function handleArgWithAssociatedDirectives(
        InputType $type,
        InputValueDefinitionNode $astNode,
        Collection $directives,
        array $argumentPath
    ): void {
        $isArgDirectiveForArray = Utils::instanceofMatcher(ArgDirectiveForArray::class);

        $this->handleArgDirectives(
            $astNode,
            $argumentPath,
            $type instanceof ListOfType
                ? $directives->filter($isArgDirectiveForArray)
                : $directives->reject($isArgDirectiveForArray)
        );
    }

    /**
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $astNode
     * @param  mixed[]  $argumentPath
     * @param  \Illuminate\Support\Collection  $directives
     * @return void
     */
    protected function handleArgDirectives(
        InputValueDefinitionNode $astNode,
        array $argumentPath,
        Collection $directives
    ): void {
        if ($directives->isEmpty()) {
            return;
        }

        $directives->each(function (Directive $directive) use ($argumentPath): void {
            if ($directive instanceof HasErrorBuffer) {
                $directive->setErrorBuffer($this->validationErrorBuffer);
            }
            if ($directive instanceof HasArgumentPath) {
                $directive->setArgumentValue($argumentPath);
            }
            if ($directive instanceof HasArgPathValue) {
                $directive->setArgPathValue($this->argValue($argumentPath));
            }
        });

        // Remove the directive from the list to avoid evaluating the same directive twice
        while ($directive = $directives->shift()) {
            // Pause the iteration once we hit any directive that has to do
            // with validation. We will resume running through the remaining
            // directives later, after we completed validation
            if ($directive instanceof ProvidesRules) {
                $validators = $this->gatherValidationDirectives($directives);

                $validators->push($directive);
                foreach ($validators as $validator) {
                    // We gather the rules from all arguments and then run validation in one full swoop
                    $this->rules = array_merge_recursive($this->rules, $validator->rules());
                    $this->messages = array_merge_recursive($this->messages, $validator->messages());
                }

                break;
            }

            if ($directive instanceof ArgTransformerDirective && $this->argValueExists($argumentPath)) {
                $this->setArgValue(
                    $argumentPath,
                    $directive->transform($this->argValue($argumentPath))
                );
            }
        }

        // If directives remain, snapshot the state that we are in now
        // to allow resuming after validation has run
        if ($directives->isNotEmpty()) {
            $this->handleArgDirectivesSnapshots[] = [$astNode, $argumentPath, $directives];
        }
    }

    protected function gatherValidationDirectives(Collection &$directives): Collection
    {
        // We only get the validator directives that are directly following on the latest validator
        // directive. If we'd get all validator directives and merge them together, it wouldn't
        // be possible anymore to mutate the input with argument transformer directives.
        $validators = new Collection();
        while ($directive = $directives->first()) {
            if ($directive instanceof ProvidesRules) {
                $validators->push($directives->shift());
            } else {
                return $validators;
            }
        }

        return $validators;
    }

    protected function argValueExists(array $argumentPath): bool
    {
        return Arr::has($this->args, implode('.', $argumentPath));
    }

    protected function setArgValue(array $argumentPath, $value): array
    {
        return Arr::set($this->args, implode('.', $argumentPath), $value);
    }

    protected function argValue(array $argumentPath)
    {
        return Arr::get($this->args, implode('.', $argumentPath));
    }
}
