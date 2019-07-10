<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ProvidesRules;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Traits\HasResolverArguments;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;

abstract class ValidationDirective extends BaseDirective implements FieldMiddleware, ProvidesRules
{
    use HasResolverArguments;

    /**
     * @var \Illuminate\Contracts\Validation\Factory
     */
    protected $validationFactory;

    /**
     * @param  \Illuminate\Contracts\Validation\Factory  $validationFactory
     * @return void
     */
    public function __construct(ValidationFactory $validationFactory)
    {
        $this->validationFactory = $validationFactory;
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $resolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
                    $validator = $this->validationFactory
                        ->make(
                            $args,
                            $this->rules(),
                            $this->messages(),
                            // The presence of those custom attributes ensures we get a GraphQLValidator
                            [
                                'root' => $root,
                                'context' => $context,
                                'resolveInfo' => $resolveInfo,
                            ]
                        );

                    // The validation exception is caught in the FieldFactory and merged with
                    // argument directive based validation
                    $validator->validate();

                    return $resolver($root, $args, $context, $resolveInfo);
                }
            )
        );
    }

    /**
     * Return custom messages for the rules.
     *
     * @return array
     */
    public function messages(): array
    {
        return [];
    }
}
