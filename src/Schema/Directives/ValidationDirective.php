<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Traits\HasResolverArguments;
use Nuwave\Lighthouse\Support\Contracts\ArgValidationDirective;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;

abstract class ValidationDirective extends BaseDirective implements FieldMiddleware, ArgValidationDirective
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
                            $this->getRules(),
                            $this->getMessages()
                        );

                    if ($validator->fails()) {
                        throw new ValidationException($validator);
                    }

                    return $resolver($root, $args, $context, $resolveInfo);
                }
            )
        );
    }
}
