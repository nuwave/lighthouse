<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\Directives\NestDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\PreSaveArgumentsAware;

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

        $previous = $this->previous;
        $liftedPreSave = [];

        if ($root instanceof Model
            && $previous instanceof PreSaveArgumentsAware
        ) {
            $liftableArguments = ArgPartitioner::liftPreSaveResolversFromNest($nestedArgs, $root);

            if ($liftableArguments !== []) {
                $previousWithPreSave = $previous->withPreSaveArguments($liftableArguments);

                if ($previousWithPreSave !== null) {
                    $previous = $previousWithPreSave;
                    $liftedPreSave = $liftableArguments;
                }
            }
        }

        if ($previous !== null) {
            $root = $previous($root, $regularArgs);
        }

        $this->resolveNestedArguments($root, $nestedArgs, $liftedPreSave);

        return $root;
    }

    /** @param  list<\Nuwave\Lighthouse\Execution\Arguments\Argument>  $alreadyRun  arguments the saver ran before saving */
    protected function resolveNestedArguments(mixed $root, ArgumentSet $nestedArgs, array $alreadyRun): void
    {
        foreach ($nestedArgs->arguments as $nested) {
            $resolver = $nested->resolver;
            if ($resolver === null) {
                continue;
            }

            if (in_array($nested, $alreadyRun, strict: true)) {
                continue;
            }

            $value = $nested->value;
            if ($resolver instanceof NestDirective) {
                if ($value === null) {
                    continue;
                }

                assert($value instanceof ArgumentSet, 'NestDirective validates that @nest is used on non-list input object types.');

                // Partitioning attaches the resolvers of the children, including implicitly detected relations.
                // Its classification is irrelevant here: there is no saver to hand regular arguments to,
                // and children the saver did not run must still resolve.
                ($this->argPartitioner)($value, $root);
                $this->resolveNestedArguments($root, $value, $alreadyRun);
                continue;
            }

            $resolver($root, $value);
        }
    }
}
