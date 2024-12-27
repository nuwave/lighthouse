<?php declare(strict_types=1);

namespace Tests\Utils\Resolvers;

use function is_callable;

/**
 * @template TReturn
 */
final class SpyResolver
{
    /**
     * @var array<string, mixed>
     */
    private array $args = [];

    /**
     * @param TReturn $return
     */
    public function __construct(
        private mixed $return = null,
    ) {}

    /**
     * @param array<string, mixed> $args
     * @return TReturn
     */
    public function __invoke(mixed $root, array $args): mixed
    {
        $this->args = $args;

        if (is_callable($this->return)) {
            return ($this->return)($root, $args);
        }

        return $this->return;
    }

    public function assertArgs(callable $expect): void
    {
        $expect($this->args);
    }
}
