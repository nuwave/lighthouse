<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Illuminate\Support\Collection;
use GraphQL\Type\Definition\InputType;
use Nuwave\Lighthouse\Support\NoValue;
use GraphQL\Type\Definition\ListOfType;
use Nuwave\Lighthouse\Support\Pipeline;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Type\Definition\InputObjectType;
use Nuwave\Lighthouse\Execution\ErrorBuffer;
use Nuwave\Lighthouse\Execution\QueryFilter;
use GraphQL\Type\Definition\InputObjectField;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\HasErrorBuffer;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath;
use Nuwave\Lighthouse\Support\Traits\HasResolverArguments;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgValidationDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;

class FieldFactory
{
    use HasResolverArguments;

    /**
     * @var FieldValue
     */
    protected $fieldValue;

    /**
     * @var QueryFilter
     */
    protected $queryFilter;

    /**
     * @var DirectiveRegistry
     */
    protected $directiveRegistry;

    /**
     * @var ArgumentFactory
     */
    protected $argumentFactory;

    /**
     * @var Pipeline
     */
    protected $pipeline;

    /**
     * @var array
     */
    protected $currentRules = [];

    /**
     * @var array
     */
    protected $currentMessages = [];

    /**
     * @var ErrorBuffer
     */
    protected $currentValidationErrorBuffer;

    /**
     * @var ArgumentValue
     */
    protected $currentArgumentValueInstance;

    /**
     * @var ArgDirective[]
     */
    protected $currentHandlerArgsOfArgDirectivesAfterValidationDirective = [];

    /**
     * @param DirectiveRegistry $directiveRegistry
     * @param ArgumentFactory   $argumentFactory
     * @param Pipeline          $pipeline
     */
    public function __construct(DirectiveRegistry $directiveRegistry, ArgumentFactory $argumentFactory, Pipeline $pipeline)
    {
        $this->directiveRegistry = $directiveRegistry;
        $this->argumentFactory = $argumentFactory;
        $this->pipeline = $pipeline;
    }

    /**
     * Convert a FieldValue to an executable FieldDefinition.
     *
     * @param FieldValue $fieldValue
     *
     * @throws DirectiveException
     *
     * @return array Configuration array for a FieldDefinition
     */
    public function handle(FieldValue $fieldValue): array
    {
        $this->fieldValue = $fieldValue;
        $fieldDefinitionNode = $fieldValue->getField();

        // Get the initial resolver from the FieldValue
        // This is either the webonyx default resolver or provided by a directive
        if ($fieldResolver = $this->directiveRegistry->fieldResolver($fieldDefinitionNode)) {
            $this->fieldValue = $fieldResolver->resolveField($fieldValue);
        }
        $resolver = $this->fieldValue->getResolver();

        $argumentValues = $this->getArgumentValues();
        // No need to do handle the arguments if there are no
        // arguments defined for the field
        if ($argumentValues->isNotEmpty()) {
            $this->queryFilter = QueryFilter::getInstance($this->fieldValue);

            $resolver = $this->decorateResolverWithArgs($resolver, $argumentValues);
        }

        $this->fieldValue->setResolver($resolver);
        $resolverWithMiddleware = $this->pipeline
            ->send($this->fieldValue)
            ->through(
                $this->directiveRegistry->fieldMiddleware($fieldDefinitionNode)
            )
            ->via('handleField')
            ->then(
                function (FieldValue $fieldValue): FieldValue {
                    return $fieldValue;
                }
            )
            ->getResolver();

        // To see what is allowed here, look at the validation rules in
        // GraphQL\Type\Definition\FieldDefinition::getDefinition()
        $fieldDefinition = [
            'name' => $fieldDefinitionNode->name->value,
            'type' => $this->fieldValue->getReturnType(),
            'args' => $this->getInputValueDefinitions($argumentValues),
            'resolve' => $resolverWithMiddleware,
            'description' => data_get($fieldDefinitionNode->description, 'value'),
            'complexity' => $this->fieldValue->getComplexity(),
        ];

        return $fieldDefinition;
    }

