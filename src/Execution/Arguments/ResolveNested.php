<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Schema\Directives\NestDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class ResolveNested implements ArgResolver
{
    /** @var callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver|null */
    protected $previous;

    /** @var callable */
    protected $argPartitioner;

    /** @param  callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver|null  $previous */
    public function __construct(?callable $previous = null, ?callable $argPartitioner = null)
    {
        $this->previous = $previous;
        $this->argPartitioner = $argPartitioner ?? [ArgPartitioner::class, 'nestedArgResolvers'];
    }

    /** @param  ArgumentSet  $args */
    public function __invoke(mixed $root, $args): mixed
    {
        [$nestedArgs, $regularArgs] = ($this->argPartitioner)($args, $root);
        assert($nestedArgs instanceof ArgumentSet);

        if ($this->previous !== null) {
            $root = ($this->previous)($root, $regularArgs);
        }

        foreach ($nestedArgs->arguments as $nested) {
            $resolver = $nested->resolver;
            assert($resolver !== null, 'we know the resolver is there because we partitioned for it');

            $value = $nested->value;
            if ($resolver instanceof NestDirective) {
                if ($value === null) {
                    continue;
                }

                assert($value instanceof ArgumentSet, 'NestDirective validates that @nest is used on non-list input object types.');

                $nestResolver = new self(null, $this->argPartitioner);
                $nestResolver($root, $value);
                continue;
            }

            $resolver($root, $value);
        }

        return $root;
    }
}
