<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class MiddlewareDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'middleware';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value): FieldValue
    {
        $checks = $this->getChecks($value);

        if ($checks) {
            if ('Query' === $value->getNodeName()) {
                graphql()->middleware()->registerQuery(
                    $value->getFieldName(),
                    $checks
                );
            } elseif ('Mutation' === $value->getNodeName()) {
                graphql()->middleware()->registerMutation(
                    $value->getFieldName(),
                    $checks
                );
            }
        }

        return $value;
    }

    /**
     * Get middleware checks.
     *
     * @param FieldValue $value
     *
     * @return array|null
     */
    protected function getChecks(FieldValue $value)
    {
        if (!in_array($value->getNodeName(), ['Mutation', 'Query'])) {
            return null;
        }

        $checks = $this->directiveArgValue('checks');

        if (!$checks) {
            return null;
        }

        if (is_string($checks)) {
            $checks = [$checks];
        }

        return $checks;
    }
}
