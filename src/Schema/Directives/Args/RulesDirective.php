<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Factories\RuleFactory;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

/**
 * Allows defining rules and messages on an InputValueDefinition.
 *
 * @see RuleFactory::getRulesAndMessages() for how this is applied.
 */
class RulesDirective extends BaseDirective
{
    /** @var string */
    const NAME = 'rules';

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return self::NAME;
    }
}