    /**
     * Transform the ArgumentValues into the final InputValueDefinitions.
     *
     * @param Collection<ArgumentValue> $argumentValues
     *
     * @return InputValueDefinitionNode[]
     */
    protected function getInputValueDefinitions(Collection $argumentValues): array
    {
        return $argumentValues
            ->mapWithKeys(function (ArgumentValue $argumentValue) {
                return [
                    $argumentValue->getName() => $this->argumentFactory->handle($argumentValue),
                ];
            })
            ->all();
    }

    /**
     * Get a collection of the fields argument definitions.
     *
     * @return Collection<ArgumentValue>
     */
    protected function getArgumentValues(): Collection
    {
        return collect($this->fieldValue->getField()->arguments)
            ->map(function (InputValueDefinitionNode $inputValueDefinition) {
                return new ArgumentValue($inputValueDefinition, $this->fieldValue);
            });
    }

    /**
     * Call `ArgMiddleware::handleArgument` at the resolving time.
     *
     * This may be used to transform the arguments, log them or do anything else
     * before they reach the final resolver.
     *
     * @param \Closure                  $resolver
     * @param Collection<ArgumentValue> $argumentValues
     *
     * @return \Closure
     */
    public function decorateResolverWithArgs(\Closure $resolver, Collection $argumentValues): \Closure
    {
        return function ($root, array $args, $context = null, ResolveInfo $resolveInfo) use ($resolver, $argumentValues) {
            $this->currentValidationErrorBuffer = resolve(ErrorBuffer::class)->setErrorType('validation');

            $this->setResolverArguments($root, $args, $context, $resolveInfo);

            $argumentValues->each(
                function (ArgumentValue $argumentValue) use (&$args) {
                    $this->currentArgumentValueInstance = $argumentValue;

                    $noValuePassedForThisArgument = ! array_key_exists($argumentValue->getName(), $args);

                    // because we are passing by reference, we need a variable to contain the null value.
                    if ($noValuePassedForThisArgument) {
                        $argValue = new NoValue();
                    } else {
                        $argValue = &$args[$argumentValue->getName()];
                    }

                    $this->handleArgWithAssociatedDirectivesRecursively(
                        $argumentValue->getType(),
                        $argValue,
                        $argumentValue->getAstNode(),
                        [$argumentValue->getName()]
                    );
                }
            );

            // Validate arguments placed before `ValidationDirective`s
            $this->validateArgumentsBeforeValidationDirectives($root, $args, $context, $resolveInfo);

            // Handle `ArgDirective`s after `ValidationDirective`s
            $this->handleArgDirectivesAfterValidationDirectives($root, $args, $context, $resolveInfo);

            // We (ab)use the ResolveInfo as a way of passing down the query filter
            // to the final resolver
            $resolveInfo->queryFilter = $this->queryFilter;

            return $resolver($root, $args, $context, $resolveInfo);
        };
    }

    /**
     * Handle the ArgMiddleware.
     *
     * @param InputType                     $type
     * @param mixed                         $argValue
     * @param InputValueDefinitionNode|null $astNode
     * @param array                         $argumentPath
     */
    protected function handleArgWithAssociatedDirectivesRecursively(InputType $type, &$argValue, InputValueDefinitionNode $astNode, array $argumentPath)
    {
        if ($argValue instanceof NoValue || null === $argValue) {
            // No rules apply to input objects themselves, so we can stop looking further
            if ($type instanceof InputObjectType) {
                return;
            }

            // There might still be some rules for the list itself
            if ($type instanceof ListOfType) {
                $this->handleArgWithAssociatedDirectives($astNode, $argValue, $argumentPath, ArgDirectiveForArray::class);
                // No need to consider the rules for the elements of the list, since we know it is empty
                return;
            }

            // all other leaf types
            $this->handleArgWithAssociatedDirectives($astNode, $argValue, $argumentPath);

            return;
        }

        if ($type instanceof InputObjectType) {
            collect($type->getFields())
                ->each(
                    function (InputObjectField $field) use ($argumentPath, &$argValue) {
                        $noValuePassedForThisArgument = ! array_key_exists($field->name, $argValue);

                        // because we are passing by reference, we need a variable to contain the null value.
                        if ($noValuePassedForThisArgument) {
                            $value = new NoValue();
                        } else {
                            $value = &$argValue[$field->name];
                        }

                        $this->handleArgWithAssociatedDirectivesRecursively(
                            $field->type,
                            $value,
                            $field->astNode,
                            $this->addPath($argumentPath, $field->name)
                        );
                    }
                );

            return;
        }

        if ($type instanceof ListOfType) {
            $argValue = $this->handleArgWithAssociatedDirectives($astNode, $argValue, $argumentPath, ArgDirectiveForArray::class);

            foreach ($argValue as $key => $fieldValue) {
                // here we are passing by reference so the `$argValue[$key]` is intended.
                $this->handleArgWithAssociatedDirectivesRecursively(
                    $type->ofType,
                    $argValue[$key],
                    $astNode,
                    $this->addPath($argumentPath, $key)
                );
            }

            return;
        }

        // all other leaf types
        $argValue = $this->handleArgWithAssociatedDirectives($astNode, $argValue, $argumentPath);
    }

