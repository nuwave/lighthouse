<?php declare(strict_types=1);

namespace Tests\Utils\Models\User;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/** @extends \Illuminate\Database\Eloquent\Builder<\Tests\Utils\Models\User> */
final class UserBuilder extends Builder
{
    /** @param  array{company: string}  $args */
    public function companyName(array $args): self
    {
        return $this->where(static fn (self $builder): \Tests\Utils\Models\User\UserBuilder => $builder
            ->whereHas('company', static fn (EloquentBuilder $q): EloquentBuilder => $q
            ->where('name', $args['company'])));
    }

    public function named(): self
    {
        return $this->whereNotNull('name');
    }
}
