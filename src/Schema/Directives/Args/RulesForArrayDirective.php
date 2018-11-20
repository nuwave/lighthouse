<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

/**
 * Allows defining rules and messages for arrays on an InputValueDefinition.
 *
 * @see RuleFactory::getRulesAndMessages() for how this is applied.
 */
class RulesForArrayDirective extends BaseDirective
{
    /** @var string */
    const NAME = 'rulesForArray';

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
