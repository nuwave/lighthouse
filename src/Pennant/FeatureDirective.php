<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Pennant;

use Laravel\Pennant\FeatureManager;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\HideDirective;

final class FeatureDirective extends HideDirective
{
    public function __construct(
        private FeatureManager $features,
    ) {
        parent::__construct();
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Include the annotated element in the schema depending on a Laravel Pennant feature.
"""
directive @feature(
    """
    The name of the feature to be checked (can be a string or class name).
    """
    name: String!
    
    """
    Specify what the state of the feature should be for the field to be included.
    """
    when: FeatureState! = ACTIVE
) on FIELD_DEFINITION | OBJECT

"""
Options for the `when` argument of `@feature`.
"""
enum FeatureState {
    """
    Indicates an active feature.
    """
    ACTIVE
    
    """
    Indicates an inactive feature.
    """
    INACTIVE
}
GRAPHQL;
    }

    protected function shouldHide(): bool
    {
        $feature = $this->directiveArgValue('name');
        $requiredFeatureState = $this->directiveArgValue('when', 'ACTIVE');

        return match ($requiredFeatureState) {
            'ACTIVE' => $this->features->inactive($feature),
            'INACTIVE' => $this->features->active($feature),
            default => throw new DefinitionException("Expected FeatureState `ACTIVE` or `INACTIVE` for argument `when` of @{$this->name()} on {$this->nodeName()}, got `{$requiredFeatureState}`."),
        };
    }
}
