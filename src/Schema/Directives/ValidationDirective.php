<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Nuwave\Lighthouse\Exceptions\ValidationException;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\ProvidesRules;
use Nuwave\Lighthouse\Support\Traits\HasResolverArguments;

abstract class ValidationDirective extends BaseDirective implements FieldMiddleware, ProvidesRules
{
    use HasResolverArguments;

    /**
     * @var \Illuminate\Contracts\Validation\Factory
     */
    protected $validationFactory;

    public function __construct(ValidationFactory $validationFactory)
    {
        $this->validationFactory = $validationFactory;
    }

    /**
     * Resolve the field directive.
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $resolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
                    $this->setResolverArguments($root, $args, $context, $resolveInfo);

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

                    if ($validator->fails()) {
                        throw new ValidationException($validator);
                    }

                    return $resolver($root, $args, $context, $resolveInfo);
                }
            )
        );
    }

    public function messages(): array
    {
        return [];
    }
}