    /**
     * @param InputValueDefinitionNode $astNode
     * @param mixed                    $argValue
     * @param array                    $argumentPath
     * @param string                   $mustImplementClass
     *
     * @return mixed
     */
    protected function handleArgWithAssociatedDirectives(
        InputValueDefinitionNode $astNode,
        $argValue,
        array $argumentPath,
        string $mustImplementClass = null
    ) {
        $directives = $this->directiveRegistry->argDirectives($astNode);

        if ($mustImplementClass) {
            $directives = $directives->filter(function ($directive) use ($mustImplementClass) {
                return $directive instanceof $mustImplementClass;
            });
        }

        return $this->handleArgDirectives($astNode, $argValue, $argumentPath, $directives);
    }

    /**
     * @param InputValueDefinitionNode $astNode
     * @param mixed                    $argumentValue
     * @param array                    $argumentPath
     * @param Collection|null          $directives
     *
     * @return mixed
     */
    protected function handleArgDirectives(
        InputValueDefinitionNode $astNode,
        $argumentValue,
        array $argumentPath,
        Collection $directives
    ) {
        if ($directives->isEmpty()) {
            return $argumentValue;
        }

        $this->prepareDirectives($astNode, $argumentPath, $directives);

        foreach ($directives as $directive) {
            // Remove the directive from the list to avoid evaluating
            // the same directive twice
            $directives->shift();

            // Pause the iteration once we hit any directive that has to do
            // with validation. We will resume running through the remaining
            // directives later, after we completed validation
            if ($directive instanceof ArgValidationDirective) {
                $this->collectRulesAndMessages($directive, $argumentPath);
                break;
            }

            if ($directive instanceof ArgTransformerDirective && ! $argumentValue instanceof NoValue) {
                $argumentValue = $directive->transform($argumentValue);
            }

            if ($directive instanceof ArgFilterDirective) {
                $this->injectArgumentFilter($directive, $astNode);
            }
        }

        // If directives remain, snapshot the state that we are in now
        // to allow resuming after validation has run
        if ($directives->count()) {
            $this->currentHandlerArgsOfArgDirectivesAfterValidationDirective[] = [$astNode, $argumentValue, $argumentPath, $directives];
        }

        return $argumentValue;
    }

    /**
     * @param InputValueDefinitionNode $astNode
     * @param array                    $argumentPath
     * @param Collection               $directives
     */
    protected function prepareDirectives(InputValueDefinitionNode $astNode, array $argumentPath, Collection $directives)
    {
        $directives->each(function (Directive $directive) use ($astNode, $argumentPath) {
            if ($directive instanceof HasErrorBuffer) {
                $directive->setErrorBuffer($this->currentValidationErrorBuffer);
            }

            if ($directive instanceof HasArgumentPath) {
                $directive->setArgumentPath($argumentPath);
            }
        });
    }

    /**
     * @param ArgValidationDirective $directive
     * @param array                  $argumentPath
     */
    protected function collectRulesAndMessages(ArgValidationDirective $directive, array $argumentPath)
    {
        $this->currentRules = array_merge($this->currentRules, $directive->getRules());
        $this->currentMessages = array_merge($this->currentMessages, $directive->getMessages());
    }

