<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Schema\Directives\NestDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Utils;

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
            if ($nested->resolver instanceof NestDirective) {
                $nestResolver = new self(null, $this->argPartitioner);
                Utils::mapEach(
                    fn (ArgumentSet $argumentSet): mixed => $nestResolver($root, $argumentSet),
                    $nested->value,
                );
            } else {
                // @phpstan-ignore-next-line we know the resolver is there because we partitioned for it
                ($nested->resolver)($root, $nested->value);
            }
        }

        return $root;
    }
}
