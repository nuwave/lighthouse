<?php declare(strict_types=1);

namespace Tests\Utils\Resolvers;

/**
 * @template TReturn
 */
final class SpyResolver
{
    private array $args = [];

    /**
     * @param TReturn $return
     */
    public function __construct(
        private mixed $return = null,
    ) {}

    /**
     * @return TReturn
     */
    public function __invoke(mixed $root, array $args): mixed
    {
        $this->args = $args;

        return $this->return;
    }

    public function assertArgs(callable $expect): void
    {
        $expect($this->args);
    }
}