    /**
     * @param ArgFilterDirective       $argFilterDirective
     * @param InputValueDefinitionNode $inputValueDefinition
     */
    protected function injectArgumentFilter(ArgFilterDirective $argFilterDirective, InputValueDefinitionNode $inputValueDefinition)
    {
        $argumentName = $inputValueDefinition->name->value;
        $directiveDefinition = ASTHelper::directiveDefinition($inputValueDefinition, $argFilterDirective->name());
        $columnName = ASTHelper::directiveArgValue($directiveDefinition, 'key', $argumentName);

        $this->queryFilter->addArgumentFilter(
            $argumentName,
            $columnName,
            $argFilterDirective
        );
    }

    /**
     * Append a path to the base path to create a new path.
     *
     * @param array      $basePath
     * @param string|int $pathToBeAdded
     *
     * @return array
     */
    protected function addPath(array $basePath, $pathToBeAdded): array
    {
        $basePath[] = $pathToBeAdded;

        return $basePath;
    }

    /**
     * Gather the error messages from each type of directive.
     *
     * @return string
     */
    protected function getValidationErrorMessage(): string
    {
        $path = implode(
            '.',
            $this->resolveInfo()->path
        );

        return "Validation failed for the field [$path].";
    }

    /**
     * @param mixed       $root
     * @param array       $args
     * @param mixed       $context
     * @param ResolveInfo $resolveInfo
     *
     * @throws \Exception
     */
    protected function validateArgumentsBeforeValidationDirectives($root, array $args, $context, ResolveInfo $resolveInfo)
    {
        if (! $this->currentRules) {
            return;
        }

        $validator = validator(
            $args,
            $this->currentRules,
            $this->currentMessages,
            [
                'root' => $root,
                'context' => $context,
                // This makes it so that we get an instance of our own Validator class
                'resolveInfo' => $resolveInfo,
            ]
        );

        if ($validator->fails()) {
            $messageBag = $validator->errors();
            foreach ($messageBag->getMessages() as $key => $errorMessages) {
                foreach ($errorMessages as $errorMessage) {
                    $this->currentValidationErrorBuffer->push($errorMessage, $key);
                }
            }
        }

        $this->flushErrorBufferIfHasErrors();

        // reset current rules and messages
        $this->currentRules = [];
        $this->currentMessages = [];
    }

    /**
     * @throws \Exception
     */
    protected function flushErrorBufferIfHasErrors()
    {
        if ($this->currentValidationErrorBuffer->hasErrors()) {
            $this->currentValidationErrorBuffer->flush(
                $this->getValidationErrorMessage()
            );
        }
    }

    /**
     * @param mixed       $root
     * @param array       $args
     * @param mixed       $context
     * @param ResolveInfo $resolveInfo
     *
     * @throws \Exception
     */
    protected function handleArgDirectivesAfterValidationDirectives($root, array &$args, $context, ResolveInfo $resolveInfo)
    {
        if (! $this->currentHandlerArgsOfArgDirectivesAfterValidationDirective) {
            return;
        }

        // copy
        $currentHandlerArgsOfArgDirectivesAfterValidationDirective = $this->currentHandlerArgsOfArgDirectivesAfterValidationDirective;

        // reset
        $this->currentHandlerArgsOfArgDirectivesAfterValidationDirective = [];

        foreach ($currentHandlerArgsOfArgDirectivesAfterValidationDirective as $handlerArgs) {
            $value = $this->handleArgDirectives(...$handlerArgs);

            if ($value instanceof NoValue) {
                continue;
            }

            $path = implode('.', $handlerArgs[2]);
            data_set($args, $path, $value);
        }

        if ($this->currentHandlerArgsOfArgDirectivesAfterValidationDirective) {
            $this->validateArgumentsBeforeValidationDirectives($root, $args, $context, $resolveInfo);
            $this->handleArgDirectivesAfterValidationDirectives($root, $args, $context, $resolveInfo);
        }
    }
}
